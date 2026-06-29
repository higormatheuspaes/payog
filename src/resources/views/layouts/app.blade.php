<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'HMPay') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Anti-flash + reaplicar dark após wire:navigate --}}
        <script>
            function applyTheme() {
                const dark = localStorage.getItem('theme') === 'dark' ||
                    (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            }
            applyTheme();
            document.addEventListener('livewire:navigated', applyTheme);
        </script>
    </head>
    <body
        x-data="{ open: false }"
        @@livewire:navigated.window="open = false"
        class="font-sans antialiased bg-gray-100"
    >
        {{-- Backdrop mobile --}}
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            @click="open = false"
            class="fixed inset-0 bg-black/50 z-40 lg:hidden"
        ></div>

        <div class="flex h-screen overflow-hidden">

            {{-- Sidebar wrapper: overlay fixo no mobile, estático no desktop --}}
            <div
                class="fixed inset-y-0 left-0 z-50 w-64 transition-transform duration-300 ease-in-out lg:static lg:z-auto lg:translate-x-0"
                :class="open ? 'translate-x-0' : '-translate-x-full'"
            >
                <livewire:layout.navigation />
            </div>

            <div class="flex flex-col flex-1 overflow-hidden min-w-0">

                {{-- Header mobile --}}
                <header class="flex-shrink-0 h-14 bg-white border-b border-gray-200 flex items-center gap-3 px-4 lg:hidden">
                    <button
                        @click="open = !open"
                        class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
                        aria-label="Abrir menu"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <span class="text-lg font-bold text-gray-900 flex-1">HMPay</span>
                    <button
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
                        class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                        <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>
                </header>

                <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                    {{ $slot }}
                </main>

            </div>
        </div>
    </body>
</html>
