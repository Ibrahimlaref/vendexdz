<?php

namespace App\Livewire\V2\Order\Traits;

use Carbon\Carbon;
use App\Models\OrderItems;
use App\Models\ProductVariant;
use App\Models\fees;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Store;
use App\Models\User;
use App\Models\Role;
use App\Models\Client;
use App\Models\Product;
use App\Models\OrderNots;
use App\Models\order_Comments;
use App\Models\ProductAgent;

trait OrderTrait
{
    public $items = [];
    public $availableProducts;
    public $order_type = 'Normal';
    public $delivery_price = 0;
    public $price = 0;
    public $discount = 0;
    public $total = 0;
    public $companie = 0;
    public $selectedWilayaId = null;
    public $expandedOrderId = null;
    public $stores;
    public $agents;
    public $store_id;
    public $showErrorModal = false;
    public $error_messages;

    public Order $activeOrder;

    public function initializeStore()
    {
        $user = auth()->user(); 
        $query = $user->stores();
        // if (request()->is('agent/orders') || request()->is('manager/orders')) {
        //     $query->where('created_by', '!=', $user->id);
        // }

        if ($user->hasRole(2)) {
            $query = Store::query(); // all stores
        }
        if($user->hasRole(3) || $user->hasRole(2)){
            $this->agents = User::whereHas('roles', function($q) {
                $q->where('roles.rid', Role::AGENT);
            })->get();
        }
        $this->stores = $query->latest()->get();
        $this->store_id = $this->stores->first()->id ?? null;
    }

    public function Storefilter($store){
        $this->storefilter = $store;
    }

    public function Agentfilter($agent){
        $this->agentfilter = $agent;
    }

    public function Productfilter($product){
        $this->productfilter = $product;
    }

    public function Statufilter($status){
        $this->statufilter = $status;
    }

    public function toggleExpand($id)
    {
        if ($this->expandedOrderId === $id) {
            $this->resetActiveOrder();
            return;
        }
        $this->expandedOrderId = $id;
        $this->loadOrderData($id);
    }

    public function calculateTotal()
    {
        $orderItems = OrderItems::where('oid', $this->activeOrder->oid)->get();
        $client = Client::find($this->activeOrder->cid);
        $totalDiscount = $orderItems->sum(function ($item){

            return $item->variant ? $item->variant->discount * $item['quantity'] : 0;
        });
        $this->price = $orderItems->sum(function ($item) {
            $price = $item->variant ? $item->variant->product->price : 0;
            return $price * $item['quantity'];
        });
        if ($client->wilaya) {
            $fee = fees::where('product_id', $orderItems[0]['product_id'])
                ->where('wid', $client->wilaya)
                ->first();
            $this->delivery_price = $fee
            ? ($this->activeOrder->details->stopdesk ? $fee->c_s_p : $fee->c_d_p)
            : 0;
        }
        $this->total = ($this->price + $this->delivery_price) - ($this->activeOrder->details->discount ?? 0);
        $this->activeOrder->details->update([
            'total' => $this->total,
            'price' => $this->price,
            'delivery_price' => $this->delivery_price
        ]);
        $this->dispatch('orderTotalsUpdated', [
            'price'          => $this->price,
            'delivery_price' => $this->delivery_price,
            'discount'       => $this->activeOrder->details->discount,
            'total'          => $this->total,
            'totalDiscount'  => $totalDiscount
        ]);
    }
    protected function loadOrderData($oid)
    {
        $this->activeOrder = Order::with([
            'client.willaya',
            'logs.user', 
            'Inconfirmation.firstStepStatu', 
            'Waiting.AcceptStepStatu',
            'Indelivery.SecondStepStatu', 
            'details', 
            'items', 
            'items.variant.product',
            'Notes.user', 
            'chats.user', 
            'histories'
        ])->where('oid', $oid)
            ->first();

        if (!$this->activeOrder) return;

        if(!$this->activeOrder->app_id){
            $firstItem = $this->activeOrder->items->first();
            $fee = fees::where('product_id', $firstItem->product_id)
                ->where('wid', $this->activeOrder->client->wilaya)
                ->first();
            if($fee){
                $this->activeOrder->update([
                    'app_id' => $fee->app_id
                ]);
            }
        }

        $this->availableProducts = Product::where('store_id', $this->activeOrder->sid)
            ->with('variants')
            ->get();
    }

    public function setDateToNull(){
        $this->start_date = null;
        $this->end_date = null;
    }
    
    public function getVariants($id)
    {
        $product = Product::find($id);
        return $product ? $product->variants : [];
    }

    public function proposeStatus($orderId, $statusId){
       if (in_array((int)$statusId, [3, 4])) {
           $this->tempOrderId = $orderId;
           $this->tempStatusId = $statusId;
           $this->scheduleTime = now()->addHours(2)->format('Y-m-d\TH:i'); // Default 2 hours later
           $this->showTimerModal = true;
       } else {
           $this->updateStatus($orderId, $statusId);
        }
    }

    public function decrementQuantity($orderItems){
        \DB::transaction(function () use ($orderItems) {
            foreach ($orderItems as $item) {
                $variant = $item->variant;

                if ($variant->quantity < $item->quantity) {
                        $this->error_messages = trans('Insufficient stock for product '). $variant->product->name;
                        $this->showErrorModal = true;
                    return;
                }
            }
            foreach ($orderItems as $item) {
                $item->variant->decrement('quantity', $item->quantity);
            }

        });
    }
    public function incrementQuantity($orderItems){
        foreach($orderItems as $item){
            $item->variant->increment('quantity', $item->quantity);
        }
    }

