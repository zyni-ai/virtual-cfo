<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = ! in_array('failed', $checks, true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    private function checkStorage(): string
    {
        try {
            $testFile = '.health_check_'.time();
            $disk = Storage::disk('local');

            if (! $disk->put($testFile, 'ok')) {
                return 'failed';
            }

            if (! $disk->exists($testFile)) {
                return 'failed';
            }

            $disk->delete($testFile);

            return 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    private function checkQueue(): string
    {
        try {
            $staleCount = DB::table('jobs')
                ->where('created_at', '<', now()->subHours(1)->getTimestamp())
                ->count();

            return $staleCount > 100 ? 'degraded' : 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }
}
