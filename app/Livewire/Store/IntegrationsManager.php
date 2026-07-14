<?php

namespace App\Livewire\Store;

use Livewire\Component;
use App\Models\StoreIntegration;
use App\Services\StoreIntegrations\PlatformAdapterFactory;
use Illuminate\Support\Facades\Auth;

class IntegrationsManager extends Component
{
    public $search = '';
    public $storeId;
    public $platforms = [];
    public $connectedIntegrations = [];

    protected $listeners = ['integrationSaved' => 'loadIntegrations'];

    public function mount()
    {
        $this->storeId = auth()->user()->userStore->store_id;
        $this->platforms = StoreIntegration::PLATFORMS;
        $this->loadIntegrations();
    }

    public function loadIntegrations()
    {
        $this->connectedIntegrations = StoreIntegration::where('store_id', $this->storeId)
            ->whereIn('status', [StoreIntegration::STATUS_PENDING, StoreIntegration::STATUS_CONNECTED])
            ->get()
            ->keyBy('platform')
            ->toArray();
    }

    public function disconnect($integrationId)
    {
        $integration = StoreIntegration::where('store_id', $this->storeId)
            ->where('id', $integrationId)
            ->first();

        if ($integration) {
            $integration->disconnect();
            $this->loadIntegrations();
            $this->dispatch('notify', ['message' => 'Intégration déconnectée avec succès.', 'type' => 'success']);
        }
    }

    public function getFilteredPlatformsProperty()
    {
        if (empty($this->search)) {
            return $this->platforms;
        }

        return array_filter($this->platforms, function ($platform) {
            return stripos($platform['name'], $this->search) !== false;
        });
    }

    public function render()
    {
        return view('livewire.store.integrations-manager', [
            'availablePlatforms' => $this->filteredPlatforms,
        ]);
    }
}
