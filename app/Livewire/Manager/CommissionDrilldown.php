<?php

namespace App\Livewire\Manager;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Store;
use App\Services\Payroll\CommissionCalculationService;
use App\Services\Payroll\CommissionExportService;

/**
 * Per-store commission drilldown.
 * Date range picker drives everything: confirmatrice list + gérant list.
 * Changing the range recomputes all values reactively.
 */
class CommissionDrilldown extends Component
{
    public $storeId;
    public $storeName;
    public $dateFrom;
    public $dateTo;
    public $confirmatrices = [];
    public $gerants = [];
    public $confirmatriceTotal = 0;
    public $gerantTotal = 0;
    public $expandedAgentId = null;

    public function mount(int $storeId)
    {
        $store = Store::findOrFail($storeId);
        $this->storeId = $store->id;
        $this->storeName = $store->name;

        // Default: current month
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $this->loadData();
    }

    public function setTodayRange()
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadData();
    }

    public function setThisMonthRange()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
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

    public function loadData()
    {
        $service = new CommissionCalculationService();
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        $this->confirmatrices = $service->getConfirmatriceCommission($this->storeId, $from, $to)->toArray();
        $this->gerants = $service->getGerantCommission($this->storeId, $from, $to)->toArray();

        $this->confirmatriceTotal = array_sum(array_column($this->confirmatrices, 'total_commission'));
        $this->gerantTotal = array_sum(array_column($this->gerants, 'total_commission'));
    }

    public function toggleAgent($agentId)
    {
        $this->expandedAgentId = $this->expandedAgentId === $agentId ? null : $agentId;
    }

    public function exportExcel()
    {
        $exporter = new CommissionExportService();
        $path = $exporter->export(
            $this->storeId,
            $this->storeName,
            Carbon::parse($this->dateFrom),
            Carbon::parse($this->dateTo)
        );

        return response()->download($path);
    }

    public function render()
    {
        return view('livewire.manager.commission-drilldown')
            ->layout('layouts.app');
    }
}
