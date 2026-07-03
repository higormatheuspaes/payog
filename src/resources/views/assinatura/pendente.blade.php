<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize seu pagamento — Payog</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="max-w-md w-full text-center">

        <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">Finalize seu pagamento</h1>
        <p class="text-gray-500 mb-8 leading-relaxed">
            Sua conta foi criada, mas o pagamento ainda não foi concluído.
            Clique abaixo para ir ao checkout e ativar seu acesso.
        </p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-4 mb-6 text-left">
                {{ $errors->first('msg') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6 text-left">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-2 h-2 rounded-full bg-yellow-400"></div>
                <span class="text-sm font-medium text-gray-700">Pagamento pendente</span>
            </div>
            <div class="space-y-2 text-xs text-gray-500">
                <div class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Acesso liberado assim que o pagamento for confirmado
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Pagamento processado com segurança pela AbacatePay
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('assinatura.checkout') }}">
            @csrf
            <button type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 active:bg-indigo-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                Ir para o pagamento
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
