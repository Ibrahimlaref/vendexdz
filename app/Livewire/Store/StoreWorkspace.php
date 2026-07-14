<?php

namespace App\Livewire\Store;

use Livewire\Component;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\OrderLog;
use App\Models\firstStepStatu;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StoreWorkspace extends Component
{
    public $selectedDate;
    public $selectedProduct = null;
    public $products;
    public $start_date;
    public $end_date;
    public $orderStats = [];
    public $performanceData = [];
    public $deliveryData = [];
    public $dailyProgress;
    public $selectedProductDisplayName = 'All Products';
    public $statusOptions;
    public $topWilayas;
    public $pipeLineFlow;
    public $topProducts;
    protected $listeners = ['refreshComponent' => '$refresh'];

    public $range = 'day';
    public $chartData = [];
    
    public function mount()
    {
        $store_id = auth()->user()->userStore->store_id;
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->products = Product::where('store_id', $store_id)->get();
        $this->statusOptions = firstStepStatu::all();
        $this->loadChart();
        $this->topWilayas = DB::table('orders as o')
            ->join('clients as c', 'c.id', '=', 'o.cid')
            ->join('willayas as w', 'w.wid', '=', 'c.wilaya')

            ->where('o.sid', $store_id)

            ->selectRaw('
                w.wid as wilaya_id,
                w.name as wilaya_name,
                COUNT(DISTINCT o.oid) as total_orders,

                COUNT(DISTINCT CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM order_logs ol
                        WHERE ol.oid = o.oid
                        AND ol.step = 2
                        AND ol.statu_new = 12
                    )
                    THEN o.oid
                END) as delivered_orders,

                ROUND(
                    COUNT(DISTINCT CASE 
                        WHEN EXISTS (
                            SELECT 1 FROM order_logs ol
                            WHERE ol.oid = o.oid
                            AND ol.step = 2
                            AND ol.statu_new = 12
                        )
                        THEN o.oid
                    END) * 100.0
                    / COUNT(DISTINCT o.oid)
                ) as delivered_percentage
            ')
            
            ->groupBy('w.wid', 'w.name')
            ->orderByDesc('delivered_percentage')
            ->limit(7)
            ->get();

        $pipeLineStats = DB::table('orders as o')
            ->leftJoin('order_logs as c', 'c.oid', '=', 'o.oid')
            ->where('o.sid', $store_id)
            ->selectRaw('
                COUNT(DISTINCT o.oid) as total_orders,

                -- confirmed (step 1)
                COUNT(DISTINCT CASE 
                    WHEN c.step = 1 AND c.statu_new IN (2,3)
                    THEN o.oid 
                END) as confirmed_orders,

                -- shipped (entered step 2)
                COUNT(DISTINCT CASE 
                    WHEN c.step = 2
                    THEN o.oid 
                END) as shipped_orders,

                -- delivered (step 2 + status 12)
                COUNT(DISTINCT CASE 
                    WHEN c.step = 2 AND c.statu_new = 12
                    THEN o.oid 
                END) as delivered_orders
            ')
            ->first();

        $pipeLineTotal = $pipeLineStats->total_orders ?: 1;
        $this->pipeLineFlow = [
            'total' => $pipeLineStats->total_orders,

            'confirmed' => [
                'count' => $pipeLineStats->confirmed_orders,
                'percent' => round($pipeLineStats->confirmed_orders / $pipeLineTotal * 100, 2),
            ],

            'shipped' => [
                'count' => $pipeLineStats->shipped_orders,
                'percent' => round($pipeLineStats->shipped_orders / $pipeLineTotal * 100, 2),
            ],

            'delivered' => [
                'count' => $pipeLineStats->delivered_orders,
                'percent' => round($pipeLineStats->delivered_orders / $pipeLineTotal * 100, 2),
            ],
        ];
        $this->topProducts = DB::table('order_items as oi')
            ->join('orders as o', 'o.oid', '=', 'oi.oid')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('order_logs as c', 'c.oid', '=', 'o.oid')

            ->where('o.sid', $store_id)
            ->where('c.step', 2)
            ->where('c.statu_new', 12)

            ->selectRaw('
                p.id,
                p.name,
                SUM(oi.quantity) as total_qty_sold,
                COUNT(DISTINCT o.oid) as total_orders
            ')

            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total_qty_sold')
            ->limit(4)
            ->get();
        $this->loadData();
    }

    public function setDateToNull(){
        $this->start_date = null;
        $this->end_date = null;
    }
    
    public function updatedSelectedProduct()
    {
        if ($this->selectedProduct) {
            $product = $this->products->find($this->selectedProduct);
            $this->selectedProductDisplayName = $product ? $product->name : 'All Products';
        } else {
            $this->selectedProductDisplayName = 'All Products';
        }
        
        $this->loadData();
    }

    public function updatedSelectedDate()
    {
        // When a single date is selected, clear the range
        $this->start_date = null;
        $this->end_date = null;
        $this->loadData();
    }

    public function updatedStartDate()
    {
        // When a start date is set, clear the single date selector
        $this->selectedDate = null;
        $this->loadData();
    }

    public function updatedEndDate()
    {
        // When an end date is set, clear the single date selector
        $this->selectedDate = null;
        $this->loadData();
    }
    
    public function loadData()
    {
        $store_id = auth()->user()->userStore->store_id;
        $query = Order::where('sid', $store_id);

        // --- THIS IS THE KEY FIX ---
        // Determine if we are using a range or a single date
        if ($this->start_date && $this->end_date) {
            // Use the date range
            $startDate = Carbon::parse($this->start_date)->startOfDay();
            $endDate = Carbon::parse($this->end_date)->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($this->selectedDate) {
            // Use the single selected date
            $date = Carbon::parse($this->selectedDate);
            $query->whereDate('created_at', $date);
        } else {
            // If no date is set, return empty data
            $this->resetStats();
            return;
        }
        
        if ($this->selectedProduct) {
            $query->whereHas('items', function($q) {
                $q->where('product_id', $this->selectedProduct);
            });
        }
        
        $orders = $query->with('latestLog')->get();
        
        // Initialize stats counters - keeping the original structure
        $stats = [
            'total' => $orders->count(),
            'confirmed' => 0,
            'cancelled' => 0,
            'in_process' => 0,
            'delivered' => 0,
            'returned' => 0,
        ];
        
        // Initialize performance data - keeping the original structure
        $performance = [
            'confirmed' => 0,
            'cancelled' => 0,
            'no_answer' => 0,
            'pending' => 0,
            'reported' => 0,
            'double' => 0,
            'no_deliv' => 0,
            'false_rate' => 0,
            'pre-confirmed'=> 0,
        ];

        $deliveryPerformance = [
            'delivered' => 0, 'suspended' => 0, 'return'=> 0, 'in_delivery' => 0 , 'in_route'=>0
        ];

        $orderOids = $orders->pluck('oid');
        $orderLogs = OrderLog::whereIn('oid', $orderOids)
            ->when($this->start_date && $this->end_date, function($q) {
                $startDate = Carbon::parse($this->start_date)->startOfDay();
                $endDate = Carbon::parse($this->end_date)->endOfDay();
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when($this->selectedDate, function($q) {
                $date = Carbon::parse($this->selectedDate);
                $q->whereDate('created_at', $date);
            })
            ->get();
        foreach ($orderLogs as $orderLog) {
            if ($orderLog->step == 1) {
                $statusId = $orderLog->status_new->fsid;
                switch ($statusId) {
                    case 1: // pending
                         $performance['pending']++;
                        break;
                    case 2: // confirmed
                        $stats['confirmed']++;
                        $performance['confirmed']++;
                        break;
                    case 3: // pre-confirmed
                        $stats['in_process']++;
                        $performance['pre-confirmed']++;
                        break;
                    case 4: // reported
                        $performance['reported']++;
                        $stats['in_process']++;
                        break;
                    case 5: // cancelled
                        $stats['cancelled']++;
                        $performance['cancelled']++;
                        break;
                    case 6: // NA/1
                    case 7: // NA/2
                    case 8: // NA/3
                    case 9: // NA/4
                    case 10: // NA/5
                        $performance['no_answer']++;
                        $stats['in_process']++;
                        break;
                    case 11: // closed
                        $performance['no_answer']++;
                        $stats['in_process']++;
                        break;
                    case 12: // no delivery
                        $performance['no_deliv']++;
                        break;
                    case 13: // double
                        $performance['double']++;
                        break;
                    case 14: // false order
                        $performance['false_rate']++;
                        break;
                    case 15: // false number
                        $performance['false_rate']++;

                        break;
                    case 16: // test
                     
                        break;
                }
            }
            if ($orderLog->step == 2) {
                $statusId = $orderLog->status_new->ssid;
                switch ($statusId) {
                    case 12: // delivered
                         $deliveryPerformance['delivered']++;
                         $stats['delivered']++;
                        break;
                    case 7: // in_delivery
                         $deliveryPerformance['in_delivery']++;
                        break;
                    case 8: // suspended
                         $deliveryPerformance['suspended']++;
                        break;
                    case 15: // in_route
                         $deliveryPerformance['in_route']++;
                        break;
                    case 17: // return
                         $deliveryPerformance['return']++;
                         $stats['returned']++;
                        break;
                }
            }
        }
        
        $this->orderStats = $stats;
        $this->performanceData = $performance;
        $this->deliveryData = $deliveryPerformance;

        $this->dispatch('performance-updated', performanceData: $this->performanceData);
        $this->dispatch('delivery-updated', deliveryData: $this->deliveryData);

        $this->dailyProgress = $this->orderStats['total'];
    }
    private function resetStats()
    {
        $this->orderStats = [
            'total' => 0, 'confirmed' => 0, 'cancelled' => 0, 
            'in_process' => 0, 'delivered' => 0, 'returned' => 0
        ];
        $this->performanceData = [
            'confirmed' => 0, 'cancelled' => 0, 'no_answer' => 0, 'pending' =>0,
            'reported' => 0, 'double' => 0,'no_deliv'=>0, 'false_rate' => 0 ,'pre-confirmed'=>0
        ];
        $this->deliveryData = [
            'delivered' => 0, 'suspended' => 0,'return'=>0, 'in_delivery' => 0 ,'in_route'=>0
        ];
        $this->dailyProgress = 0;
    }

    private function getPeriods($range)
    {
        $end = now();
        $start = match($range) {
            'day' => $end->copy()->subDays(30),     // 7 days total
            'week' => $end->copy()->subWeeks(3),   // 4 weeks total
            'month' => $end->copy()->subMonths(3), // 4 months total
        };

        $period = match($range) {
            'day' => CarbonPeriod::create($start, '1 day', $end),
            'week' => CarbonPeriod::create($start->startOfWeek(), '1 week', $end->endOfWeek()),
            'month' => CarbonPeriod::create($start->startOfMonth(), '1 month', $end->endOfMonth()),
        };

        return collect($period)->map(fn($d) => match($range) {
            'day' => $d->format('Y-m-d'),
            'week' => $d->format('o-W'), // ISO week
            'month' => $d->format('Y-m'),
        });
    }

    public function setRange($range)
    {
        $this->range = $range;
        $this->loadChart();
    }

    private function loadChart()
    {
        $store_id = auth()->user()->userStore->store_id;

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $format = match ($this->range) {
                'week'  => '%Y-%W',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };
            $selectConfirmed = "strftime('{$format}', c.created_at) as period, COUNT(*) as total";
            $selectDelivered = "strftime('{$format}', d.created_at) as period, COUNT(*) as total";
        } else {
            $format = match ($this->range) {
                'week'  => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };
            $selectConfirmed = "DATE_FORMAT(c.created_at, '{$format}') as period, COUNT(*) as total";
            $selectDelivered = "DATE_FORMAT(d.created_at, '{$format}') as period, COUNT(*) as total";
        }

        $confirmed = DB::table('order_logs as c')
            ->join('orders as o','o.oid','=','c.oid')
            ->where('o.sid', $store_id)
            ->where('c.step', 1)
            ->whereIn('c.statu_new',[2,3])
            ->selectRaw($selectConfirmed)
            ->groupBy('period')
            ->pluck('total','period');
            
        $delivered = DB::table('order_logs as d')
            ->join('orders as o','o.oid','=','d.oid')
            ->where('o.sid', $store_id)
            ->where('d.step', 2)
            ->where('d.statu_new', 12)
            ->selectRaw($selectDelivered)
            ->groupBy('period')
            ->pluck('total','period');

        $labels = $this->getPeriods($this->range);

        $confirmedData = $labels->map(fn($label) => $confirmed[$label] ?? 0);
        $deliveredData = $labels->map(fn($label) => $delivered[$label] ?? 0);

        $this->chartData = [
            'labels' => $labels->toArray(),
            'confirmed' => $confirmedData->toArray(),
            'delivered' => $deliveredData->toArray(),
        ];
        $this->dispatch('confirmation-data-updated', chartData: $this->chartData);
    }

    
    public function render()
    {
        return view('livewire.store.store-workspace');
    }
}