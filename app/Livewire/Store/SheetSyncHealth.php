<?php

namespace App\Livewire\Store;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\GoogleSheetConnection;
use App\Models\SheetSyncLog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\OrderItems;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Client;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;

class SheetSyncHealth extends Component
{
    use WithPagination;

    public $connection = null;
    public $syncStats = [];
    public $statusFilter = 'needs_review'; // needs_review, failed, success, all
    public $resolveRowId = null;
    public $resolveProductId = null;
    public $resolveVariantId = null;
    public $availableProducts = [];
    public $availableVariants = [];

    public function mount()
    {
        $storeId = auth()->user()->userStore->store_id;
        $this->connection = GoogleSheetConnection::where('sid', $storeId)->first();
        $this->loadStats();
        $this->availableProducts = Product::where('store_id', $storeId)->with('variants')->get()->toArray();
    }

    public function loadStats()
    {
        if (!$this->connection) {
            $this->syncStats = ['total' => 0, 'success' => 0, 'failed' => 0, 'needs_review' => 0];
            return;
        }

        $storeId = $this->connection->sid;
        $this->syncStats = [
            'total' => SheetSyncLog::where('store_id', $storeId)->count(),
            'success' => SheetSyncLog::where('store_id', $storeId)->where('status', 'success')->count(),
            'failed' => SheetSyncLog::where('store_id', $storeId)->where('status', 'failed')->count(),
            'needs_review' => SheetSyncLog::where('store_id', $storeId)->needsReview()->count(),
        ];
    }

    public function setFilter($filter)
    {
        $this->statusFilter = $filter;
        $this->resetPage();
    }

    public function startResolve($logId)
    {
        $this->resolveRowId = $logId;
        $this->resolveProductId = null;
        $this->resolveVariantId = null;
        $this->availableVariants = [];
    }

    public function updatedResolveProductId($value)
    {
        if ($value) {
            $product = Product::with('variants')->find($value);
            $this->availableVariants = $product ? $product->variants->toArray() : [];
            if (count($this->availableVariants) === 1) {
                $this->resolveVariantId = $this->availableVariants[0]['id'];
            }
        } else {
            $this->availableVariants = [];
        }
    }

    public function resolveRow()
    {
        if (!$this->resolveRowId || !$this->resolveProductId || !$this->resolveVariantId) {
            return;
        }

        $log = SheetSyncLog::find($this->resolveRowId);
        if (!$log || $log->status !== 'needs_review') {
            return;
        }

        $rowData = $log->row_data;
        $product = Product::find($this->resolveProductId);
        $variant = ProductVariant::find($this->resolveVariantId);

        if (!$product || !$variant) {
            return;
        }

        try {
            // Create the order using the row data
            $storeId = $this->connection->sid;
            $mapping = $this->connection->column_mapping ?? [];

            $mapper = new \App\Services\GoogleSheets\ColumnMapperService();
            $mapped = $mapper->applyMapping($mapping, $rowData);

            // Resolve wilaya
            $wilayaId = null;
            $wilayaValue = trim($mapped['wilaya'] ?? '');
            if (is_numeric($wilayaValue)) {
                $wilayaId = (int) $wilayaValue;
            } else {
                $w = \App\Models\willaya::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($wilayaValue)])->first();
                $wilayaId = $w?->wid;
            }

            if (!$wilayaId) {
                $this->dispatch('notify', type: 'error', message: trans('Wilaya not found'));
                return;
            }

            DB::transaction(function () use ($mapped, $product, $variant, $wilayaId, $log, $storeId) {
                $phone = preg_replace('/[^0-9+]/', '', $mapped['phone_primary'] ?? '');
                $client = Client::updateOrCreate(
                    ['phone_number_1' => $phone],
                    [
                        'full_name' => $mapped['customer_name'] ?? '',
                        'phone_number_2' => $mapped['phone_secondary'] ?? '',
                        'wilaya' => $wilayaId,
                        'town' => $mapped['commune'] ?? '',
                        'address' => $mapped['address'] ?? '',
                    ]
                );

                $order = Order::create([
                    'oid' => time() . mt_rand(1000, 9999),
                    'cid' => $client->id,
                    'sid' => $storeId,
                    'app_id' => 0,
                    'from' => 'google_sheets',
                    'type' => 'normal',
                ]);

                $price = (float) ($mapped['price'] ?? 0);
                $quantity = max(1, (int) ($mapped['quantity'] ?? 1));
                $deliveryType = $this->resolveDeliveryType($mapped['delivery_type'] ?? '');

                $order->details()->create([
                    'oid' => $order->oid,
                    'price' => $price,
                    'total' => $price,
                    'delivery_price' => 0,
                    'discount' => 0,
                    'commenter' => $mapped['note'] ?? '',
                    'stopdesk' => $deliveryType,
                ]);

                OrderItems::create([
                    'oid' => $order->oid,
                    'product_id' => $product->id,
                    'vid' => $variant->id,
                    'quantity' => $quantity,
                ]);

                OrderLog::create([
                    'oid' => $order->oid,
                    'aid' => auth()->id() ?? Store::find($storeId)->created_by ?? 1,
                    'statu_old' => 1,
                    'statu_new' => 1,
                    'text' => trans('Order imported from Google Sheets (manual resolve)'),
                ]);

                $order->Inconfirmation()->create(['fsid' => 1]);

                // Mark log as resolved
                $log->update([
                    'status' => 'success',
                    'order_id' => $order->id,
                    'resolved_at' => now(),
                ]);
            });

            $this->resolveRowId = null;
            $this->loadStats();
            $this->dispatch('notify', type: 'success', message: trans('Order created successfully from review item'));

        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: trans('Error: ') . $e->getMessage());
        }
    }

    public function skipRow($logId)
    {
        $log = SheetSyncLog::find($logId);
        if ($log) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Skipped by user',
                'resolved_at' => now(),
            ]);
            $this->loadStats();
        }
    }

    private function resolveDeliveryType(string $value): int
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        $stopDeskKeywords = ['stop desk', 'stopdesk', 'stop_desk', 'point relais', 'pickup', '1'];
        foreach ($stopDeskKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) return 1;
        }
        return 0;
    }

    public function render()
    {
        $storeId = auth()->user()->userStore->store_id;

        $logsQuery = SheetSyncLog::where('store_id', $storeId);

        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'needs_review') {
                $logsQuery->needsReview();
            } else {
                $logsQuery->where('status', $this->statusFilter);
            }
        }

        $logs = $logsQuery->latest()->paginate(15);

        return view('livewire.store.sheet-sync-health', [
            'logs' => $logs,
        ]);
    }
}
