<?php

namespace App\Services\GoogleSheets;

use App\Models\Client;
use App\Models\GoogleSheetConnection;
use App\Models\Order;
use App\Models\OrderInconfirmation;
use App\Models\OrderItems;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SheetSyncLog;
use App\Models\willaya;
use App\Models\town;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes mapped sheet rows and creates orders through the same path
 * as OrderRepository::store(), adapted for sheet data.
 *
 * SKU resolution: exact match on ProductVariant.sku first,
 * then fuzzy match on product/variant name → marks as needs_review.
 */
class SheetOrderImportService
{
    private GoogleSheetConnection $connection;
    private ColumnMapperService $mapper;

    public function __construct(GoogleSheetConnection $connection)
    {
        $this->connection = $connection;
        $this->mapper = new ColumnMapperService();
    }

    /**
     * Import a batch of mapped rows. Returns summary stats.
     */
    public function importRows(array $rows): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'needs_review' => 0, 'failed' => 0];
        $mapping = $this->connection->column_mapping ?? [];

        foreach ($rows as $row) {
            try {
                $result = $this->importSingleRow($row, $mapping);
                $stats[$result]++;
            } catch (\Exception $e) {
                Log::error("Sheet import row {$row['row_number']} failed: " . $e->getMessage());
                $this->logRow($row, 'failed', $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Import a single row. Returns status string.
     */
    private function importSingleRow(array $row, array $mapping): string
    {
        $mapped = $this->mapper->applyMapping($mapping, $row['data']);

        // Check idempotency
        if ($this->isDuplicate($mapped, $row)) {
            return 'skipped';
        }

        // Validate required fields
        $missing = $this->validateRequired($mapped);
        if (!empty($missing)) {
            $this->logRow($row, 'failed', 'Missing required fields: ' . implode(', ', $missing));
            return 'failed';
        }

        // Resolve product/SKU
        $productResolution = $this->resolveProduct($mapped);
        if ($productResolution['status'] === 'needs_review') {
            $this->logRow($row, 'needs_review', $productResolution['reason'], $productResolution['field'] ?? 'product');
            return 'needs_review';
        }
        if ($productResolution['status'] === 'failed') {
            $this->logRow($row, 'failed', $productResolution['reason']);
            return 'failed';
        }

        // Resolve wilaya
        $wilayaId = $this->resolveWilaya($mapped['wilaya'] ?? '');
        if (!$wilayaId) {
            $this->logRow($row, 'failed', "Wilaya not found: " . ($mapped['wilaya'] ?? ''));
            return 'failed';
        }

        // Resolve delivery type
        $deliveryType = $this->resolveDeliveryType($mapped['delivery_type'] ?? '');

        // Create the order using the same pattern as OrderRepository::store()
        $orderId = $this->createOrder($mapped, $productResolution, $wilayaId, $deliveryType, $row);

        if ($orderId) {
            $this->logRow($row, 'success', null, null, $orderId);
            return 'created';
        }

        return 'failed';
    }

    /**
     * Check if this row was already imported.
     */
    private function isDuplicate(array $mapped, array $row): bool
    {
        $orderRef = $mapped['order_reference'] ?? null;

        if ($orderRef) {
            // Check by order reference
            $exists = SheetSyncLog::where('store_id', $this->connection->sid)
                ->where('order_reference', $orderRef)
                ->where('status', 'success')
                ->exists();
            if ($exists) return true;
        }

        // Check by row hash
        $hash = md5(json_encode($row['data']));
        return SheetSyncLog::where('store_id', $this->connection->sid)
            ->where('row_hash', $hash)
            ->where('status', 'success')
            ->exists();
    }

    /**
     * Validate required fields are present.
     */
    private function validateRequired(array $mapped): array
    {
        $missing = [];
        $required = ['customer_name', 'phone_primary', 'wilaya', 'commune'];

        foreach ($required as $field) {
            if (empty($mapped[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Resolve product from SKU or product name.
     * Enforces exact SKU matching. Fuzzy name matches do not auto-create orders.
     * Returns ['status' => 'found'|'needs_review'|'failed', 'variant' => ProductVariant|null, 'product' => Product|null]
     */
    private function resolveProduct(array $mapped): array
    {
        $storeId = $this->connection->sid;
        $sku = trim($mapped['sku'] ?? '');
        $productName = trim($mapped['product'] ?? '');

        // 1. Try exact SKU match (enforces exact SKU equality matching, matching Shopify/WooCommerce)
        if ($sku) {
            $variant = ProductVariant::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('sku', $sku)
                ->first();

            if ($variant) {
                return [
                    'status' => 'found',
                    'variant' => $variant,
                    'product' => $variant->product,
                ];
            }
        }

        // 2. Generate a fuzzy suggestion/hint (never auto-creates the order)
        $suggestion = null;
        if ($productName) {
            $product = Product::where('store_id', $storeId)
                ->where(function ($q) use ($productName) {
                    $q->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($productName))])
                      ->orWhereRaw('LOWER(TRIM(nickname)) = ?', [strtolower(trim($productName))]);
                })
                ->first();

            if ($product) {
                $variant = $product->variants->first();
                if ($variant) {
                    $suggestion = [
                        'product' => $product,
                        'variant' => $variant,
                        'reason' => "Suggested match by name: '{$product->name}' (SKU: '{$variant->sku}')",
                    ];
                }
            } else {
                // Partial name match
                $product = Product::where('store_id', $storeId)
                    ->where(function ($q) use ($productName) {
                        $q->where('name', 'LIKE', '%' . $productName . '%')
                          ->orWhere('nickname', 'LIKE', '%' . $productName . '%');
                    })
                    ->first();

                if ($product) {
                    $variant = $product->variants->first();
                    if ($variant) {
                        $suggestion = [
                            'product' => $product,
                            'variant' => $variant,
                            'reason' => "Suggested match by partial name: '{$product->name}' (SKU: '{$variant->sku}')",
                        ];
                    }
                }
            }
        }

        // 3. Fallback partial SKU suggestion
        if ($sku && !$suggestion) {
            $variant = ProductVariant::whereHas('product', fn($q) => $q->where('store_id', $storeId))
                ->where('sku', 'LIKE', '%' . $sku . '%')
                ->first();

            if ($variant) {
                $suggestion = [
                    'product' => $variant->product,
                    'variant' => $variant,
                    'reason' => "Suggested match by partial SKU: '{$variant->product->name}' (SKU: '{$variant->sku}')",
                ];
            }
        }

        if (!$sku && !$productName) {
            return ['status' => 'failed', 'reason' => 'No product/SKU column mapped or value empty.'];
        }

        // Return as review required with suggestion
        return [
            'status' => 'needs_review',
            'variant' => $suggestion['variant'] ?? null,
            'product' => $suggestion['product'] ?? null,
            'reason' => $suggestion ? $suggestion['reason'] : "No matching product found for SKU '{$sku}' / name '{$productName}' in store.",
            'field' => 'product',
        ];
    }

    /**
     * Resolve wilaya from text or numeric ID.
     */
    private function resolveWilaya(string $value): ?int
    {
        $value = trim($value);

        // Try numeric first
        if (is_numeric($value)) {
            $exists = willaya::where('wid', (int) $value)->exists();
            return $exists ? (int) $value : null;
        }

        // Try by name
        $wilaya = willaya::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($value)])->first();
        return $wilaya?->wid;
    }

    /**
     * Resolve delivery type from text.
     * Returns 0 (home/domicile) or 1 (stop desk).
     */
    private function resolveDeliveryType(string $value): int
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        $stopDeskKeywords = ['stop desk', 'stopdesk', 'stop_desk', 'point relais', 'pickup', '1'];
        foreach ($stopDeskKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 1;
            }
        }

        return 0; // Default: home delivery
    }

    /**
     * Create the order and its related records.
     * Follows the same structure as OrderRepository::store().
     */
    private function createOrder(array $mapped, array $productResolution, int $wilayaId, int $deliveryType, array $row): ?int
    {
        return DB::transaction(function () use ($mapped, $productResolution, $wilayaId, $deliveryType, $row) {
            // 1. Create/update client
            $phone = $this->normalizePhone($mapped['phone_primary']);
            $client = Client::updateOrCreate(
                ['phone_number_1' => $phone],
                [
                    'full_name' => $mapped['customer_name'],
                    'phone_number_2' => $this->normalizePhone($mapped['phone_secondary'] ?? ''),
                    'wilaya' => $wilayaId,
                    'town' => $mapped['commune'] ?? '',
                    'address' => $mapped['address'] ?? '',
                ]
            );

            // 2. Create order
            $order = Order::create([
                'oid' => time() . mt_rand(1000, 9999),
                'cid' => $client->id,
                'sid' => $this->connection->sid,
                'app_id' => 0,
                'from' => 'google_sheets',
                'type' => 'normal',
            ]);

            // 3. Create order details
            $price = (float) ($mapped['price'] ?? 0);
            $quantity = max(1, (int) ($mapped['quantity'] ?? 1));

            $order->details()->create([
                'oid' => $order->oid,
                'price' => $price,
                'total' => $price,
                'delivery_price' => 0,
                'discount' => 0,
                'commenter' => $mapped['note'] ?? '',
                'stopdesk' => $deliveryType,
            ]);

            // 4. Create order items
            if ($productResolution['variant'] && $productResolution['product']) {
                OrderItems::create([
                    'oid' => $order->oid,
                    'product_id' => $productResolution['product']->id,
                    'vid' => $productResolution['variant']->id,
                    'quantity' => $quantity,
                ]);
            }

            // 5. Create order log
            $store = Store::find($this->connection->sid);
            OrderLog::create([
                'oid' => $order->oid,
                'aid' => auth()->id() ?? $store->created_by ?? 1,
                'statu_old' => 1,
                'statu_new' => 1,
                'text' => trans('Order imported from Google Sheets'),
            ]);

            // 6. Set initial status: in confirmation
            $order->Inconfirmation()->create([
                'fsid' => 1,
            ]);

            return $order->id;
        });
    }

    /**
     * Normalize Algerian phone number.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+213')) {
            $phone = '0' . substr($phone, 4);
        } elseif (str_starts_with($phone, '213') && strlen($phone) > 9) {
            $phone = '0' . substr($phone, 3);
        }

        return $phone;
    }

    /**
     * Log a row's import result.
     */
    private function logRow(array $row, string $status, ?string $error = null, ?string $matchedField = null, ?int $orderId = null): void
    {
        $mapped = $this->mapper->applyMapping($this->connection->column_mapping ?? [], $row['data']);
        $orderRef = $mapped['order_reference'] ?? null;
        $hash = md5(json_encode($row['data']));

        SheetSyncLog::create([
            'store_id' => $this->connection->sid,
            'sheet_id' => $this->connection->sheet_id,
            'status' => $status,
            'row_number' => $row['row_number'],
            'row_data' => $row['data'],
            'error_message' => $error,
            'matched_field' => $matchedField,
            'order_id' => $orderId,
            'row_hash' => $hash,
            'order_reference' => $orderRef,
        ]);
    }
}
