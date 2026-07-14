<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\StoreIntegration;
use App\Models\WebhookLog;
use App\Services\StoreIntegrations\PlatformAdapterFactory;
use App\Jobs\ProcessIncomingOrderJob;

class StoreWebhookController extends Controller
{
    /**
     * Handle incoming webhooks from all connected platforms.
     * Route: /api/webhooks/{platform}/{uuid}
     */
    public function handle(Request $request, string $platform, string $uuid)
    {
        // 1. Find the store integration by UUID
        $integration = StoreIntegration::where('platform', $platform)
            ->where('webhook_uuid', $uuid)
            ->first();

        if (!$integration || !$integration->isActive()) {
            Log::warning("Webhook received for unknown or inactive UUID: {$platform}/{$uuid}");
            return response()->json(['error' => 'Integration not found or inactive'], 404);
        }

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }

        // 2. Initial log entry
        $logEntry = WebhookLog::create([
            'sid' => $integration->creator->id,
            'store_integration_id' => $integration->id,
            'source' => $request->ip(),
            'platform' => $platform,
            'event_type' => $request->header('X-Shopify-Topic', $request->header('X-WC-Webhook-Topic', 'order.created')),
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            // 3. Resolve Adapter
            $adapter = PlatformAdapterFactory::make($platform);

            // 4. Verify Signature
            $isValid = $adapter->verifySignature($request, $integration->webhook_secret);
            $logEntry->update(['signature_valid' => $isValid]);

            if (!$isValid) {
                $logEntry->update([
                    'status' => 'failed',
                    'error_message' => 'Signature verification failed',
                    'response_code' => 401
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // 5. Normalize Payload
            $normalizedData = $adapter->normalizeOrderPayload($payload);

            if (empty($normalizedData)) {
                $logEntry->update([
                    'status' => 'failed',
                    'processing_result' => 'failed',
                    'error_message' => 'Adapter returned empty or invalid normalized payload',
                    'response_code' => 422
                ]);
                return response()->json(['error' => 'Unprocessable payload'], 422);
            }

            // 6. Dispatch to Queue
            ProcessIncomingOrderJob::dispatch($integration->id, $logEntry->id, $normalizedData);

            // Update timestamp
            $integration->update(['last_webhook_received_at' => now()]);

            // 7. Return 200 OK immediately
            return response()->json(['success' => true, 'message' => 'Webhook received and queued for processing']);

        } catch (\Throwable $e) {
            Log::error("StoreWebhookController Error: " . $e->getMessage(), [
                'platform' => $platform,
                'uuid' => $uuid,
                'trace' => $e->getTraceAsString()
            ]);

            $logEntry->update([
                'status' => 'failed',
                'error_message' => 'System error: ' . $e->getMessage(),
                'response_code' => 500
            ]);

            return response()->json(['error' => 'Internal server error processing webhook'], 500);
        }
    }
}
