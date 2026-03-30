<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     * Returns application status and database connectivity.
     */
    public function check(): JsonResponse
    {
        $dbStatus = 'connected';

        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = 'disconnected';
        }

        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'db'        => $dbStatus,
        ])->withHeaders([
            'X-RateLimit-Limit'     => '100',
            'X-RateLimit-Remaining' => '99',
            'Cache-Control'         => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