    /**
     * Snapshot both commission rates into order_items at confirmation time.
     *
     * - confirmatrice rate: ProductAgent.portion (per agent × product)
     * - gérant rate: Product.gerant_rate (per product, applies to all gérants)
     *
     * Both are locked at confirmation. Whether they ever count toward payroll
     * depends on the order reaching delivered status later (step=2, statu_new=12).
     * A later rate change never rewrites historic snapshots.
     */
    private function snapshotConfirmationRate(Order $order, int $agentId): void
    {
        foreach ($order->items as $item) {
            // Confirmatrice rate from ProductAgent pivot
            $productAgent = ProductAgent::where('aid', $agentId)
                ->where('product_id', $item->product_id)
                ->first();

            $confirmatriceRate = $productAgent?->portion ?? 0;

            if (!$productAgent) {
                \Log::warning("Commission: No ProductAgent record for agent {$agentId} / product {$item->product_id} on order {$order->oid}. Confirmatrice rate defaulted to 0.");
            }

            // Gérant rate from Product itself
            $product = $item->product;
            $gerantRate = $product?->gerant_rate ?? 0;

            $item->update([
                'rate_at_confirmation' => $confirmatriceRate,
                'gerant_rate_at_confirmation' => $gerantRate,
            ]);
        }
    }

    public function confirmStatusWithTimer()
    {
        // Ensure we have an order ID and a time
        if (!$this->tempOrderId || !$this->scheduleTime) return;

        $order = Order::find($this->tempOrderId);

        $authUser = auth()->user();

        if ($order && $order->Inconfirmation) {
            // 1. Capture the CURRENT status before we change it (for the logs)
            $oldStatusId = $order->Inconfirmation->fsid;

            // 2. Format the full Date and Time
            $formattedTime = Carbon::parse($this->scheduleTime)->format('Y-m-d H:i:s');

            // 3. Update the Order Status
            $order->Inconfirmation->update(['fsid' => $this->tempStatusId]);

            $order->update([
                'aid' => $authUser->id,
            ]);

            // 4. Create the Timer entry with the FULL Date/Time
            \App\Models\Timer::updateOrCreate(
                ['oid' => $order->oid],
                ['time' => $formattedTime] 
            );

            // 5. IMPORTANT: Add the Log entry so you don't get the 'statu_old' error
            OrderLog::create([
                'oid'       => $order->oid,
                'aid'       => $authUser->id,
                'statu_old' => $oldStatusId,
                'statu_new' => $this->tempStatusId,
                'text'      => trans('Follow-up scheduled for: ') . $formattedTime,
            ]);

            // Sync UI
            $this->activeOrder = $order->load(['Inconfirmation.firstStepStatu', 'logs.user']);
            $this->showTimerModal = false;
            $this->selectedStatu = $this->tempStatusId;
            
            session()->flash('success', 'Status updated and follow-up scheduled for ' . $formattedTime);
        }
    }
    public function updateStatus($orderId, $statusId)
    {
        $orderId = (int) $orderId;
        $statusId = (int) $statusId;
        $order = Order::find($orderId);
        
        if ($order && $order->Inconfirmation) {
            $oldStatusId = $order->Inconfirmation->fsid;

            if ($oldStatusId == $statusId) return;

            if($statusId == 2) {
                $this->decrementQuantity($order->items);
                // Snapshot the agent's confirmation rate for payroll
                $this->snapshotConfirmationRate($order, auth()->id());
            } elseif($oldStatusId == 2) {
                $this->incrementQuantity($order->items);
            }

            if($this->showErrorModal) return;
            
            $order->Inconfirmation->update([
                'fsid' => $statusId 
            ]);

            $order->update([
                'aid' => auth()->id(),
            ]);

            OrderLog::create([
                'oid'       => $order->oid,
                'aid'       => auth()->id(),
                'statu_old' => $oldStatusId,
                'statu_new' => $statusId,
                'text'      => trans('Status updated'),
            ]);

            $this->selectedStatu = $statusId;

            // 3. Cleanup Timer
            if (!in_array($statusId, [3, 4])) {
                \App\Models\Timer::where('oid', $order->oid)->delete();
            }

            $this->activeOrder = $order->load(['Inconfirmation.firstStepStatu', 'logs.user']);
            session()->flash('success', 'Status updated and history logged.');
        }
    }
    private function resetActiveOrder()
    {
        $this->expandedOrderId = null;
        $this->reset(['availableProducts', 'price', 'delivery_price', 'discount', 'total']);
        $this->dispatch('orderItemsUpdated', []);
        $this->dispatch('orderTotalsUpdated', [
            'price'          => 0,
            'delivery_price' => 0,
            'discount'       => 0,
            'total'          => 0,
            'totalDiscount' => 0,
        ]);
    }
    public function saveNote()
    {
        if (empty($this->newNote) || !$this->activeOrder) return;

        OrderNots::create([
            'oid' => $this->activeOrder->oid,
            'uid' => auth()->id(),
            'text' => $this->newNote
        ]);
    
        $this->newNote = '';
        $this->activeOrder->load('Notes.user');
    }

    public function closeErrorModal()
    {
        $this->showErrorModal = false;
    }
}
