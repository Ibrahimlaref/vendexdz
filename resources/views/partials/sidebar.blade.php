<aside id="sidebar"
    class="fixed lg:static inset-y-0 left-0 w-64 bg-white border-r border-gray-100 flex flex-col justify-between flex-shrink-0 z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div>
        <div class="h-16 flex items-center px-6 gap-2">
            <x-application-vector class="w-10 h-10"></x-application-vector>
            <span class="text-2xl font-bold text-slate-800 tracking-tight">Vendex</span>
        </div>

        <div class="px-4 mb-4">
            <div class="bg-gray-100 p-1 rounded-lg flex text-xs font-medium">
                <button
                    class="flex-1 text-gray-500 py-1.5 rounded-md hover:text-gray-700 flex items-center justify-center gap-2 transition">
                    <i class="ri-filter-3-line"></i> @lang('Admin Panel')
                </button>

            </div>
        </div>

        <nav class="px-3 space-y-0.5  capitalize">


            <x-responsive-sidebar-link href="{{ route('admin-dashboard') }}" :active="request()->routeIs('admin-dashboard')">
                <i class="ri-home-4-line text-sm"></i> @lang('Dashboard')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.link') }}" :active="request()->routeIs('admin.link')">
                <i class="ri-pulse-line text-sm"></i> @lang('Links')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('log-viewer') }}" :active="request()->routeIs('log-viewer')">
                <i class="ri-newspaper-line text-sm"></i> @lang('Logs')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.users') }}" :active="request()->routeIs('admin.users')">
                <i class="ri-group-line text-sm"></i> @lang('Users')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.status') }}" :active="request()->routeIs('admin.status')">
                <i class="ri-store-2-line text-sm"></i> @lang('Status')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.orders') }}" :active="request()->routeIs('admin.orders')">
                <i class="ri-box-3-line text-sm"></i> @lang('Orders')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.create-order') }}" :active="request()->routeIs('admin.create-order')">
                <i class="ri-box-3-line text-sm"></i> @lang('Create Order')
            </x-responsive-sidebar-link>
            <!-- <x-responsive-sidebar-link href="{{ route('admin-dashboard') }}" :active="request()->routeIs('admin-dashboard')">
                <i class="ri-price-tag-3-line text-sm"></i> @lang('Products')
            </x-responsive-sidebar-link> -->
            <x-responsive-sidebar-link href="{{ route('admin.fees') }}" :active="request()->routeIs('admin.fees')">
                <i class="ri-coins-line text-sm"></i> @lang('Fees')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.commission') }}" :active="request()->routeIs('admin.commission') || request()->routeIs('admin.commission.show')">
                <i class="ri-money-dollar-circle-line text-sm"></i> @lang('Commission')
            </x-responsive-sidebar-link>
            <x-responsive-sidebar-link href="{{ route('admin.payroll') }}" :active="request()->routeIs('admin.payroll')">
                <i class="ri-file-list-3-line text-sm"></i> @lang('Payroll')
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