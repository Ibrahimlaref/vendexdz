<?php

namespace App\Livewire\V2\Order;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use App\Models\firstStepStatu;
use App\Models\User;
use App\Models\Role;
use App\Services\ShippingSwitcher;
use Illuminate\Validation\Rule;     
use App\Livewire\V2\Order\Traits\OrderTrait;
use App\Models\OrderWaiting;
use App\Models\OrderInconfirmation;
use Livewire\WithPagination;

class Inconfermation extends Component
{
    use OrderTrait, WithPagination;
    protected $pageName = 'inPage';
    public $showTimerModal = false;
    public $tempStatusId;
    public $tempOrderId;
    public $scheduleTime;
    public $selectedStatu = null;
    public $storefilter;
    public $agentfilter;
    public $productfilter;
    public $statufilter;
    public $start_date=null;
    public $end_date=null;
    public $search;

    public $activeTab = 'chat'; 
    protected $listeners = ['orderSaved' => 'syncOrder'];
    public string $context;
    
    public function mount($context){
        $this->context = $context;
        $this->initializeStore();
    }
 
    public function render()
    { 
        $firstStepStatus = firstStepStatu::all();
        $products = Product::when($this->storefilter, function ($query) {
            $query->where('store_id', $this->storefilter['id']);
        })
        ->when($this->storefilter == null, function ($query) {
            $query->whereIn('store_id', $this->stores->pluck('id'));
        })
        ->latest()->get();
        $orders = Order::query()->whereIn('sid',$this->stores->pluck('id'))
            ->whereHas('Inconfirmation', function ($query) {
            if ($this->statufilter) {
                $query->where('fsid', $this->statufilter['fsid']);
            } else {
                $query->whereNotIn('fsid', [3, 4]);
            }
        })
        ->when($this->storefilter, function ($query) {
            $query->where('sid', $this->storefilter['id']);
        })
        ->when($this->agentfilter, function ($query) {
            $query->where('aid', $this->agentfilter['id']);
        })
        ->when($this->search, function ($query) {
            $query->where(function($q) {
                $q->where('id', 'like', '%' . $this->search . '%')
                ->orWhereHas('client', function ($vq) {
                    $vq->where('full_name', 'like', '%' . $this->search . '%')
                        ->orwhere('phone_number_1', 'like', '%' . $this->search . '%');
                });
            }); 
        })
        ->when($this->productfilter, function ($query) {
            $query->whereHas('items', function ($q) {
                $q->where('product_id', $this->productfilter['id']);
            });
        })
        ->when(auth()->user()->hasRole(Role::AGENT), function ($query) {
            $query->where('aid', auth()->id())
                ->orWhereNull('aid');
        })
        ->when($this->start_date, fn($q) => $q->whereDate('created_at', '>=', $this->start_date))
        ->when($this->end_date, fn($q) => $q->whereDate('created_at', '<=', $this->end_date))
        ->with([
            'client', 
            'details', 
            'Inconfirmation.firstStepStatu', 
            'items',
            'store'
        ])
        ->latest()
        ->paginate(10, ['*'], 'inPage');
        $orders->withQueryString();
        return view('livewire.v2.order.inconfermation', ['orders' => $orders, 'firstStepStatus'=>$firstStepStatus ,'products'=>$products]);
    }


    public function syncOrder(){
        $this->loadOrderData($this->activeOrder->oid);
        $this->expandedOrderId =null;
    }

    public function sendAllToShipping()
    {
        $ordersToSend = Order::whereHas('Inconfirmation.firstStepStatu', function ($query) {
            $query->where('fsid', 2); 
        })
        ->when($this->storefilter, function ($query) {
            $query->where('sid', $this->storefilter['id']);
        })
        ->when($this->agentfilter, function ($query) {
            $query->where('aid', $this->agentfilter['id']);
        })
        ->when($this->storefilter == null, function ($query) {
            $query->whereIn('sid', $this->stores->pluck('id'));
        })
        ->with(['client', 'details', 'items.variant.product'])
        ->get();

        if ($ordersToSend->isEmpty()) {
            session()->flash('error', 'No confirmed orders found to dispatch.');
            return;
        }
        $storesOrders = $ordersToSend->groupBy('sid');
        $notifications = [];
        foreach ($storesOrders as $storeOrders){
            $groupedOrders = $storeOrders->groupBy('app_id');
        
            $totalSuccess = 0;
            $totalErrors = 0;

            try {
                $switcher = new ShippingSwitcher();

                foreach ($groupedOrders as $appId => $ordersInGroup) {
                    $standardizedOrders = $ordersInGroup->map(function($order) {
                        $this->activeOrder = $order;
                        return $this->getLocalStandardizedData();
                    })->toArray();

                    $notifications = array_merge(
                        $notifications, 
                        $switcher->createParcels($standardizedOrders, $this->activeOrder)
                    );
                    foreach ($notifications as $notification) {
                        $notification['status'] == 'success' ? $totalSuccess++ : $totalErrors++;
                    }
                }
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }
        }
        $this->dispatch('notify', type: 'success', message: "Process Finished: $totalSuccess orders sent. $totalErrors failed.");
    }

    private function getLocalStandardizedData()
    {
        return (object) [
            'ref'           => 'VN-'.$this->activeOrder->id,
            'name'          => $this->activeOrder->client->full_name ?? 'Unknown Client',
            'phone'         => $this->activeOrder->client->phone_number_1 ?? '',
            'phone2'        => $this->activeOrder->client->phone_number_2 ?? '',
            'address'       => $this->activeOrder->client->address ?? '',
            'city'          => $this->activeOrder->client->town ?? '',
            'wilaya'        => $this->activeOrder->client->wilaya ?? '',
            'total_price'   => $this->activeOrder->details->total ?? 0,
            'delivery_type' => $this->activeOrder->details->stopdesk ?? 0,
            'note'          => $this->activeOrder->details->commenter ?? '',
            'product_name'  => collect($this->activeOrder->items)->map(function($item) {
                $name = $item->product->nickname ?? $item->product->name;
                $variant = $item->variant ? $item->variant->label : '';
                $qty = " x" . ($item['quantity'] ?? 1);
                
                return $name . $variant . $qty;
            })->implode(' + '),
            'quantity'      => collect($this->activeOrder->items)->sum('quantity'),
            "commenter"     => $this->activeOrder->details->commenter ?? null,
        ];
    }
}