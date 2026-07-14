<div class="space-y-6">
    {{-- Connection Status Card --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="ri-file-excel-2-line text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('Google Sheets Integration')</h3>
                        <p class="text-xs text-slate-500 mt-0.5">@lang('Import orders from a Google Sheet automatically')</p>
                    </div>
                </div>
                @if($connection && $connection->is_active)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        @lang('Active')
                    </span>
                @elseif($connection)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full">
                        <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
                        @lang('Paused')
                    </span>
                @endif
            </div>
        </div>

        <div class="p-6">
            @if($connection && $connection->mapping_confirmed)
                {{-- Connected State --}}
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase">@lang('Sheet ID')</p>
                            <p class="text-sm font-mono text-slate-700 mt-1">{{ $connection->sheet_id }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-slate-500 uppercase">@lang('Last Sync')</p>
                            <p class="text-sm text-slate-700 mt-1">
                                {{ $connection->last_synced_at ? $connection->last_synced_at->diffForHumans() : __('Never') }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-indigo-50 rounded-xl text-center">
                            <p class="text-2xl font-black text-indigo-600">{{ $connection->sync_interval_minutes }}</p>
                            <p class="text-xs text-indigo-500 font-bold mt-1">@lang('Min. Interval')</p>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl text-center">
                            <p class="text-2xl font-black text-slate-600">{{ count($connection->column_mapping ?? []) }}</p>
                            <p class="text-xs text-slate-500 font-bold mt-1">@lang('Mapped Columns')</p>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button wire:click="toggleActive" class="flex-1 px-4 py-2 text-sm font-bold rounded-xl transition
                            {{ $connection->is_active ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }}">
                            {{ $connection->is_active ? __('Pause Sync') : __('Resume Sync') }}
                        </button>
                        <button wire:click="disconnect" wire:confirm="{{ __('Are you sure? This will remove the sheet connection.') }}"
                                class="px-4 py-2 text-sm font-bold bg-red-100 text-red-700 hover:bg-red-200 rounded-xl transition">
                            @lang('Disconnect')
                        </button>
                    </div>
                </div>
            @else
                {{-- Setup State --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">@lang('Google Sheet ID')</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="sheetId" placeholder="e.g. 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms"
                                   class="flex-1 text-sm border-slate-200 rounded-xl focus:ring-indigo-500 focus:border-indigo-500">
                            <button wire:click="testConnection" wire:loading.attr="disabled"
                                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="testConnection">@lang('Test')</span>
                                <span wire:loading wire:target="testConnection"><i class="ri-loader-4-line animate-spin"></i></span>
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">
                            @lang('Share your sheet with:') <code class="bg-slate-100 px-1 py-0.5 rounded text-indigo-600">{{ config('services.google_sheets.service_account_email', 'service-account@vendex.iam.gserviceaccount.com') }}</code>
                        </p>
                    </div>

                    @if($errorMessage)
                        <div class="p-3 bg-red-50 border border-red-200 rounded-xl">
                            <p class="text-sm text-red-600 font-medium">{{ $errorMessage }}</p>
                        </div>
                    @endif

                    @if($testResult && $testResult['success'])
                        <div class="p-3 bg-green-50 border border-green-200 rounded-xl">
                            <p class="text-sm text-green-700 font-bold"><i class="ri-check-line"></i> @lang('Connected to:') {{ $testResult['title'] }}</p>
                            <p class="text-xs text-green-600 mt-1">{{ $testResult['header_count'] }} @lang('columns detected')</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Column Mapping Step --}}
    @if($showMappingStep && !($connection && $connection->mapping_confirmed))
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('Column Mapping')</h3>
                <p class="text-xs text-slate-500 mt-1">@lang('Confirm how each column maps to a system field. Auto-detected matches are pre-selected.')</p>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach($detectedHeaders as $header)
                    <div class="p-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-8 w-8 bg-slate-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="ri-table-line text-slate-400"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-700 truncate">{{ $header }}</p>
                                @if(isset($autoMappingResult[$header]) && $autoMappingResult[$header]['confidence'] === 'high')
                                    <p class="text-xs text-green-500 font-medium">@lang('Auto-detected')</p>
                                @elseif(isset($autoMappingResult[$header]) && $autoMappingResult[$header]['confidence'] === 'medium')
                                    <p class="text-xs text-yellow-500 font-medium">@lang('Best guess — please confirm')</p>
                                @else
                                    <p class="text-xs text-slate-400">@lang('Not recognized')</p>
                                @endif
                            </div>
                        </div>

                        <select wire:model.live="columnMapping.{{ $header }}" 
                                class="text-sm border-slate-200 rounded-xl focus:ring-indigo-500 w-48
                                    {{ isset($autoMappingResult[$header]) && $autoMappingResult[$header]['confidence'] === 'high' ? 'border-green-300 bg-green-50' : '' }}">
                            <option value="">— @lang('Skip') —</option>
                            @foreach($this->systemFields as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="p-6 bg-slate-50 flex items-center justify-between">
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase mb-1">@lang('Sync Interval (minutes)')</label>
                    <input type="number" wire:model="syncInterval" min="5" max="60" 
                           class="w-24 text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                </div>
                <button wire:click="saveMapping" class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition">
                    <i class="ri-save-line mr-1"></i> @lang('Save & Activate')
                </button>
            </div>
        </div>
    @endif

    {{-- SKU Reference --}}
    @if(!empty($storeSkus))
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('Your Product SKUs')</h3>
                <p class="text-xs text-slate-500 mt-1">@lang('Use these SKU codes in your Google Sheet for accurate matching')</p>
            </div>
            <div class="max-h-64 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">@lang('SKU')</th>
                            <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">@lang('Product')</th>
                            <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">@lang('Variant')</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($storeSkus as $sku)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 font-mono font-bold text-indigo-600">{{ $sku['sku'] }}</td>
                                <td class="px-4 py-2 text-slate-700">{{ $sku['product'] }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $sku['variant'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
