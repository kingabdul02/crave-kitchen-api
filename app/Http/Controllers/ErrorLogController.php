<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ErrorLogController extends BaseApiController
{
    /**
     * Log a single error from the frontend
     */
    public function logError(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'message' => 'required|string',
            'stack' => 'nullable|string',
            'url' => 'required|string',
            'timestamp' => 'required|string',
            'userAgent' => 'required|string',
            'sessionId' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'context' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $errorData = $validator->validated();

        // Add server-side context
        $errorData['server_timestamp'] = now()->toISOString();
        $errorData['ip_address'] = $request->ip();
        $errorData['user_id'] = auth()->id();

        // Log based on severity
        $logLevel = $this->getLogLevel($errorData['severity']);
        $logMessage = "Frontend Error [{$errorData['severity']}]: {$errorData['message']}";

        Log::log($logLevel, $logMessage, [
            'error_data' => $errorData,
            'request_id' => $request->header('X-Request-ID'),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer')
        ]);

        // For critical errors, also log to a separate channel or send alerts
        if ($errorData['severity'] === 'critical') {
            $this->handleCriticalError($errorData, $request);
        }

        return $this->sendResponse(['logged' => true], 'Error logged successfully');
    }

    /**
     * Log multiple errors in batch from the frontend
     */
    public function logErrorBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'errors' => 'required|array',
            'errors.*.id' => 'required|string',
            'errors.*.message' => 'required|string',
            'errors.*.stack' => 'nullable|string',
            'errors.*.url' => 'required|string',
            'errors.*.timestamp' => 'required|string',
            'errors.*.userAgent' => 'required|string',
            'errors.*.sessionId' => 'required|string',
            'errors.*.severity' => 'required|in:low,medium,high,critical',
            'errors.*.context' => 'nullable|array',
            'sessionId' => 'required|string',
            'timestamp' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $batchData = $validator->validated();
        $loggedCount = 0;
        $criticalCount = 0;

        foreach ($batchData['errors'] as $errorData) {
            // Add server-side context
            $errorData['server_timestamp'] = now()->toISOString();
            $errorData['ip_address'] = $request->ip();
            $errorData['user_id'] = auth()->id();
            $errorData['batch_id'] = $batchData['sessionId'];

            // Log the error
            $logLevel = $this->getLogLevel($errorData['severity']);
            $logMessage = "Frontend Error Batch [{$errorData['severity']}]: {$errorData['message']}";

            Log::log($logLevel, $logMessage, [
                'error_data' => $errorData,
                'batch_info' => [
                    'session_id' => $batchData['sessionId'],
                    'batch_timestamp' => $batchData['timestamp'],
                    'total_errors' => count($batchData['errors'])
                ],
                'request_id' => $request->header('X-Request-ID')
            ]);

            $loggedCount++;

            if ($errorData['severity'] === 'critical') {
                $criticalCount++;
            }
        }

        // Handle critical errors in batch
        if ($criticalCount > 0) {
            $this->handleCriticalErrorBatch($batchData, $criticalCount, $request);
        }

        return $this->sendResponse([
            'logged' => $loggedCount,
            'critical_errors' => $criticalCount
        ], 'Error batch logged successfully');
    }

    /**
     * Get error statistics for monitoring
     */
    public function getErrorStats(Request $request): JsonResponse
    {
        // This would typically query a database or log aggregation service
        // For now, we'll return mock data

        $stats = [
            'total_errors_today' => 0,
            'critical_errors_today' => 0,
            'error_rate_per_hour' => [],
            'top_error_types' => [],
            'affected_users' => 0
        ];

        return $this->sendResponse($stats, 'Error statistics retrieved');
    }

    /**
     * Health check endpoint for error logging service
     */
    public function health(): JsonResponse
    {
        return $this->sendResponse([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ], 'Error logging service is healthy');
    }

    /**
     * Get appropriate log level for severity
     */
    private function getLogLevel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'info',
            default => 'info'
        };
    }

    /**
     * Handle critical errors with special processing
     */
    private function handleCriticalError(array $errorData, Request $request): void
    {
        // Log to critical error channel
        Log::channel('critical')->critical('Critical Frontend Error', [
            'error_data' => $errorData,
            'request_context' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID')
            ]
        ]);

        // Here you could:
        // - Send email alerts to developers
        // - Post to Slack/Discord
        // - Create incident tickets
        // - Trigger monitoring alerts
    }

    /**
     * Handle critical errors in batch
     */
    private function handleCriticalErrorBatch(array $batchData, int $criticalCount, Request $request): void
    {
        Log::channel('critical')->critical('Critical Frontend Error Batch', [
            'batch_data' => $batchData,
            'critical_count' => $criticalCount,
            'request_context' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'request_id' => $request->header('X-Request-ID')
            ]
        ]);

        // Additional alerting for multiple critical errors
        if ($criticalCount >= 5) {
            // This indicates a serious issue - trigger immediate alerts
            Log::emergency('Multiple critical frontend errors detected', [
                'critical_count' => $criticalCount,
                'session_id' => $batchData['sessionId']
            ]);
        }
    }
}
