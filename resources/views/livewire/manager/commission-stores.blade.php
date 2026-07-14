<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="ri-store-2-line text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">@lang('Commission — Select a Store')</h3>
                    <p class="text-xs text-slate-500 mt-0.5">@lang('View confirmatrice and gérant commissions per store')</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Store Cards --}}
    @if(empty($stores))
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
            <i class="ri-store-line text-4xl text-slate-300"></i>
            <p class="text-sm text-slate-500 mt-2">@lang('No stores assigned to your account')</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($stores as $store)
                <a href="{{ route('admin.commission.show', $store['id']) }}"
                   class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md hover:border-indigo-200 transition group">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-600 font-black text-lg group-hover:bg-indigo-600 group-hover:text-white transition">
                            {{ strtoupper(substr($store['name'], 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate">{{ $store['name'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">@lang('Click to view commissions')</p>
                        </div>
                        <i class="ri-arrow-right-s-line text-slate-300 text-xl group-hover:text-indigo-500 transition"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
