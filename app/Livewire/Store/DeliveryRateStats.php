<?php

namespace App\Livewire\Store;

use Livewire\Component;
use App\Models\Product;
use App\Models\Category;
use App\Models\Store;
use App\Services\Statistics\DeliveryRateService;

class DeliveryRateStats extends Component
{
    public $dateFrom;
    public $dateTo;
    public $selectedStoreId = null; // null = all stores
    public $selectedProductId = null;
    public $selectedCategoryId = null;
    public $stores = [];
    public $products = [];
    public $categories = [];
    public $results = null;

    public function mount()
    {
        $user = auth()->user();
        
        if ($user->hasRole(2)) {
            $this->stores = Store::all()->toArray();
        } else {
            $this->stores = $user->stores()->get()->toArray();
        }

        $storeIds = collect($this->stores)->pluck('id')->toArray();

        $this->products = Product::whereIn('store_id', $storeIds)->get()->toArray();
        $this->categories = Category::all()->toArray();

        // Default: last 30 days
        $this->dateFrom = now()->subDays(30)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $this->loadData();
    }

    public function updatedDateFrom()
    {
        $this->loadData();
    }

    public function updatedDateTo()
    {
        $this->loadData();
    }

    public function updatedSelectedStoreId()
    {
        // Reload products for selected store
        if ($this->selectedStoreId) {
            $this->products = Product::where('store_id', $this->selectedStoreId)->get()->toArray();
        } else {
            $storeIds = collect($this->stores)->pluck('id')->toArray();
            $this->products = Product::whereIn('store_id', $storeIds)->get()->toArray();
        }
        $this->selectedProductId = null;
        $this->loadData();
    }

    public function updatedSelectedProductId()
    {
        $this->loadData();
    }

    public function updatedSelectedCategoryId()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $storeIds = $this->selectedStoreId
            ? [(int) $this->selectedStoreId]
            : collect($this->stores)->pluck('id')->toArray();

        $service = new DeliveryRateService();
        $this->results = $service->getRate([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'store_id' => $storeIds,
            'product_id' => $this->selectedProductId ? (int) $this->selectedProductId : null,
            'category_id' => $this->selectedCategoryId ? (int) $this->selectedCategoryId : null,
        ]);
    }

    public function render()
    {
        return view('livewire.store.delivery-rate-stats');
    }
}
