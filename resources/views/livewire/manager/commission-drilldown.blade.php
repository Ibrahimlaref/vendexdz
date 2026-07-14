<div class="space-y-6">
    {{-- Header + Date Range + Export --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.commission') }}" class="h-10 w-10 bg-slate-100 rounded-lg flex items-center justify-center hover:bg-indigo-100 transition">
                        <i class="ri-arrow-left-line text-slate-600"></i>
                    </a>
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">{{ $storeName }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">@lang('Commission by delivery date')</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="exportExcel" class="px-4 py-2 text-sm font-bold bg-green-600 text-white rounded-xl hover:bg-green-700 transition">
                        <i class="ri-file-excel-line mr-1"></i> @lang('Export Excel')
                    </button>
                    <button onclick="downloadPDF('commission_report.pdf');" class="px-4 py-2 text-sm font-bold bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition">
                        <i class="ri-file-pdf-line mr-1"></i> @lang('Export PDF')
                    </button>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="mb-4 flex items-center gap-2">
                <span class="text-xs font-bold text-slate-400 uppercase">@lang('Quick Ranges'):</span>
                <button wire:click="setTodayRange" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-indigo-100 text-slate-700 hover:text-indigo-600 rounded-lg transition">
                    @lang('Today')
                </button>
                <button wire:click="setThisMonthRange" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-indigo-100 text-slate-700 hover:text-indigo-600 rounded-lg transition">
                    @lang('This Month')
                </button>
            </div>
            <div class="grid grid-cols-2 gap-4 max-w-md">
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

    {{-- Grand Totals --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-2xl p-6 text-white">
            <p class="text-sm font-bold text-indigo-200 uppercase">@lang('Total Confirmatrice Commission')</p>
            <p class="text-3xl font-black mt-1">{{ number_format($confirmatriceTotal, 2) }} <span class="text-base font-bold text-indigo-200">DA</span></p>
            <p class="text-sm text-indigo-200 mt-1">{{ count($confirmatrices) }} @lang('agents')</p>
        </div>
        <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl p-6 text-white">
            <p class="text-sm font-bold text-emerald-200 uppercase">@lang('Total Gérant Commission')</p>
            <p class="text-3xl font-black mt-1">{{ number_format($gerantTotal, 2) }} <span class="text-base font-bold text-emerald-200">DA</span></p>
            <p class="text-sm text-emerald-200 mt-1">{{ count($gerants) }} @lang('gérant(s)')</p>
        </div>
    </div>

    {{-- Confirmatrices Section --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                <i class="ri-user-line text-indigo-500 mr-1"></i> @lang('Confirmatrices')
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">@lang('Commission from delivered orders they confirmed')</p>
        </div>

        @if(empty($confirmatrices))
            <div class="p-12 text-center">
                <i class="ri-user-search-line text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-500 mt-2">@lang('No delivered orders in this period')</p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($confirmatrices as $agent)
                    {{-- Agent Summary Row --}}
                    <div>
                        <div class="p-4 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition"
                             wire:click="toggleAgent({{ $agent['agent_id'] }})">
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-black text-sm">
                                    {{ strtoupper(substr($agent['agent_name'], 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ $agent['agent_name'] }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $agent['delivered_count'] }} @lang('delivered orders')
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-lg font-black text-indigo-600">{{ number_format($agent['total_commission'], 2) }} <span class="text-xs text-slate-400">DA</span></p>
                                <i class="ri-arrow-{{ $expandedAgentId === $agent['agent_id'] ? 'up' : 'down' }}-s-line text-slate-400 text-xl"></i>
                            </div>
                        </div>

                        {{-- Expanded Breakdown --}}
                        @if($expandedAgentId === $agent['agent_id'])
                            <div class="border-t border-slate-100 bg-slate-50/50">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-slate-100">
                                            <th class="px-4 py-2 text-left text-xs font-bold text-slate-500 uppercase">@lang('Product')</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-slate-500 uppercase">@lang('Orders')</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-slate-500 uppercase">@lang('Total Qty')</th>
                                            <th class="px-4 py-2 text-center text-xs font-bold text-slate-500 uppercase">@lang('Rate (DA)')</th>
                                            <th class="px-4 py-2 text-right text-xs font-bold text-slate-500 uppercase">@lang('Subtotal (DA)')</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($agent['breakdown'] as $b)
                                            <tr class="hover:bg-white transition">
                                                <td class="px-4 py-2 font-bold text-slate-700">{{ $b['product_name'] }}</td>
                                                <td class="px-4 py-2 text-center text-slate-600">{{ $b['order_count'] }}</td>
                                                <td class="px-4 py-2 text-center text-slate-600">{{ $b['total_qty'] }}</td>
                                                <td class="px-4 py-2 text-center text-slate-600">{{ number_format($b['rate'], 2) }}</td>
                                                <td class="px-4 py-2 text-right font-bold text-indigo-600">{{ number_format($b['subtotal'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Gérants Section --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50/50">
            <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest">
                <i class="ri-shield-user-line text-emerald-500 mr-1"></i> @lang('Gérants')
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">@lang('Aggregate commission across their confirmatrices')</p>
        </div>

        @if(empty($gerants))
            <div class="p-12 text-center">
                <i class="ri-shield-user-line text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-500 mt-2">@lang('No gérants found for this store')</p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($gerants as $gerant)
                    <div class="p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600 font-black text-sm">
                                {{ strtoupper(substr($gerant['gerant_name'], 0, 2)) }}
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800">{{ $gerant['gerant_name'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $gerant['delivered_count'] }} @lang('delivered orders') · {{ $gerant['agent_count'] }} @lang('agents')
                                </p>
                            </div>
                        </div>
                        <p class="text-lg font-black text-emerald-600">{{ number_format($gerant['total_commission'], 2) }} <span class="text-xs text-slate-400">DA</span></p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Hidden Invoice Print Template -->
    <div id="invoice-template" class="hidden">
        <div style="padding: 40px; font-family: 'Inter', sans-serif; background: #fff; color: #1e293b; max-width: 800px; margin: 0 auto;">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800; color: #4f46e5; margin: 0; letter-spacing: -0.5px;">VENDEX</h1>
                    <p style="font-size: 11px; color: #64748b; margin: 2px 0 0 0; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Commission Payout Invoice</p>
                </div>
                <div style="text-align: right;">
                    <h2 style="font-size: 18px; font-weight: 800; margin: 0; color: #0f172a; letter-spacing: -0.5px;">PAYOUT STATEMENT</h2>
                    <p style="font-size: 12px; color: #64748b; margin: 4px 0 0 0;">Store: <span style="font-weight: 700; color: #334155;">{{ $storeName }}</span></p>
                </div>
            </div>

            <!-- Meta Info -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 35px; font-size: 13px;">
                <div>
                    <p style="margin: 0; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Issuer</p>
                    <p style="margin: 4px 0 0 0; color: #1e293b; font-weight: 600;">Vendex Platform Administrator</p>
                    <p style="margin: 2px 0 0 0; color: #475569;">admin@vendex.com</p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Invoice Details</p>
                    <p style="margin: 4px 0 0 0; color: #1e293b;">Date: <span style="font-weight: 600;">{{ now()->format('d/m/Y') }}</span></p>
                    <p style="margin: 2px 0 0 0; color: #1e293b;">Period: <span style="font-weight: 600;">{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</span></p>
                </div>
            </div>

            <!-- Table -->
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px; margin-bottom: 35px;">
                <thead>
                    <tr style="border-bottom: 2px solid #cbd5e1; background-color: #f8fafc;">
                        <th style="padding: 12px 10px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Recipient Type</th>
                        <th style="padding: 12px 10px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Full Name</th>
                        <th style="padding: 12px 10px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Contact Info</th>
                        <th style="padding: 12px 10px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; text-align: center;">Orders</th>
                        <th style="padding: 12px 10px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; text-align: right;">Amount Due</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Confirmatrices -->
                    @forelse($confirmatrices as $agent)
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px 10px; color: #4f46e5; font-weight: 600;">Confirmatrice</td>
                            <td style="padding: 12px 10px; font-weight: 600; color: #1e293b;">{{ $agent['agent_name'] }}</td>
                            <td style="padding: 12px 10px; color: #475569; font-size: 11px;">
                                Email: {{ $agent['agent_email'] ?? 'N/A' }}<br>Phone: {{ $agent['agent_phone'] ?? 'N/A' }}
                            </td>
                            <td style="padding: 12px 10px; color: #334155; text-align: center; font-weight: 600;">{{ $agent['delivered_count'] }}</td>
                            <td style="padding: 12px 10px; font-weight: 700; color: #1e293b; text-align: right; font-size: 13px;">{{ number_format($agent['total_commission'], 2) }} DA</td>
                        </tr>
                    @empty
                        <!-- Handled inside grand empty wrapper -->
                    @endforelse

                    <!-- Gérants -->
                    @forelse($gerants as $gerant)
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px 10px; color: #10b981; font-weight: 600;">Gérant / Manager</td>
                            <td style="padding: 12px 10px; font-weight: 600; color: #1e293b;">{{ $gerant['gerant_name'] }}</td>
                            <td style="padding: 12px 10px; color: #475569; font-size: 11px;">
                                Email: {{ $gerant['gerant_email'] ?? 'N/A' }}<br>Phone: {{ $gerant['gerant_phone'] ?? 'N/A' }}
                            </td>
                            <td style="padding: 12px 10px; color: #334155; text-align: center; font-weight: 600;">{{ $gerant['delivered_count'] }}</td>
                            <td style="padding: 12px 10px; font-weight: 700; color: #1e293b; text-align: right; font-size: 13px;">{{ number_format($gerant['total_commission'], 2) }} DA</td>
                        </tr>
                    @empty
                        <!-- Handled inside grand empty wrapper -->
                    @endforelse

                    @if(empty($confirmatrices) && empty($gerants))
                        <tr>
                            <td colspan="5" style="padding: 40px 10px; text-align: center; color: #94a3b8; font-size: 13px;">
                                No delivered orders or payments calculated for the selected period.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <!-- Totals -->
            <div style="display: flex; justify-content: flex-end; font-size: 13px;">
                <div style="width: 280px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                    <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #e2e8f0; color: #475569;">
                        <span>Confirmatrices Total:</span>
                        <span style="font-weight: 600; color: #1e293b;">{{ number_format($confirmatriceTotal, 2) }} DA</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e2e8f0; color: #475569; margin-top: 4px;">
                        <span>Gérants Total:</span>
                        <span style="font-weight: 600; color: #1e293b;">{{ number_format($gerantTotal, 2) }} DA</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 8px; font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        <span>Total Payout Due:</span>
                        <span style="color: #4f46e5;">{{ number_format($confirmatriceTotal + $gerantTotal, 2) }} DA</span>
                    </div>
                </div>
            </div>

            <!-- Footer Notice -->
            <div style="margin-top: 60px; border-top: 1px dashed #cbd5e1; padding-top: 20px; text-align: center; font-size: 11px; color: #94a3b8; line-height: 1.5;">
                <p style="margin: 0; font-weight: 600;">This document serves as an official payout invoice generated by the Vendex Platform.</p>
                <p style="margin: 2px 0 0 0;">Commissions are accrued exclusively upon successful delivery status confirmation from the logistics courier.</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
    function downloadPDF(filename) {
        // Clone the hidden invoice element
        const element = document.getElementById('invoice-template').cloneNode(true);
        element.classList.remove('hidden'); // Show cloned node for DOM rendering

        const opt = {
            margin:       15,
            filename:     filename || 'invoice.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' } // Portrait orientation for invoices
        };
        
        html2pdf().set(opt).from(element).save();
    }
    </script>
</div>
