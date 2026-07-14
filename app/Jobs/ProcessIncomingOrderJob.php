<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StoreIntegration;
use App\Models\WebhookLog;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\ProductVariant;
use App\Models\Client;
use App\Models\fees;
use App\Models\UserStore;
use App\Models\Role;
use App\Models\OrderLog;
use Exception;

class ProcessIncomingOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    protected $integrationId;
    protected $webhookLogId;
    protected $normalizedData;

    public function __construct(int $integrationId, int $webhookLogId, array $normalizedData)
    {
        $this->integrationId = $integrationId;
        $this->webhookLogId = $webhookLogId;
        $this->normalizedData = $normalizedData;
    }

    public function handle(): void
    {
        $integration = StoreIntegration::findOrFail($this->integrationId);
        $logEntry = WebhookLog::findOrFail($this->webhookLogId);
        $data = $this->normalizedData;

        // Ensure we have a platform order ID for idempotency
        $platformOrderId = $data['platform_order_id'] ?? null;
        if (!$platformOrderId) {
            $this->markFailed($logEntry, 'Missing platform_order_id in normalized data');
            return;
        }

        $logEntry->update(['platform_order_id' => $platformOrderId]);

        // 1. Idempotency Check: Don't process the same order twice
        $existingOrder = Order::where('store_integration_id', $integration->id)
            ->where('platform_order_id', $platformOrderId)
            ->first();

        if ($existingOrder) {
            $logEntry->update([
                'status' => 'processed',
                'processing_result' => 'duplicate',
                'response_code' => 200
            ]);
            return;
        }

        DB::beginTransaction();

        try {
            $storeId = $integration->store_id;

            // 2. Validate Items & Find Variants
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new Exception("No items found in order");
            }

            // We handle the first item differently because of legacy delivery fee logic
            $firstItemSku = $items[0]['sku'];
            $variant = ProductVariant::where('sku', $firstItemSku)->first();
            
            // Note: If variant is not found, we still create the order but flag it.
            // The original controller threw firstOrFail() which broke silently.
            $hasUnmatchedItems = !$variant;

            // 3. Create or Update Client
            $client = Client::updateOrCreate(
                ['phone_number_1' => $data['phone1']],
                [
                    'full_name'      => $data['client_name'],
                    'phone_number_2' => $data['phone2'] ?? '',
                    'email'          => $data['email'] ?? '',
                    'wilaya'         => $data['wilaya'],
                    'town'           => $data['city'] ?? '',
                    'address'        => $data['address'] ?? '',
                ]
            );

            // 4. Calculate Delivery Fees
            $app_id = 0;
            $deliveryPrice = 0;
            
            if ($variant) {
                $fee = fees::where('product_id', $variant->product_id)
                    ->where('wid', $data['wilaya'])
                    ->first();

                if ($fee) {
                    $app_id = $fee->app_id;
                    $deliveryPrice = ($data['delivery_type'] == 1) ? $fee->c_s_p : ($fee->c_d_p ?? 0);
                }
            }

            // 5. Round Robin Agent Assignment
            $assignedUserId = $this->assignAgent($storeId);

            // 6. Create Main Order Record
            $order = Order::create([
                'oid'                  => $this->generateOrderId(),
                'cid'                  => $client->id,
                'sid'                  => $storeId,
                'app_id'               => $app_id,
                'aid'                  => $assignedUserId,
                'from'                 => $integration->platform,
                'platform_order_id'    => $platformOrderId,
                'store_integration_id' => $integration->id,
            ]);

            // 7. Process Items and Calculate Subtotal
            $calculatedPrice = 0;
            foreach ($items as $item) {
                $itemVariant = ProductVariant::where('sku', $item['sku'])->first();
                if ($itemVariant) {
                    $price = $itemVariant->product->price ?? 0;
                    OrderItems::create([
                        'oid'        => $order->oid,
                        'sku'        => $item['sku'],
                        'product_id' => $itemVariant->product_id,
                        'vid'        => $itemVariant->id,
                        'quantity'   => $item['quantity'],
                        'price'      => $price,
                    ]);
                    $calculatedPrice += $price * $item['quantity'];
                } else {
                    $hasUnmatchedItems = true;
                    // We log unmatched SKUs to OrderLogs later
                }
            }

            // Fallback to webhook subtotal if we couldn't match items
            if ($calculatedPrice == 0) {
                $calculatedPrice = $data['subtotal'] ?? 0;
            }

            $discount = $data['discount'] ?? 0;
            $total = $calculatedPrice + $deliveryPrice - $discount;

            // 8. Create Order Details
            $order->details()->create([
                'oid'               => $order->oid,
                'price'             => $calculatedPrice,
                'total'             => $total,
                'delivery_price'    => $deliveryPrice,
                'commenter'         => $data['comment'] ?? '',
                'stopdesk'          => $data['delivery_type'] ?? 1,
                'discount'          => $discount,
                'original_currency' => $data['currency'] ?? 'DZD',
                'original_total'    => $data['total'] ?? 0,
            ]);

            // 9. Initial Status (In Confirmation)
            $order->Inconfirmation()->create([
                'fsid' => 1,
                'aid'  => $integration->created_by,
            ]);

            OrderLog::create([
                'oid'       => $order->oid,
                'aid'       => $integration->created_by,
                'statu_old' => 1,
                'statu_new' => 1,
                'text'      => 'Order created via ' . ucfirst($integration->platform) . ' webhook',
            ]);

            if ($hasUnmatchedItems) {
                OrderLog::create([
                    'oid'       => $order->oid,
                    'aid'       => $integration->created_by,
                    'statu_old' => 1,
                    'statu_new' => 1,
                    'text'      => 'WARNING: Some items in this order did not match any SKU in the catalog.',
                ]);
            }

            DB::commit();

            // 10. Update Logs & Integration status
            $logEntry->update([
                'status' => 'processed',
                'processing_result' => $hasUnmatchedItems ? 'unmatched' : 'processed',
                'response_code' => 200
            ]);

            $integration->markConnected();

        } catch (Exception $e) {
            DB::rollBack();
            $this->markFailed($logEntry, $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e; // Re-throw so queue worker retries
        }
    }

    private function assignAgent($storeId)
    {
        $activeConfirmatrices = UserStore::where('is_active', true)
            ->where('store_id', $storeId)
            ->orderBy('id')
            ->whereHas('user.roles', function ($query) {
                $query->where('roles.rid', Role::AGENT); // 4 = Agent
            })
            ->get();

        $availableAgents = $activeConfirmatrices->filter(function ($userStore) use ($storeId) {
            if (!$userStore->order_limit) return true;

            $todayCount = Order::where('sid', $storeId)
                ->where('aid', $userStore->user_id)
                ->whereDate('created_at', today())
                ->count();

            return $todayCount < $userStore->order_limit;
        });

        $agentIds = $availableAgents->pluck('user_id');

        if ($agentIds->count() === 1) {
            return $agentIds->first();
        } elseif ($agentIds->count() > 1) {
            $lastOrder = Order::where('sid', $storeId)
                ->whereIn('aid', $agentIds)
                ->latest()
                ->first();

            if (!$lastOrder?->aid) {
                return $agentIds->first();
            } else {
                $lastIndex = $agentIds->search($lastOrder->aid);
                $nextIndex = $lastIndex !== false ? ($lastIndex + 1) % $agentIds->count() : 0;
                return $agentIds->values()[$nextIndex];
            }
        }
        
        return null;
    }

    private function generateOrderId(): string
    {
        return time() . mt_rand(1000, 9999);
    }

    private function markFailed($logEntry, string $error): void
    {
        Log::error("Webhook Job Failed [Log ID: {$this->webhookLogId}]: $error");
        $logEntry->update([
            'status' => 'failed',
            'processing_result' => 'failed',
            'error_message' => substr($error, 0, 1000), // Prevent text overflow
            'response_code' => 500
        ]);
    }
}
