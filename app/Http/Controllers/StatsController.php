<?php

namespace App\Http\Controllers;

use App\Models\Mantra;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class StatsController
{
    /**
     * Dashboard de estadísticas. Lee EXCLUSIVAMENTE de daily_aggregates
     * (precalculado): nunca suma sesiones crudas en rangos largos.
     * Todos los rangos se anclan al "hoy" de la timezone del usuario.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $range = in_array($request->query('range'), ['week', 'month', 'year'], true)
            ? $request->query('range')
            : 'week';

        $mantraId = $request->query('mantra');
        $mantraId = $mantraId !== null && $mantraId !== ''
            ? (int) $mantraId
            : null;

        try {
            $today = now($user->timezone ?: config('app.timezone'));
        } catch (\Throwable) {
            $today = now();
        }

        $mantraKey = $mantraId ?? 0; // 0 = fila total-del-día (columna generada)

        // ── Serie temporal ──────────────────────────────────────────────
        if ($range === 'year') {
            $from = $today->copy()->subMonths(11)->startOfMonth();

            $rows = $user->dailyAggregates()
                ->where('mantra_key', $mantraKey)
                ->where('local_date', '>=', $from->toDateString())
                ->selectRaw('SUBSTR(local_date, 1, 7) as bucket, SUM(recitations) as recitations')
                ->groupBy('bucket')
                ->pluck('recitations', 'bucket');

            $series = collect(range(0, 11))->map(function ($i) use ($from, $rows) {
                $bucket = $from->copy()->addMonths($i)->format('Y-m');

                return ['key' => $bucket, 'value' => (int) ($rows[$bucket] ?? 0)];
            });
        } else {
            $days = $range === 'week' ? 7 : 30;
            $from = $today->copy()->subDays($days - 1);

            $rows = $user->dailyAggregates()
                ->where('mantra_key', $mantraKey)
                ->where('local_date', '>=', $from->toDateString())
                ->pluck('recitations', 'local_date')
                ->mapWithKeys(fn ($value, $date) => [substr((string) $date, 0, 10) => (int) $value]);

            $series = collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
                $date = $from->copy()->addDays($i)->toDateString();

                return ['key' => $date, 'value' => (int) ($rows[$date] ?? 0)];
            });
        }

        // ── Totales del rango ───────────────────────────────────────────
        $rangeTotals = $user->dailyAggregates()
            ->where('mantra_key', $mantraKey)
            ->where('local_date', '>=', $from->toDateString())
            ->selectRaw('COALESCE(SUM(recitations),0) as recitations, COALESCE(SUM(malas),0) as malas, COALESCE(SUM(duration_seconds),0) as duration_seconds, COALESCE(SUM(sessions_count),0) as sessions')
            ->first();

        $allTimeRecitations = (int) $user->dailyAggregates()
            ->where('mantra_key', $mantraKey)
            ->sum('recitations');

        // ── Racha (global o del mantra filtrado) ────────────────────────
        $streak = $user->streaks()
            ->where('mantra_key', $mantraKey)
            ->first();

        // ── Desglose por mantra dentro del rango ────────────────────────
        $byMantra = $user->dailyAggregates()
            ->whereNotNull('mantra_id')
            ->where('local_date', '>=', $from->toDateString())
            ->selectRaw('mantra_id, SUM(recitations) as recitations')
            ->groupBy('mantra_id')
            ->orderByDesc('recitations')
            ->limit(8)
            ->get();

        $mantraNames = Mantra::whereIn('id', $byMantra->pluck('mantra_id'))
            ->pluck('name', 'id');

        return Inertia::render('stats/Index', [
            'filters' => ['range' => $range, 'mantra' => $mantraId],
            'mantras' => Mantra::accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'series' => $series,
            'granularity' => $range === 'year' ? 'month' : 'day',
            'totals' => [
                'recitations' => (int) $rangeTotals->recitations,
                'malas' => (int) $rangeTotals->malas,
                'duration_seconds' => (int) $rangeTotals->duration_seconds,
                'sessions' => (int) $rangeTotals->sessions,
            ],
            'allTimeRecitations' => $allTimeRecitations,
            'streak' => [
                'current' => (int) ($streak->current_count ?? 0),
                'max' => (int) ($streak->max_count ?? 0),
            ],
            'byMantra' => $byMantra->map(fn ($row) => [
                'id' => $row->mantra_id,
                'name' => $mantraNames[$row->mantra_id] ?? '—',
                'recitations' => (int) $row->recitations,
            ]),
        ]);
    }
}
