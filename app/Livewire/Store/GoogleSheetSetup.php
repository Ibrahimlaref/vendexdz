<?php

namespace App\Livewire\Store;

use Livewire\Component;
use App\Models\GoogleSheetConnection;
use App\Models\ProductVariant;
use App\Services\GoogleSheets\GoogleSheetsService;
use App\Services\GoogleSheets\ColumnMapperService;
use Illuminate\Support\Facades\Log;

class GoogleSheetSetup extends Component
{
    public $sheetId = '';
    public $connection = null;
    public $testResult = null;
    public $detectedHeaders = [];
    public $columnMapping = []; // [header_text => system_field]
    public $autoMappingResult = [];
    public $showMappingStep = false;
    public $showSuccessMessage = false;
    public $errorMessage = '';
    public $syncInterval = 10;
    public $storeSkus = [];

    public function mount()
    {
        $storeId = auth()->user()->userStore->store_id;

        // Load existing connection
        $this->connection = GoogleSheetConnection::where('sid', $storeId)->first();

        if ($this->connection) {
            $this->sheetId = $this->connection->sheet_id;
            $this->columnMapping = $this->connection->column_mapping ?? [];
            $this->syncInterval = $this->connection->sync_interval_minutes;
        }

        // Load store SKUs for reference
        $this->storeSkus = ProductVariant::whereHas('product', function ($q) use ($storeId) {
            $q->where('store_id', $storeId);
        })->with('product')->get()->map(fn($v) => [
            'sku' => $v->sku,
            'product' => $v->product->name,
            'variant' => $v->label,
        ])->toArray();
    }

    public function testConnection()
    {
        $this->resetErrorState();

        if (empty($this->sheetId)) {
            $this->errorMessage = trans('Please enter a Google Sheet ID');
            return;
        }

        try {
            $service = new GoogleSheetsService();
            $this->testResult = $service->testConnection($this->sheetId);

            if ($this->testResult['success']) {
                $this->detectedHeaders = $this->testResult['headers'];

                // Auto-map headers
                $mapper = new ColumnMapperService();
                $this->autoMappingResult = $mapper->autoMap($this->detectedHeaders);

                // Pre-fill column mapping from auto-detection
                $this->columnMapping = [];
                foreach ($this->autoMappingResult as $header => $match) {
                    $this->columnMapping[$header] = $match['field'];
                }

                $this->showMappingStep = true;
            } else {
                $this->errorMessage = $this->testResult['error'] ?? trans('Connection failed');
            }
        } catch (\Exception $e) {
            $this->errorMessage = trans('Error: ') . $e->getMessage();
            Log::error('Google Sheet test connection error: ' . $e->getMessage());
        }
    }

    public function saveMapping()
    {
        $this->resetErrorState();

        // Validate required fields are mapped
        $mapper = new ColumnMapperService();
        $missing = $mapper->getMissingRequiredFields($this->columnMapping);

        if (!empty($missing)) {
            $this->errorMessage = trans('Please map the following required fields: ') . implode(', ', $missing);
            return;
        }

        $storeId = auth()->user()->userStore->store_id;

        $this->connection = GoogleSheetConnection::updateOrCreate(
            ['sid' => $storeId],
            [
                'sheet_id' => $this->sheetId,
                'column_mapping' => $this->columnMapping,
                'mapping_confirmed' => true,
                'is_active' => true,
                'sync_interval_minutes' => max(5, min(60, (int) $this->syncInterval)),
            ]
        );

        $this->showSuccessMessage = true;
        $this->dispatch('notify', type: 'success', message: trans('Google Sheet connected and column mapping saved!'));
    }

    public function toggleActive()
    {
        if ($this->connection) {
            $this->connection->update(['is_active' => !$this->connection->is_active]);
            $this->connection->refresh();
        }
    }

    public function disconnect()
    {
        if ($this->connection) {
            $this->connection->delete();
            $this->connection = null;
            $this->reset(['sheetId', 'columnMapping', 'testResult', 'detectedHeaders', 'showMappingStep']);
        }
    }

    public function getSystemFieldsProperty()
    {
        return (new ColumnMapperService())->getSystemFields();
    }

    private function resetErrorState()
    {
        $this->errorMessage = '';
        $this->showSuccessMessage = false;
    }

    public function render()
    {
        return view('livewire.store.google-sheet-setup');
    }
}
