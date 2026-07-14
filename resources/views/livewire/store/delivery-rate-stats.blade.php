<div class="space-y-6">
    {{-- Filters --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                <i class="ri-pie-chart-line text-indigo-500 mr-1"></i> @lang('Delivery Rate — Taux de Livraison')
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('From')</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('To')</label>
                    <input type="date" wire:model.live="dateTo" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                </div>
                @if(count($stores) > 1)
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('Store')</label>
                        <select wire:model.live="selectedStoreId" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                            <option value="">@lang('All Stores')</option>
                            @foreach($stores as $store)
                                <option value="{{ $store['id'] }}">{{ $store['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('Product')</label>
                    <select wire:model.live="selectedProductId" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                        <option value="">@lang('All Products')</option>
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('Category')</label>
                    <select wire:model.live="selectedCategoryId" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                        <option value="">@lang('All Categories')</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['cid'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if($results)
        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-3xl font-black text-indigo-600">{{ $results['summary']['rate'] }}%</p>
                <p class="text-xs font-bold text-slate-500 uppercase mt-1">@lang('Delivery Rate')</p>
            </div>
            <div class="bg-white rounded-2xl border border-green-200 p-5 text-center">
                <p class="text-3xl font-black text-green-600">{{ $results['summary']['delivered'] }}</p>
                <p class="text-xs font-bold text-green-500 uppercase mt-1">@lang('Delivered')</p>
            </div>
            <div class="bg-white rounded-2xl border border-red-200 p-5 text-center">
                <p class="text-3xl font-black text-red-600">{{ $results['summary']['returned'] }}</p>
                <p class="text-xs font-bold text-red-500 uppercase mt-1">@lang('Returned')</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 text-center">
                <p class="text-3xl font-black text-slate-700">{{ $results['summary']['total'] }}</p>
                <p class="text-xs font-bold text-slate-500 uppercase mt-1">@lang('Total Resolved')</p>
            </div>
        </div>

        {{-- By Product Table --}}
        @if($results['by_product']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('By Product')</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">@lang('Product')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Delivered')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Returned')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Rate')</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($results['by_product'] as $row)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-bold text-slate-700">{{ $row->product_name }}</td>
                                    <td class="px-4 py-3 text-center text-green-600 font-bold">{{ $row->delivered }}</td>
                                    <td class="px-4 py-3 text-center text-red-600 font-bold">{{ $row->returned }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-black rounded-full
                                            {{ $row->rate >= 70 ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $row->rate >= 40 && $row->rate < 70 ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $row->rate < 40 ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ $row->rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- By Category Table --}}
        @if($results['by_category']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('By Category')</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">@lang('Category')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Delivered')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Returned')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Rate')</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($results['by_category'] as $row)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-bold text-slate-700">{{ $row->category_name }}</td>
                                    <td class="px-4 py-3 text-center text-green-600 font-bold">{{ $row->delivered }}</td>
                                    <td class="px-4 py-3 text-center text-red-600 font-bold">{{ $row->returned }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-black rounded-full
                                            {{ $row->rate >= 70 ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $row->rate >= 40 && $row->rate < 70 ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $row->rate < 40 ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ $row->rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- By Store (multi-store only) --}}
        @if(!empty($results['by_store']) && count($results['by_store']) > 0)
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('By Store')</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">@lang('Store')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Delivered')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Returned')</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">@lang('Rate')</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($results['by_store'] as $row)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-4 py-3 font-bold text-slate-700">{{ $row->store_name }}</td>
                                    <td class="px-4 py-3 text-center text-green-600 font-bold">{{ $row->delivered }}</td>
                                    <td class="px-4 py-3 text-center text-red-600 font-bold">{{ $row->returned }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2.5 py-1 text-xs font-black rounded-full
                                            {{ $row->rate >= 70 ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $row->rate >= 40 && $row->rate < 70 ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $row->rate < 40 ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ $row->rate }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Empty State --}}
        @if($results['summary']['total'] === 0)
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <i class="ri-bar-chart-box-line text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-500 mt-2">@lang('No delivery data found for the selected filters')</p>
            </div>
        @endif
    @endif
</div>
