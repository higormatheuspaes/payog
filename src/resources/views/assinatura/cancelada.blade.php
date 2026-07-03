<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura cancelada — Payog</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center">

        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Assinatura cancelada</h1>
        <p class="text-gray-500 mb-8 leading-relaxed">
            Sua assinatura foi cancelada. Seus dados continuam salvos por
            <strong class="text-gray-700">30 dias</strong> — você pode reativar a qualquer momento.
        </p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-4 mb-6 text-left">
                {{ $errors->first('msg') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6 text-left">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                <span class="text-sm font-medium text-gray-700">Conta desativada</span>
            </div>
            <div class="space-y-2 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                    </svg>
                    Seus dados permanecem seguros e preservados
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reative agora e volte de onde parou
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('assinatura.checkout') }}">
            @csrf
            <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 active:bg-indigo-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Reativar assinatura
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                Sair da conta
            </button>
        </form>

        <p class="mt-6 text-xs text-gray-400">
            Algum problema? <a href="mailto:suporte@payog.com.br" class="text-indigo-600 hover:underline">suporte@payog.com.br</a>
        </p>
    </div>
</body>
</html>
