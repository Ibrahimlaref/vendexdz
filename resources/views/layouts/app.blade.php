<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vendex Dashboard')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    body {
        font-family: 'Inter', sans-serif;
    }

    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    @media print {
        #sidebar, header, nav, button, .no-print, input, select, label, .fixed {
            display: none !important;
        }
        main {
            padding: 0 !important;
            margin: 0 !important;
            background: white !important;
            width: 100% !important;
            overflow: visible !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
        }
        body {
            background: white !important;
            color: black !important;
        }
        .shadow-sm, .shadow-md, .shadow-lg {
            box-shadow: none !important;
            border: 1px solid #e2e8f0 !important;
        }
        .flex-1 {
            overflow: visible !important;
        }
        .h-screen {
            height: auto !important;
            overflow: visible !important;
        }
    }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
</head>

<body class="bg-[#F9FAFB] text-slate-800 antialiased">
    <x-notifications />
    <div class="flex h-screen overflow-hidden">

        @include('partials.sidebar')

        <div class="flex-1 flex flex-col min-w-0">

            @include('partials.header')

            <main class="flex-1 overflow-y-auto p-8">
                @yield('content')
                {{ $slot ?? '' }}
            </main>

        </div>

        <!-- <div class="fixed bottom-6 right-6 z-50">
            <div
                class="w-14 h-14 bg-[#111827] rounded-full shadow-lg shadow-indigo-900/20 flex items-center justify-center cursor-pointer hover:scale-110 transition duration-300 border-2 border-indigo-500/30">
                <div class="relative">
                    <i
                        class="ri-robot-2-fill text-2xl bg-gradient-to-tr from-indigo-400 to-cyan-300 bg-clip-text text-transparent"></i>
                </div>
            </div>
        </div> -->

    </div>
    @stack('modals')

    @livewireScripts
</body>

</html>