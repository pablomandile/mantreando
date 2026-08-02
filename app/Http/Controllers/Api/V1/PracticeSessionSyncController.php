<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Practice\RecordPracticeSessions;
use App\Http\Requests\Api\SyncPracticeSessionsRequest;
use Illuminate\Http\JsonResponse;

class PracticeSessionSyncController
{
    public function __invoke(
        SyncPracticeSessionsRequest $request,
        RecordPracticeSessions $action,
    ): JsonResponse {
        $result = $action->handle($request->user(), $request->validated('sessions'));

        return response()->json([
            'data' => [
                'results' => $result['results'],
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
