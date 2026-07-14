<div class="space-y-6">
    {{-- Sync Health Summary --}}
    @if($connection)
        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-4 text-center">
                <p class="text-2xl font-black text-slate-700">{{ $syncStats['total'] }}</p>
                <p class="text-xs font-bold text-slate-500 uppercase mt-1">@lang('Total Rows')</p>
            </div>
            <div class="bg-white rounded-2xl border border-green-200 p-4 text-center cursor-pointer hover:bg-green-50 transition"
                 wire:click="setFilter('success')">
                <p class="text-2xl font-black text-green-600">{{ $syncStats['success'] }}</p>
                <p class="text-xs font-bold text-green-500 uppercase mt-1">@lang('Imported')</p>
            </div>
            <div class="bg-white rounded-2xl border border-yellow-200 p-4 text-center cursor-pointer hover:bg-yellow-50 transition"
                 wire:click="setFilter('needs_review')">
                <p class="text-2xl font-black text-yellow-600">{{ $syncStats['needs_review'] }}</p>
                <p class="text-xs font-bold text-yellow-500 uppercase mt-1">@lang('Needs Review')</p>
            </div>
            <div class="bg-white rounded-2xl border border-red-200 p-4 text-center cursor-pointer hover:bg-red-50 transition"
                 wire:click="setFilter('failed')">
                <p class="text-2xl font-black text-red-600">{{ $syncStats['failed'] }}</p>
                <p class="text-xs font-bold text-red-500 uppercase mt-1">@lang('Failed')</p>
            </div>
        </div>
    @endif

    {{-- Review Queue --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                    @lang('Sync Log') — 
                    @if($statusFilter === 'needs_review') @lang('Needs Review')
                    @elseif($statusFilter === 'failed') @lang('Failed')
                    @elseif($statusFilter === 'success') @lang('Imported')
                    @else @lang('All')
                    @endif
                </h3>
                <div class="flex gap-1">
                    @foreach(['needs_review' => __('Review'), 'failed' => __('Failed'), 'success' => __('Imported'), 'all' => __('All')] as $key => $label)
                        <button wire:click="setFilter('{{ $key }}')"
                                class="px-3 py-1 text-xs font-bold rounded-lg transition
                                    {{ $statusFilter === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if($logs->isEmpty())
            <div class="p-12 text-center">
                <i class="ri-inbox-line text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-500 mt-2">@lang('No records to display')</p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($logs as $log)
                    <div wire:key="log-{{ $log->id }}" class="p-4 hover:bg-slate-50/50 transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-bold rounded-full
                                        {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $log->status === 'needs_review' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $log->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                    </span>
                                    <span class="text-xs text-slate-400">@lang('Row') #{{ $log->row_number }}</span>
                                    <span class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
                                </div>

                                @if($log->error_message)
                                    <p class="text-xs text-slate-600 mt-1">{{ $log->error_message }}</p>
                                @endif

                                {{-- Row data preview --}}
                                @if($log->row_data)
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @foreach(array_slice($log->row_data, 0, 4) as $key => $value)
                                            <span class="inline-flex text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded">
                                                <strong>{{ $key }}:</strong>&nbsp;{{ \Illuminate\Support\Str::limit($value, 20) }}
                                            </span>
                                        @endforeach
                                        @if(count($log->row_data) > 4)
                                            <span class="text-xs text-slate-400">+{{ count($log->row_data) - 4 }} @lang('more')</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Resolve Form --}}
                                @if($log->status === 'needs_review' && $resolveRowId === $log->id)
                                    <div class="mt-3 p-3 bg-indigo-50 rounded-xl space-y-2">
                                        <p class="text-xs font-bold text-indigo-700 uppercase">@lang('Assign Product')</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <select wire:model.live="resolveProductId" class="text-sm border-slate-200 rounded-lg">
                                                <option value="">@lang('Select Product')</option>
                                                @foreach($availableProducts as $product)
                                                    <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:model="resolveVariantId" class="text-sm border-slate-200 rounded-lg">
                                                <option value="">@lang('Select Variant')</option>
                                                @foreach($availableVariants as $variant)
                                                    <option value="{{ $variant['id'] }}">{{ $variant['sku'] }} — {{ $variant['label'] ?? 'Default' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="flex gap-2">
                                            <button wire:click="resolveRow" class="px-3 py-1.5 text-xs font-bold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                                <i class="ri-check-line"></i> @lang('Create Order')
                                            </button>
                                            <button wire:click="$set('resolveRowId', null)" class="px-3 py-1.5 text-xs font-bold bg-slate-200 text-slate-600 rounded-lg hover:bg-slate-300 transition">
                                                @lang('Cancel')
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            @if($log->status === 'needs_review' && $resolveRowId !== $log->id)
                                <div class="flex gap-1 flex-shrink-0">
                                    <button wire:click="startResolve({{ $log->id }})" 
                                            class="px-3 py-1.5 text-xs font-bold bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition">
                                        <i class="ri-edit-line"></i> @lang('Resolve')
                                    </button>
                                    <button wire:click="skipRow({{ $log->id }})" 
                                            class="px-3 py-1.5 text-xs font-bold bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition">
                                        @lang('Skip')
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 bg-slate-50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
