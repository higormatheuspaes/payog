<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página não encontrada — {{ config('app.name', 'Payog') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen flex items-center justify-center px-4">

    <div class="text-center max-w-md w-full">

        {{-- Número grande --}}
        <div class="relative mb-6 select-none">
            <span class="text-[9rem] font-black text-gray-100 leading-none">404</span>
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-20 h-20 rounded-2xl bg-indigo-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Página não encontrada</h1>
        <p class="text-gray-500 text-sm mb-8">
            O endereço que você acessou não existe ou foi movido.<br>
            Verifique o link ou volte para o início.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            @auth
                <a href="{{ route('dashboard') }}"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Ir para o dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Ir para o login
                </a>
            @endauth

            <button onclick="history.back()"
                class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                Voltar
            </button>
        </div>

        <div class="mt-10 text-xs text-gray-300 font-semibold tracking-widest uppercase">
            Payog
        </div>

    </div>

</body>
</html>
