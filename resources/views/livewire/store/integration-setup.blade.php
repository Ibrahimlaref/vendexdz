<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 font-sans bg-slate-50/50 min-h-screen">
    
    <div class="mb-8">
        <a href="{{ route('store.integrations') }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-bold flex items-center gap-1 mb-4 w-fit">
            <i class="ri-arrow-left-line"></i> Retour
        </a>
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Connectez votre boutique {{ $platformConfig['name'] }}</h1>
        <p class="text-slate-500 text-sm mt-1 font-medium">Configurez l'intégration webhook pour importer automatiquement les commandes.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- Left Column: 70% width -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- CONNECT FORM CARD -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background-color: {{ $platformConfig['color'] }}15;">
                        @if(isset($platformConfig['logo']))
                            <img src="{{ $platformConfig['logo'] }}" alt="logo" class="w-5 h-5 opacity-90 mix-blend-multiply">
                        @else
                            <i class="{{ $platformConfig['icon'] }} text-xl" style="color: {{ $platformConfig['color'] }}"></i>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $platformConfig['name'] }}</h3>
                        <p class="text-xs text-slate-500">Créez votre boutique et configurez l'intégration webhook</p>
                    </div>
                </div>
                <div class="p-6 space-y-6">
                    
                    <div>
                        <label for="name" class="block text-sm font-bold text-slate-700 mb-1">
                            Nom du magasin <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-store-2-line text-slate-400"></i>
                            </div>
                            <input type="text" id="name" wire:model="name" class="block w-full pl-10 rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="My {{ $platformConfig['name'] }} Store">
                        </div>
                        @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="external_url" class="block text-sm font-bold text-slate-700 mb-1">
                            {{ $platformConfig['url_label'] }} <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-link text-slate-400"></i>
                            </div>
                            <input type="text" id="external_url" wire:model="external_url" placeholder="{{ $platformConfig['url_placeholder'] }}" class="block w-full pl-10 rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Veuillez entrer l'URL exacte de votre boutique sans le / à la fin.</p>
                        @error('external_url') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <button wire:click="save" class="inline-flex justify-center items-center gap-2 py-2.5 px-5 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            <i class="ri-lock-line"></i> {{ $integration ? 'Mettre à jour' : 'Créer une boutique' }}
                        </button>
                        <a href="{{ route('store.integrations') }}" class="inline-flex justify-center items-center py-2.5 px-5 border border-slate-300 rounded-xl shadow-sm text-sm font-bold text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                            Annuler
                        </a>
                    </div>

                </div>
            </div>

            <!-- INSTRUCTIONS CARD -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-900">Instructions de configuration manuelle du webhook</h3>
                </div>
                <div class="p-6">
                    <div class="relative">
                        <!-- Continuous vertical line -->
                        <div class="absolute left-4 top-4 bottom-8 w-0.5 bg-slate-200"></div>

                        <!-- Step 1 -->
                        <div class="relative pl-12 pb-8">
                            <div class="absolute left-0 top-0 bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-sm ring-4 ring-white">
                                1
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1 pt-1">Accédez aux paramètres {{ $platformConfig['name'] }}</h4>
                            <p class="text-sm text-slate-600 mb-4">Connectez-vous à votre panneau d'administration {{ $platformConfig['name'] }}, puis allez dans <strong>Paramètres &rarr; Notifications</strong>.</p>
                            
                            <!-- Sub-box -->
                            <div class="bg-slate-50 rounded-xl border border-slate-200 p-4 space-y-4">
                                <div class="flex items-start gap-3">
                                    <div class="text-slate-400 mt-0.5"><i class="ri-settings-4-fill text-lg"></i></div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm">Paramètres</p>
                                        <p class="text-xs text-slate-500">En bas à gauche de votre écran d'administration.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="text-slate-400 mt-0.5"><i class="ri-notification-3-fill text-lg"></i></div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm">Notifications</p>
                                        <p class="text-xs text-slate-500">Dans la liste du menu des paramètres.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="relative pl-12 pb-8">
                            <div class="absolute left-0 top-0 bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-sm ring-4 ring-white">
                                2
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1 pt-1">Défilez jusqu'à Webhooks</h4>
                            <p class="text-sm text-slate-600 mb-1">Faites défiler vers le bas jusqu'à la section Webhooks.</p>
                            <p class="text-sm text-slate-600">Cliquez sur <strong>Créer un webhook</strong>.</p>
                        </div>

                        <!-- Step 3 -->
                        <div class="relative pl-12 pb-8">
                            <div class="absolute left-0 top-0 bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-sm ring-4 ring-white">
                                3
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1 pt-1">Configurer le webhook</h4>
                            
                            <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 mt-4 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-3">
                                    <span class="text-sm font-bold text-slate-700">Événement</span>
                                    <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full">Création de commande</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-3">
                                    <span class="text-sm font-bold text-slate-700">Format</span>
                                    <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full">JSON</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-3">
                                    <span class="text-sm font-bold text-slate-700">Version de l'API webhook</span>
                                    <span class="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-full">Dernière version</span>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-1">
                                    <span class="text-sm font-bold text-slate-700 whitespace-nowrap">URL</span>
                                    
                                    @if($integration && $integration->webhook_uuid)
                                        <div class="flex items-center w-full sm:w-auto mt-2 sm:mt-0">
                                            <code class="flex-1 sm:flex-none block bg-slate-800 text-green-400 rounded-l-lg border border-slate-700 py-1.5 px-3 text-xs break-all font-mono">
                                                {{ $integration->webhook_url }}
                                            </code>
                                            <button onclick="navigator.clipboard.writeText('{{ $integration->webhook_url }}'); alert('Copié !')" class="bg-indigo-600 hover:bg-indigo-700 text-white py-1.5 px-3 rounded-r-lg border border-indigo-600 transition-colors" title="Copier l'URL">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-400 italic bg-white px-3 py-1.5 rounded border border-slate-200">(Enregistrez d'abord la boutique pour obtenir l'URL)</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 items-start">
                                <i class="ri-alert-line text-amber-500 text-xl mt-0.5"></i>
                                <div>
                                    <p class="text-sm text-amber-800 font-bold">Important</p>
                                    <p class="text-xs text-amber-700 mt-1">Assurez-vous que la version de l'API est définie sur la plus récente prise en charge pour garantir la compatibilité.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="relative pl-12">
                            <div class="absolute left-0 top-0 bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm shadow-sm ring-4 ring-white">
                                4
                            </div>
                            <h4 class="text-base font-bold text-slate-900 mb-1 pt-1">Créer un webhook</h4>
                            <p class="text-sm text-slate-600 mb-1">Cliquez sur le bouton <strong>Enregistrer</strong>.</p>
                            <p class="text-sm text-slate-600">Votre boutique enverra désormais automatiquement les données de chaque nouvelle commande vers votre tableau de bord.</p>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: 30% width, Sticky -->
        <div class="lg:col-span-1 space-y-6 sticky top-6">
            
            <!-- VIDEO TUTORIAL CARD -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center gap-3">
                    <div class="bg-red-100 text-red-600 w-8 h-8 rounded-lg flex items-center justify-center">
                        <i class="ri-play-fill text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Tutoriel vidéo</h3>
                        <p class="text-xs text-slate-500">Guide visuel étape par étape</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="relative rounded-xl overflow-hidden cursor-pointer group aspect-video bg-slate-900">
                        <img src="https://images.unsplash.com/photo-1611162617474-5b21e879e113?auto=format&fit=crop&q=80&w=800" alt="Video thumbnail" class="w-full h-full object-cover opacity-60 group-hover:opacity-75 transition-opacity">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-12 h-12 bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                                <i class="ri-play-fill text-2xl ml-1"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-3 flex items-center gap-1 font-medium">
                        <i class="ri-time-line"></i> Durée de la vidéo 46 seconds
                    </p>
                </div>
            </div>

            <!-- HELP CARD -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center gap-3">
                    <div class="bg-blue-100 text-blue-600 w-8 h-8 rounded-full flex items-center justify-center">
                        <i class="ri-question-mark text-lg font-bold"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Besoin d'aide ?</h3>
                </div>
                <div class="p-2">
                    <a href="#" class="flex items-center justify-between p-3 hover:bg-slate-50 rounded-xl transition-colors group">
                        <div class="flex items-center gap-3">
                            <i class="ri-book-open-line text-slate-400 group-hover:text-indigo-600"></i>
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-600">Guide des webhooks {{ $platformConfig['name'] }}</span>
                        </div>
                        <i class="ri-external-link-line text-slate-400 group-hover:text-indigo-600"></i>
                    </a>
                    <a href="#" class="flex items-center justify-between p-3 hover:bg-slate-50 rounded-xl transition-colors group">
                        <div class="flex items-center gap-3">
                            <i class="ri-customer-service-2-line text-slate-400 group-hover:text-indigo-600"></i>
                            <span class="text-sm font-bold text-slate-700 group-hover:text-indigo-600">Contacter l'assistance</span>
                        </div>
                        <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-indigo-600"></i>
                    </a>
                </div>
                <div class="px-5 pb-5 pt-2">
                    <p class="text-xs text-slate-500 flex items-center gap-2">
                        <i class="ri-mail-line"></i> support@vendex.com
                    </p>
                </div>
            </div>

        </div>

    </div>
</div>
