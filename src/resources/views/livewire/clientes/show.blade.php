<?php

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Cliente $cliente;

    public function mount(Cliente $cliente): void
    {
        abort_if($cliente->empresa_id !== Auth::user()->empresa_id, 403);

        $this->cliente = $cliente->load([
            'cobrancas.parcelas.logMensagens',
            'scoreHistorico' => fn($q) => $q->with('parcela.cobranca')->latest()->limit(20),
        ]);
    }
}; ?>

<div>
    {{-- Cabeçalho --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('clientes.index') }}" wire:navigate
            class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-semibold text-gray-900 truncate">{{ $cliente->nome }}</h1>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                @if($cliente->telefone)
                    <span class="text-sm text-gray-500">{{ $cliente->telefone }}</span>
                @endif
                @if($cliente->cpf_cnpj)
                    <span class="text-sm text-gray-400">·</span>
                    <span class="text-sm text-gray-500">{{ $cliente->cpf_cnpj }}</span>
                @endif
                @if($cliente->email)
                    <span class="text-sm text-gray-400">·</span>
                    <span class="text-sm text-gray-500">{{ $cliente->email }}</span>
                @endif
            </div>
        </div>

        @php
            $scoreBadge = match($cliente->score_categoria) {
                'bom_pagador' => ['label' => 'Bom pagador', 'class' => 'bg-green-100 text-green-700'],
                'atencao'     => ['label' => 'Atenção',     'class' => 'bg-yellow-100 text-yellow-700'],
                'risco'       => ['label' => 'Risco',       'class' => 'bg-red-100 text-red-700'],
                default       => ['label' => 'Novo',        'class' => 'bg-gray-100 text-gray-600'],
            };
        @endphp
        <span class="text-xs font-medium px-2.5 py-1 rounded-full flex-shrink-0 {{ $scoreBadge['class'] }}">
            {{ $scoreBadge['label'] }} · {{ $cliente->score_atual }} pts
        </span>
    </div>

    {{-- Cards de resumo --}}
    @php
        $todasParcelas   = $cliente->cobrancas->flatMap->parcelas;
        $totalParcelas   = $todasParcelas->count();
        $pagas           = $todasParcelas->where('status', 'pago')->count();
        $emAtraso        = $todasParcelas->filter(fn($p) => $p->status === 'atrasado' || ($p->status === 'pendente' && $p->vencimento->lt(today())))->count();
        $valorTotal      = $todasParcelas->sum('valor');
        $valorPago       = $todasParcelas->where('status', 'pago')->sum('valor');
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Cobranças</p>
            <p class="text-2xl font-bold text-gray-900">{{ $cliente->cobrancas->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Parcelas pagas</p>
            <p class="text-2xl font-bold text-green-600">{{ $pagas }}<span class="text-sm font-normal text-gray-400">/{{ $totalParcelas }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Em atraso</p>
            <p class="text-2xl font-bold {{ $emAtraso > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $emAtraso }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Valor pago</p>
            <p class="text-2xl font-bold text-gray-900">R$ {{ number_format($valorPago, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-400">de R$ {{ number_format($valorTotal, 2, ',', '.') }}</p>
        </div>
    </div>

    {{-- Grid principal --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

        {{-- Cobranças (col 2/3) com scroll --}}
        <div class="lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">
                Cobranças
                <span class="font-normal text-gray-400">({{ $cliente->cobrancas->count() }})</span>
            </h2>

            <div class="space-y-3">
                @forelse($cliente->cobrancas->sortByDesc('created_at') as $cobranca)
                    @php
                        $totalParcCobranca  = $cobranca->parcelas->count();
                        $pagasCobranca      = $cobranca->parcelas->where('status', 'pago')->count();
                        $temAtraso          = $cobranca->parcelas->contains(fn($p) => $p->status === 'atrasado' || ($p->status === 'pendente' && $p->vencimento->lt(today())));
                        $statusCobranca     = match(true) {
                            $temAtraso => ['label' => 'Em atraso', 'class' => 'bg-red-100 text-red-700'],
                            $pagasCobranca === $totalParcCobranca && $totalParcCobranca > 0 => ['label' => 'Quitada', 'class' => 'bg-green-100 text-green-700'],
                            default => ['label' => 'Em aberto', 'class' => 'bg-yellow-100 text-yellow-700'],
                        };
                        $msgCobranca = $cobranca->parcelas->flatMap->logMensagens->sortByDesc('enviado_em');
                    @endphp

                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ open: false }">
                        {{-- Header clicável --}}
                        <button @click="open = !open"
                            class="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-gray-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $cobranca->descricao }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <p class="text-xs text-gray-400">
                                        {{ $pagasCobranca }}/{{ $totalParcCobranca }} parcelas ·
                                        R$ {{ number_format($cobranca->valor_total, 2, ',', '.') }}
                                    </p>
                                    @if($msgCobranca->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                            </svg>
                                            {{ $msgCobranca->count() }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusCobranca['class'] }}">
                                    {{ $statusCobranca['label'] }}
                                </span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>

                        {{-- Detalhes: parcelas + mensagens --}}
                        <div x-show="open" x-transition class="border-t border-gray-100 divide-y divide-gray-50">

                            {{-- Parcelas --}}
                            @foreach($cobranca->parcelas->sortBy('numero') as $parcela)
                                @php
                                    $parcelaStatus = $parcela->status;
                                    $vencida    = $parcelaStatus === 'pendente' && $parcela->vencimento->lt(today());
                                    $statusInfo = match(true) {
                                        $parcelaStatus === 'pago'                 => ['dot' => 'bg-green-500', 'label' => 'Pago',      'valor' => 'text-green-700'],
                                        $parcelaStatus === 'atrasado' || $vencida => ['dot' => 'bg-red-500',   'label' => 'Atrasado',  'valor' => 'text-red-700'],
                                        default                                   => ['dot' => 'bg-gray-300',  'label' => 'Em aberto', 'valor' => 'text-gray-700'],
                                    };
                                @endphp
                                <div class="flex items-start gap-3 px-5 py-3">
                                    <div class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5 {{ $statusInfo['dot'] }}"></div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm text-gray-700">Parcela {{ $parcela->numero }}/{{ $totalParcCobranca }}</span>
                                        @if($parcela->origem === 'manual')
                                            <span class="ml-1.5 inline-flex items-center px-1 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-600">manual</span>
                                        @endif
                                        <span class="text-xs text-gray-400 ml-2">
                                            vence {{ $parcela->vencimento->format('d/m/Y') }}
                                            @if($parcela->data_pagamento)
                                                · pago em {{ $parcela->data_pagamento->format('d/m/Y') }}
                                            @endif
                                        </span>
                                        @if($parcela->codigo_boleto)
                                            <p class="text-xs text-gray-400 font-mono mt-0.5 break-all">{{ $parcela->codigo_boleto }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-sm font-semibold {{ $statusInfo['valor'] }}">
                                            R$ {{ number_format($parcela->valor, 2, ',', '.') }}
                                        </p>
                                        <p class="text-xs text-gray-400">{{ $statusInfo['label'] }}</p>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Mensagens desta cobrança --}}
                            @if($msgCobranca->isNotEmpty())
                                <div class="bg-gray-50 px-5 py-2.5">
                                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Notificações enviadas</p>
                                    <div class="space-y-1.5">
                                        @foreach($msgCobranca as $msg)
                                            @php
                                                $tipoLabel = match($msg->tipo) {
                                                    'lembrete_antes'        => 'Lembrete antes',
                                                    'lembrete_dia'          => 'No dia',
                                                    'aviso_atraso'          => 'Atraso',
                                                    'confirmacao_pagamento' => 'Confirmação',
                                                };
                                                $tipoClass = match($msg->tipo) {
                                                    'lembrete_antes', 'lembrete_dia' => 'bg-blue-100 text-blue-700',
                                                    'aviso_atraso'                   => 'bg-red-100 text-red-700',
                                                    'confirmacao_pagamento'          => 'bg-green-100 text-green-700',
                                                };
                                            @endphp
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $msg->status === 'enviado' ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $tipoClass }}">
                                                        {{ $tipoLabel }}
                                                    </span>
                                                    <span class="text-xs text-gray-400 ml-auto">{{ $msg->enviado_em->format('d/m/Y H:i') }}</span>
                                                </div>
                                                @if($msg->status === 'enviado')
                                                    <p class="text-xs text-gray-500 pl-3.5 whitespace-pre-line">{{ $msg->mensagem }}</p>
                                                @else
                                                    <p class="text-xs text-red-500 pl-3.5">Falha ao enviar: {{ $msg->erro_detalhes ?? 'erro desconhecido' }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                @empty
                    <div class="bg-white rounded-xl border border-dashed border-gray-200 p-8 text-center">
                        <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="text-sm text-gray-400">Nenhuma cobrança registrada</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Sidebar: Score (col 1/3) --}}
        <div class="space-y-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Score</h2>

                <div class="bg-white rounded-xl border border-gray-200 p-5 mb-3">
                    @php
                        $score      = max(0, min(100, $cliente->score_atual));
                        $scoreColor = match(true) {
                            $score >= 70 => ['bar' => 'bg-green-500',  'text' => 'text-green-600'],
                            $score >= 40 => ['bar' => 'bg-yellow-400', 'text' => 'text-yellow-600'],
                            default      => ['bar' => 'bg-red-500',    'text' => 'text-red-600'],
                        };
                    @endphp
                    <div class="flex items-end justify-between mb-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-0.5">Pontuação atual</p>
                            <p class="text-3xl font-bold {{ $scoreColor['text'] }}">{{ $cliente->score_atual }}</p>
                        </div>
                        <p class="text-xs text-gray-400 mb-1">/ 100</p>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full transition-all {{ $scoreColor['bar'] }}" style="width: {{ $score }}%"></div>
                    </div>
                    <div class="flex justify-between mt-1.5">
                        <span class="text-xs text-gray-400">Risco</span>
                        <span class="text-xs text-gray-400">Bom pagador</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-900">Histórico de pontos</p>
                    </div>
                    @if($cliente->scoreHistorico->isEmpty())
                        <div class="px-5 py-6 text-center">
                            <p class="text-xs text-gray-400">Nenhuma movimentação ainda.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-50">
                            @foreach($cliente->scoreHistorico as $historico)
                                <div class="flex items-start gap-3 px-5 py-3">
                                    <span class="text-sm font-bold flex-shrink-0 w-12 text-right {{ $historico->pontos_aplicados >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $historico->pontos_aplicados > 0 ? '+' : '' }}{{ $historico->pontos_aplicados }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-gray-700 truncate">
                                            @if($historico->parcela?->cobranca)
                                                {{ $historico->parcela->cobranca->descricao }}
                                                · parc. {{ $historico->parcela->numero }}
                                            @else
                                                Ajuste manual
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-400">{{ $historico->created_at->format('d/m/Y') }} · resultado: {{ $historico->score_resultante }} pts</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

</div>
