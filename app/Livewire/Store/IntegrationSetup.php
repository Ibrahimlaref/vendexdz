<?php

namespace App\Livewire\Store;

use Livewire\Component;
use App\Models\StoreIntegration;
use App\Services\StoreIntegrations\PlatformAdapterFactory;

class IntegrationSetup extends Component
{
    public $platform;
    public $platformConfig;
    public $integration;
    public $storeId;
    
    // Form fields
    public $name = '';
    public $external_url = '';

    public $showSecret = false;

    public function mount($platform)
    {
        $this->platform = $platform;
        $this->storeId = auth()->user()->userStore->store_id;

        if (!isset(StoreIntegration::PLATFORMS[$platform])) {
            abort(404, 'Plateforme non supportée');
        }

        $this->platformConfig = StoreIntegration::PLATFORMS[$platform];
        $this->name = 'Mon Magasin ' . $this->platformConfig['name'];

        $this->integration = StoreIntegration::where('store_id', $this->storeId)
            ->where('platform', $platform)
            ->whereIn('status', [StoreIntegration::STATUS_PENDING, StoreIntegration::STATUS_CONNECTED])
            ->first();

        if ($this->integration) {
            $this->name = $this->integration->name;
            $this->external_url = $this->integration->external_url;
        }
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        if ($this->platformConfig['url_required']) {
            $rules['external_url'] = 'required|url|max:255';
        } else {
            $rules['external_url'] = 'nullable|url|max:255';
        }

        $this->validate($rules);

        if (!$this->integration) {
            $this->integration = StoreIntegration::create([
                'store_id' => $this->storeId,
                'platform' => $this->platform,
                'name' => $this->name,
                'external_url' => $this->external_url,
                'created_by' => auth()->id(),
            ]);
            $this->dispatch('notify', ['message' => 'Intégration créée avec succès.', 'type' => 'success']);
        } else {
            $this->integration->update([
                'name' => $this->name,
                'external_url' => $this->external_url,
            ]);
            $this->dispatch('notify', ['message' => 'Mise à jour réussie.', 'type' => 'success']);
        }
    }

    public function toggleSecret()
    {
        $this->showSecret = !$this->showSecret;
    }

    public function regenerateSecret()
    {
        if ($this->integration) {
            $this->integration->regenerateSecret();
            $this->showSecret = true;
            $this->dispatch('notify', ['message' => 'Secret regénéré. Veuillez mettre à jour votre boutique.', 'type' => 'success']);
        }
    }

    public function render()
    {
        $adapter = PlatformAdapterFactory::make($this->platform);
        
        $instructions = [];
        if ($this->integration) {
            $instructions = $adapter->getSetupInstructions($this->integration->webhook_url);
        }

        return view('livewire.store.integration-setup', [
            'instructions' => $instructions
        ]);
    }
}
