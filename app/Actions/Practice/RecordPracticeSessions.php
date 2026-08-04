<?php

namespace App\Actions\Practice;

use App\Enums\PracticeMode;
use App\Events\PracticeSessionsRecorded;
use App\Models\Mantra;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecordPracticeSessions
{
    /**
     * Batch upsert idempotente de sesiones de práctica.
     *
     * - Valida cada ítem por separado: uno inválido no bloquea el batch.
     * - Insert-or-ignore por uuid: reintentos de la outbox nunca duplican.
     * - Despacha PracticeSessionsRecorded SOLO con las sesiones nuevas,
     *   así los agregados diarios se incrementan una única vez.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{results: list<array{uuid: mixed, status: string, errors?: array<string, array<int, string>>}>}
     */
    public function handle(User $user, array $items): array
    {
        $results = [];
        $valid = [];

        $accessibleMantraIds = Mantra::query()
            ->accessibleBy($user)
            ->pluck('id')
            ->flip();

        foreach ($items as $item) {
            $validator = Validator::make($item, [
                'uuid' => ['required', 'uuid'],
                'mantra_id' => ['required', 'integer'],
                'mode' => ['required', Rule::enum(PracticeMode::class)],
                'recitations' => ['required', 'integer', 'min:0', 'max:1000000'],
                'completed_malas' => ['required', 'integer', 'min:0', 'max:10000'],
                'started_at' => ['required', 'date'],
                'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
                'duration_seconds' => ['required', 'integer', 'min:0', 'max:172800'],
                'local_date' => ['required', 'date_format:Y-m-d'],
            ]);

            if ($validator->fails()) {
                $results[] = [
                    'uuid' => $item['uuid'] ?? null,
                    'status' => 'invalid',
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $data = $validator->validated();

            if (! $accessibleMantraIds->has((int) $data['mantra_id'])) {
                $results[] = [
                    'uuid' => $data['uuid'],
                    'status' => 'invalid',
                    'errors' => ['mantra_id' => ['El mantra no existe o no te pertenece.']],
                ];

                continue;
            }

            $valid[$data['uuid']] = $data;
        }

        if ($valid !== []) {
            $created = DB::transaction(function () use ($user, $valid, &$results) {
                $existing = PracticeSession::query()
                    ->whereIn('uuid', array_keys($valid))
                    ->pluck('uuid')
                    ->flip();

                $created = collect();

                foreach ($valid as $uuid => $data) {
                    if ($existing->has($uuid)) {
                        $results[] = ['uuid' => $uuid, 'status' => 'duplicate'];

                        continue;
                    }

                    $created->push(PracticeSession::create([
                        ...$data,
                        'user_id' => $user->id,
                        'synced_at' => now(),
                    ]));

                    $results[] = ['uuid' => $uuid, 'status' => 'created'];
                }

                return $created;
            });

            if ($created->isNotEmpty()) {
                PracticeSessionsRecorded::dispatch($created);
            }
        }

        return ['results' => $results];
    }
}
