<?php

namespace App\Livewire\Manager;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Store;
use App\Services\Payroll\CommissionCalculationService;
use App\Services\Payroll\CommissionExportService;

/**
 * Manager payroll dashboard — shows commission summary
 * across all stores the manager has access to.
 * Redirects to /manager/commission for the detailed stores→drilldown flow.
 */
class Payroll extends Component
{
    public $dateFrom;
    public $dateTo;
    public $selectedStoreId = null;
    public $stores = [];
    public $confirmatrices = [];
    public $gerants = [];
    public $confirmatriceTotal = 0;
    public $gerantTotal = 0;
    public $expandedAgentId = null;

    public function mount()
    {
        $user = auth()->user();

        if ($user->hasRole(2)) {
            $this->stores = Store::all()->toArray();
        } else {
            $this->stores = $user->stores()->get()->toArray();
        }

        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        if (count($this->stores) === 1) {
            $this->selectedStoreId = $this->stores[0]['id'];
        }

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

    public function updatedSelectedStoreId()
    {
        $this->loadData();
    }

    public function loadData()
    {
        if (!$this->selectedStoreId) {
            $this->confirmatrices = [];
            $this->gerants = [];
            $this->confirmatriceTotal = 0;
            $this->gerantTotal = 0;
            return;
        }

        $service = new CommissionCalculationService();
        $from = Carbon::parse($this->dateFrom);
        $to = Carbon::parse($this->dateTo);

        $this->confirmatrices = $service->getConfirmatriceCommission((int) $this->selectedStoreId, $from, $to)->toArray();
        $this->gerants = $service->getGerantCommission((int) $this->selectedStoreId, $from, $to)->toArray();

        $this->confirmatriceTotal = array_sum(array_column($this->confirmatrices, 'total_commission'));
        $this->gerantTotal = array_sum(array_column($this->gerants, 'total_commission'));
    }

    public function toggleAgent($agentId)
    {
        $this->expandedAgentId = $this->expandedAgentId === $agentId ? null : $agentId;
    }

    public function exportExcel()
    {
        if (!$this->selectedStoreId) return;

        $store = Store::find($this->selectedStoreId);
        $exporter = new CommissionExportService();
        $path = $exporter->export(
            (int) $this->selectedStoreId,
            $store->name,
            Carbon::parse($this->dateFrom),
            Carbon::parse($this->dateTo)
        );

        return response()->download($path);
    }

    public function render()
    {
        return view('livewire.manager.payroll')
            ->layout('layouts.app');
    }
}
