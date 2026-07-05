<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Payog') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
<div class="min-h-screen flex">

    {{-- Painel esquerdo: branding (desktop) --}}
    <div class="hidden lg:flex w-5/12 xl:w-1/2 bg-indigo-600 flex-col justify-between p-14 relative overflow-hidden flex-shrink-0">

        {{-- Círculos decorativos --}}
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-indigo-500 opacity-50 pointer-events-none"></div>
        <div class="absolute top-1/2 -right-20 w-80 h-80 rounded-full bg-indigo-700 opacity-60 pointer-events-none"></div>
        <div class="absolute -bottom-24 left-1/4 w-72 h-72 rounded-full bg-indigo-500 opacity-40 pointer-events-none"></div>

        <div class="relative z-10">
            <span class="text-white text-2xl font-bold tracking-tight">Payog</span>
        </div>

        <div class="relative z-10 space-y-8">
            <div class="space-y-4">
                <h1 class="text-white text-4xl font-bold leading-snug">
                    Gestão de cobranças<br>simplificada.
                </h1>
                <p class="text-indigo-200 text-base leading-relaxed">
                    Controle clientes, parcelas e notificações<br>em um só lugar. Sem complicação.
                </p>
            </div>

            <div class="space-y-3">
                @foreach([
                    ['Cobranças automáticas por WhatsApp', 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                    ['Relatórios em PDF com um clique', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['Histórico completo de cada cliente', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ] as [$texto, $path])
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white bg-opacity-15 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/>
                        </svg>
                    </div>
                    <span class="text-indigo-100 text-sm">{{ $texto }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="relative z-10 text-indigo-300 text-xs">
            © {{ date('Y') }} Payog. Todos os direitos reservados.
        </div>
    </div>

    {{-- Painel direito: formulário --}}
    <div class="flex-1 flex flex-col justify-center items-center bg-gray-50 px-6 py-12 overflow-y-auto">

        {{-- Logo mobile --}}
        <div class="lg:hidden mb-8">
            <span class="text-gray-900 text-2xl font-bold tracking-tight">Payog</span>
        </div>

        <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-gray-400">© {{ date('Y') }} Payog</p>
    </div>

</div>
</body>
</html>
