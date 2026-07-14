<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 font-sans">
    
    <!-- HEADER SECTION -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Magasins</h1>
            <p class="text-slate-500 text-sm mt-1 font-medium">Gérez et connectez vos boutiques en ligne</p>
        </div>
        
        <div class="relative w-full md:w-96">
            <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" wire:model.live="search" class="block w-full pl-10 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 shadow-sm transition" placeholder="Rechercher une plateforme..." autocomplete="off">
        </div>
    </div>

    <!-- MAIN GRID SECTION (Now correctly on top) -->
    <div class="mb-12">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
            @forelse($availablePlatforms as $key => $platform)
                <a href="{{ route('store.integrations.setup', $key) }}" class="group bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-md transition-all duration-200 flex flex-col items-center justify-center text-center h-full">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4 transition-transform group-hover:scale-105" style="background-color: {{ $platform['color'] }}15;">
                        @if(isset($platform['logo']))
                            <img src="{{ $platform['logo'] }}" alt="{{ $platform['name'] }} logo" class="w-8 h-8 opacity-90 mix-blend-multiply">
                        @else
                            <i class="{{ $platform['icon'] }} text-3xl" style="color: {{ $platform['color'] }}"></i>
                        @endif
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">{{ $platform['name'] }}</h3>
                    <div class="mt-auto">
                        <span class="text-sm font-medium text-indigo-600 group-hover:text-indigo-700">Connecter &rarr;</span>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-12 text-center text-slate-500 bg-white rounded-2xl border border-slate-200">
                    <i class="ri-search-line text-4xl mb-3 block text-slate-300"></i>
                    Aucune plateforme trouvée pour "{{ $search }}"
                </div>
            @endforelse

            <!-- NEW: DEMANDER UNE NOUVELLE INTEGRATION CARD -->
            @if(empty($search))
            <button onclick="alert('Feature coming soon!')" class="group bg-slate-50 rounded-2xl p-6 border-2 border-dashed border-slate-300 hover:border-indigo-300 hover:bg-indigo-50 transition-all duration-200 flex flex-col items-center justify-center text-center h-full">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mb-4 bg-white border border-slate-200 text-slate-400 group-hover:text-indigo-500 transition-colors">
                    <i class="ri-add-line text-3xl"></i>
                </div>
                <h3 class="font-bold text-slate-700 group-hover:text-indigo-700 mb-1">Demander une nouvelle intégration</h3>
                <span class="text-xs font-medium text-slate-500 group-hover:text-indigo-600">Get your platform added &rarr;</span>
            </button>
            @endif
        </div>
    </div>

    <!-- CONNECTED STORES SECTION (Now below the grid) -->
    @if(count($connectedIntegrations) > 0)
        <div>
            <h2 class="text-xl font-bold text-slate-900 mb-6">Vos magasins connectés</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($connectedIntegrations as $platformKey => $integration)
                    @php $config = \App\Models\StoreIntegration::PLATFORMS[$platformKey]; @endphp
                    <div class="bg-white rounded-2xl p-6 border {{ $integration['status'] == \App\Models\StoreIntegration::STATUS_ERROR ? 'border-red-300' : 'border-slate-200' }} shadow-sm relative overflow-hidden group hover:shadow-md transition">
                        
                        <div class="absolute top-0 right-0 p-4">
                            @if($integration['status'] === \App\Models\StoreIntegration::STATUS_CONNECTED)
                                <span class="flex h-3 w-3" title="Connecté">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                </span>
                            @elseif($integration['status'] === \App\Models\StoreIntegration::STATUS_PENDING)
                                <span class="flex h-3 w-3" title="En attente">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-yellow-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
                                </span>
                            @else
                                <span class="flex h-3 w-3" title="Erreur">
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $config['color'] }}15;">
                                @if(isset($config['logo']))
                                    <img src="{{ $config['logo'] }}" alt="{{ $config['name'] }} logo" class="w-6 h-6 opacity-90 mix-blend-multiply">
                                @else
                                    <i class="{{ $config['icon'] }} text-2xl" style="color: {{ $config['color'] }}"></i>
                                @endif
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900">{{ $integration['name'] }}</h3>
                                <p class="text-xs text-slate-500">{{ $config['name'] }}</p>
                            </div>
                        </div>

                        @if($integration['external_url'])
                        <div class="mb-4">
                            <a href="{{ $integration['external_url'] }}" target="_blank" class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                                <i class="ri-external-link-line"></i> {{ parse_url($integration['external_url'], PHP_URL_HOST) ?? $integration['external_url'] }}
                            </a>
                        </div>
                        @endif

                        <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-100">
                            <a href="{{ route('store.integrations.setup', $platformKey) }}" class="px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1">
                                <i class="ri-settings-4-line"></i> Gérer
                            </a>
                            <button wire:click="disconnect({{ $integration['id'] }})" wire:confirm="Êtes-vous sûr de vouloir déconnecter cette boutique ? Les nouvelles commandes ne seront plus importées." class="px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold rounded-xl transition">
                                <i class="ri-plug-disconnect-line"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
