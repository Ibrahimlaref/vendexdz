<aside id="sidebar"
    class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-100 flex flex-col justify-between flex-shrink-0 z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div>
        <div class="h-16 flex items-center px-6 gap-2">
            <x-application-vector class="w-10 h-10"></x-application-vector>
            <span class="text-2xl font-bold text-slate-800 tracking-tight">Vendex</span>
        </div>

        <div class="px-4 mb-4">
            <div class="bg-gray-100 p-1 rounded-lg flex text-xs font-medium">
                <div
                    class="flex-1 text-gray-500 py-1.5 rounded-md hover:text-gray-700 flex items-center justify-center gap-2 transition">
                    <i class="ri-store-2-line"></i> @lang('Store Panel')
                </div>

            </div>
        </div>

        <nav class="px-3 space-y-0.5  capitalize">


            <x-responsive-sidebar-link href="{{ route('store-dashboard') }}"
                :active="request()->routeIs('store-dashboard')">
                <i class="ri-home-4-line text-sm"></i> @lang('Dashboard')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('store.orders') }}" :active="request()->routeIs('store.orders')">
                <i class="ri-box-3-line text-sm"></i> @lang('Orders')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('store.create-order') }}" :active="request()->routeIs('store.create-order')">
                <i class="ri-box-3-line text-sm"></i> @lang('Create Order')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('store.products') }}"
                :active="request()->routeIs('store.products')">
                <i class="ri-price-tag-3-line text-sm"></i> @lang('Products')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('store.companies') }}"
                :active="request()->routeIs('store.companies')">
                <i class="ri-truck-line text-sm"></i> @lang('Delivery company')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('store.territories') }}"
                :active="request()->routeIs('store.territories')">
                <i class="ri-map-2-line text-sm"></i> @lang('territories & fees')
            </x-responsive-sidebar-link>
            <!-- <x-responsive-sidebar-link href="{{ route('store.bills') }}" :active="request()->routeIs('store.bills')">
                <i class="ri-coins-line text-sm"></i> Bills
            </x-responsive-sidebar-link> -->
            <x-responsive-sidebar-link href="{{ route('store.integrations') }}"
                :active="request()->routeIs('store.integrations') || request()->routeIs('store.integrations.setup')">
                <i class="ri-links-line text-sm"></i> @lang('Magasins')
            </x-responsive-sidebar-link>
        </nav>
    </div>

    <div class="p-4 border-t border-gray-100">


        <a href="#"
            class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-xl cursor-pointer transition-all duration-200 group border border-transparent hover:border-slate-100">
            <div
                class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shadow-sm group-hover:scale-105 transition-transform">
                @if(Auth::user()->profile_photo_path)
                <img src="/storage/{{ Auth::user()->profile_photo_path }}" alt="{{ Auth::user()->name }}"
                    class="w-full h-full rounded-full object-cover">
                @else
                {{ Auth::user()->short_name }}
                @endif
            </div>

            <div class="flex flex-col flex-1 overflow-hidden">
                <span class="text-sm font-bold text-slate-800 truncate">
                    {{ Auth::user()->name }}
                </span>

            </div>

            <i
                class="ri-arrow-right-s-line text-slate-300 group-hover:text-indigo-500 group-hover:translate-x-1 transition-all"></i>
        </a>

        <div class="flex justify-center mt-2 px-2 text-gray-400">
            <!-- <button>
             <i class="ri-settings-4-line hover:text-gray-600 cursor-pointer text-sm"></i>
            </button>  
            
            <button>
             <i class="ri-translate-2 hover:text-gray-600 cursor-pointer text-sm"></i>
            </button>   -->

            <form method="POST" action="{{ route('logout') }}" x-data>
                @csrf
                <button href="{{ route('logout') }}" class="w-full justify-center px-6 py-2 bg-gray-700 hover:bg-gray-900 active:bg-indigo-800 rounded-xl shadow-md transition ease-in-out duration-150 text-white font-semibold tracking-wide"
                    @click.prevent="$root.submit();">
                    @lang('Logout')
                    <i class="ri-logout-box-line hover:text-gray-600 cursor-pointer text-sm"></i>
                </button>
            </form>

        </div>
    </div>
</aside>