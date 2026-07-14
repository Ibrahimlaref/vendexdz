<div class="space-y-6">
    {{-- Header & Date Filter --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="ri-wallet-3-line text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('My Commission')</h3>
                    <p class="text-xs text-slate-500 mt-0.5">@lang('Commission from delivered orders — filtered by delivery date')</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('From')</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">@lang('To')</label>
                    <input type="date" wire:model.live="dateTo" class="w-full text-sm border-slate-200 rounded-xl focus:ring-indigo-500">
                </div>
            </div>
        </div>
    </div>

    @if($commission)
        {{-- Total Card --}}
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-6 text-white">
            <p class="text-sm font-bold text-green-200 uppercase">@lang('Total Earned')</p>
            <p class="text-4xl font-black mt-1">{{ number_format($commission['total'], 2) }} <span class="text-lg font-bold text-green-200">DA</span></p>
            <p class="text-sm text-green-200 mt-2">{{ $commission['delivered_count'] }} @lang('delivered orders')</p>
        </div>

        {{-- Product Breakdown --}}
        @if(!empty($commission['breakdown']))
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('Breakdown by Product')</h3>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($commission['breakdown'] as $b)
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold text-slate-700">{{ $b['product_name'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $b['total_qty'] }} @lang('units') × {{ number_format($b['rate'], 2) }} DA
                                    · {{ $b['order_count'] }} @lang('orders')
                                </p>
                            </div>
                            <p class="text-lg font-black text-green-600">{{ number_format($b['subtotal'], 2) }} <span class="text-xs text-slate-400">DA</span></p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
                <i class="ri-wallet-line text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-500 mt-2">@lang('No delivered orders in this period')</p>
            </div>
        @endif
    @endif
</div>
