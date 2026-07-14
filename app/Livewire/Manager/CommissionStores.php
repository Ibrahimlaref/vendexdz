<?php

namespace App\Livewire\Manager;

use Livewire\Component;
use App\Models\Store;

/**
 * Landing page: lists stores the current manager/gérant manages.
 * Clicking a store navigates to the commission drilldown.
 */
class CommissionStores extends Component
{
    public $stores = [];

    public function mount()
    {
        $user = auth()->user();

        // Admin (role=2): sees all stores
        // Manager (role=3): sees their assigned stores
        if ($user->hasRole(2)) {
            $this->stores = Store::all()->toArray();
        } else {
            $this->stores = Store::whereHas('managers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->get()->toArray();

            // Fallback: if user has stores via UserStore
            if (empty($this->stores)) {
                $this->stores = $user->stores()->get()->toArray();
            }
        }
    }

    public function render()
    {
        return view('livewire.manager.commission-stores');
    }
}
