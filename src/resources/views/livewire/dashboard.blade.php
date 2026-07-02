<?php

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Parcela;
use App\Services\ConsumoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component
{
    public int     $periodoMeses = 6;
    public ?string $dataInicio   = null;
    public ?string $dataFim      = null;

    public function setPeriodo(int $meses): void
    {
        $this->periodoMeses = $meses;
        $this->dataInicio   = null;
        $this->dataFim      = null;
    }

    #[\Livewire\Attributes\Renderless]
    public function fetchChartData(string $dataInicio = '', string $dataFim = ''): array
    {
        $empresaId = Auth::user()->empresa_id;
        $useCustom = $dataInicio && $dataFim;
        $inicio    = $useCustom ? Carbon::parse($dataInicio)->startOfDay()  : now()->subMonths($this->periodoMeses - 1)->startOfMonth();
        $fim       = $useCustom ? Carbon::parse($dataFim)->endOfDay()       : now()->endOfMonth();
        $numMeses  = (int) $inicio->copy()->startOfMonth()->diffInMonths($fim->copy()->startOfMonth()) + 1;

        $bd = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where('status', 'pago')
            ->whereDate('data_pagamento', '>=', $inicio)
            ->whereDate('data_pagamento', '<=', $fim)
            ->selectRaw('DATE_FORMAT(data_pagamento, "%Y-%m") as mes, SUM(valor) as total')
            ->groupBy('mes')->get()->keyBy('mes');

        $labels  = [];
        $valores = [];
        for ($i = 0; $i < $numMeses; $i++) {
            $data     = $inicio->copy()->addMonths($i);
            $labels[] = mb_strtoupper($data->translatedFormat('M/y'));
            $valores[] = (float) ($bd[$data->format('Y-m')]->total ?? 0);
        }

        return compact('labels', 'valores');
    }

    public function with(): array
    {
        $empresa   = Auth::user()->empresa->load('plano');
        $empresaId = $empresa->id;

        $totalClientes = Cliente::where('empresa_id', $empresaId)->count();

        $valorAReceber = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where('status', 'pendente')
            ->whereDate('vencimento', '>=', now())
            ->whereDate('vencimento', '<=', now()->addDays(30))
            ->sum('valor');

        $totalAtrasadas = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where(fn($q) => $q->where('status', 'atrasado')
                ->orWhere(fn($q) => $q->where('status', 'pendente')->whereDate('vencimento', '<', today())))
            ->count();

        $valorAtrasado = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where(fn($q) => $q->where('status', 'atrasado')
                ->orWhere(fn($q) => $q->where('status', 'pendente')->whereDate('vencimento', '<', today())))
            ->sum('valor');

        $recebidoMes = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where('status', 'pago')
            ->whereMonth('data_pagamento', now()->month)
            ->whereYear('data_pagamento', now()->year)
            ->sum('valor');

        $proximasVencer = Parcela::with(['cobranca.cliente'])
            ->whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where('status', 'pendente')
            ->whereDate('vencimento', '>=', now())
            ->whereDate('vencimento', '<=', now()->addDays(30))
            ->orderBy('vencimento')
            ->limit(20)
            ->get();

        $ultimasAtrasadas = Parcela::with(['cobranca.cliente'])
            ->whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where(fn($q) => $q->where('status', 'atrasado')
                ->orWhere(fn($q) => $q->where('status', 'pendente')->whereDate('vencimento', '<', today())))
            ->orderBy('vencimento')
            ->limit(20)
            ->get();

        // Recebimentos — período customizado ou janela em meses
        $useCustom  = $this->dataInicio && $this->dataFim;
        $inicio     = $useCustom ? Carbon::parse($this->dataInicio)->startOfDay()  : now()->subMonths($this->periodoMeses - 1)->startOfMonth();
        $fim        = $useCustom ? Carbon::parse($this->dataFim)->endOfDay()       : now()->endOfMonth();
        $numMeses   = (int) $inicio->copy()->startOfMonth()->diffInMonths($fim->copy()->startOfMonth()) + 1;

        $recebimentosBd = Parcela::whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->where('status', 'pago')
            ->whereDate('data_pagamento', '>=', $inicio)
            ->whereDate('data_pagamento', '<=', $fim)
            ->selectRaw('DATE_FORMAT(data_pagamento, "%Y-%m") as mes, SUM(valor) as total')
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $mesesLabels  = [];
        $mesesValores = [];
        for ($i = 0; $i < $numMeses; $i++) {
            $data = $inicio->copy()->addMonths($i);
            $mesesLabels[]  = mb_strtoupper($data->translatedFormat('M/y'));
            $mesesValores[] = (float) ($recebimentosBd[$data->format('Y-m')]->total ?? 0);
        }

        // Distribuição de score
        $scoreDist = Cliente::where('empresa_id', $empresaId)
            ->selectRaw('score_categoria, COUNT(*) as total')
            ->groupBy('score_categoria')
            ->get()
            ->keyBy('score_categoria');

        $scoreLabels  = ['Bom pagador', 'Atenção', 'Risco'];
        $scoreValores = [
            (int) ($scoreDist['bom_pagador']->total ?? 0),
            (int) ($scoreDist['atencao']->total ?? 0),
            (int) ($scoreDist['risco']->total ?? 0),
        ];

        $consumo         = (new ConsumoService)->consumoAtual($empresa);
        $limiteMensagens = $empresa->plano?->limite_mensagens_mes ?? 0;
        $percentualConsumo = $limiteMensagens > 0
            ? min(100, (int) round(($consumo->mensagens_enviadas / $limiteMensagens) * 100))
            : 0;

        return compact(
            'totalClientes',
            'valorAReceber',
            'totalAtrasadas',
            'valorAtrasado',
            'recebidoMes',
            'proximasVencer',
            'ultimasAtrasadas',
            'mesesLabels',
            'mesesValores',
            'scoreLabels',
            'scoreValores',
            'consumo',
            'limiteMensagens',
            'percentualConsumo',
        );
    }
}; ?>

<div class="space-y-4">

    {{-- Saudação --}}
    <div>
        <h1 class="text-xl font-semibold text-gray-900">Olá, {{ Auth::user()->name }} 👋</h1>
        <p class="text-sm text-gray-500 mt-0.5">Aqui está o resumo da sua operação hoje.</p>
    </div>

    {{-- Cards de métricas --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Clientes</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalClientes }}</p>
            <a href="{{ route('clientes.index') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-700 mt-2 inline-block">Ver todos →</a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">A receber</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">R$ {{ number_format($valorAReceber, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-400 mt-2">próximos 30 dias</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 {{ $totalAtrasadas > 0 ? 'border-red-200 bg-red-50' : '' }}">
            <p class="text-xs font-medium {{ $totalAtrasadas > 0 ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wide">Atrasadas</p>
            <p class="text-2xl font-bold {{ $totalAtrasadas > 0 ? 'text-red-600' : 'text-gray-900' }} mt-1">{{ $totalAtrasadas }}</p>
            @if($totalAtrasadas > 0)
                <p class="text-xs text-red-400 mt-2">R$ {{ number_format($valorAtrasado, 2, ',', '.') }} em aberto</p>
            @else
                <p class="text-xs text-gray-400 mt-2">Tudo em dia</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Recebido este mês</p>
            <p class="text-2xl font-bold text-green-600 mt-1">R$ {{ number_format($recebidoMes, 2, ',', '.') }}</p>
            <a href="{{ route('parcelas.index') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-700 mt-2 inline-block">Ver parcelas →</a>
        </div>

    </div>

    {{-- Consumo de mensagens --}}
    @if($limiteMensagens > 0)
    @php
        $emExcedente = $percentualConsumo >= 100 && ! $consumo->envios_pausados;

        if ($consumo->envios_pausados) {
            $borderClass = 'border-red-300';
            $barColor    = 'bg-red-500';
            $badgeClass  = 'bg-red-100 text-red-700';
            $badgeLabel  = 'Envios pausados';
            $pctClass    = 'text-red-600 font-semibold';
        } elseif ($emExcedente) {
            $borderClass = 'border-red-200';
            $barColor    = 'bg-red-400';
            $badgeClass  = 'bg-red-100 text-red-700';
            $badgeLabel  = 'Em excedente';
            $pctClass    = 'text-red-500 font-semibold';
        } elseif ($percentualConsumo >= 80) {
            $borderClass = 'border-yellow-200';
            $barColor    = 'bg-yellow-400';
            $badgeClass  = 'bg-yellow-100 text-yellow-700';
            $badgeLabel  = 'Atenção';
            $pctClass    = 'text-yellow-600 font-medium';
        } else {
            $borderClass = 'border-gray-200';
            $barColor    = 'bg-indigo-500';
            $badgeClass  = 'bg-green-100 text-green-700';
            $badgeLabel  = 'Normal';
            $pctClass    = 'text-gray-500';
        }
    @endphp
    <div class="bg-white rounded-xl border {{ $borderClass }} p-5">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Consumo de mensagens</h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ now()->translatedFormat('F Y') }}</p>
            </div>
            <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $badgeClass }}">{{ $badgeLabel }}</span>
        </div>

        <div class="flex items-center gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                    <span>{{ $consumo->mensagens_enviadas }} / {{ $limiteMensagens }} mensagens</span>
                    <span class="{{ $pctClass }}">{{ $percentualConsumo }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all {{ $barColor }}" style="width: {{ min(100, $percentualConsumo) }}%"></div>
                </div>
            </div>

            @if($consumo->mensagens_excedentes > 0)
            <div class="flex-shrink-0 text-right pl-4 border-l border-red-100">
                <p class="text-sm font-bold text-red-600">R$ {{ number_format($consumo->valor_excedente_acumulado, 2, ',', '.') }}</p>
                <p class="text-xs text-red-400 mt-0.5">{{ $consumo->mensagens_excedentes }} msg(s) excedentes</p>
            </div>
            @endif
        </div>

        {{-- Alertas --}}
        @if($consumo->envios_pausados)
            <div class="mt-3 flex items-start gap-2 p-3 rounded-lg bg-red-50 border border-red-100">
                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
                <p class="text-xs text-red-700">
                    <strong>Envios bloqueados.</strong> O teto de R$ {{ number_format($consumo->teto_gasto_excedente, 2, ',', '.') }} foi atingido. Nenhuma mensagem será enviada até o início do próximo ciclo.
                    <a href="{{ route('configuracoes.index') }}" wire:navigate class="underline ml-1">Ajustar limite →</a>
                </p>
            </div>
        @elseif($emExcedente)
            <div class="mt-3 flex items-start gap-2 p-3 rounded-lg bg-red-50 border border-red-100">
                <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs text-red-700">
                    <strong>Limite do plano atingido.</strong> Cada mensagem adicional está sendo cobrada a R$ 0,20.
                    @if($consumo->teto_gasto_excedente)
                        Teto configurado: R$ {{ number_format($consumo->teto_gasto_excedente, 2, ',', '.') }}.
                    @else
                        <a href="{{ route('configuracoes.index') }}" wire:navigate class="underline ml-1">Configurar teto de gasto →</a>
                    @endif
                </p>
            </div>
        @elseif($percentualConsumo >= 80)
            <div class="mt-3 flex items-start gap-2 p-3 rounded-lg bg-yellow-50 border border-yellow-100">
                <svg class="w-4 h-4 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs text-yellow-700">
                    <strong>Atenção:</strong> você já usou {{ $percentualConsumo }}% do limite de mensagens do seu plano este mês.
                </p>
            </div>
        @elseif($consumo->teto_gasto_excedente)
            <p class="text-xs text-gray-400 mt-2">Teto configurado: R$ {{ number_format($consumo->teto_gasto_excedente, 2, ',', '.') }}</p>
        @endif
    </div>
    @endif

    {{-- Tabelas lado a lado --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Atrasadas --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Parcelas atrasadas</h2>
                @if($ultimasAtrasadas->isNotEmpty())
                    <a href="{{ route('parcelas.index') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-700">Ver todas</a>
                @endif
            </div>

            @if($ultimasAtrasadas->isEmpty())
                <div class="px-5 py-10 text-center">
                    <svg class="w-8 h-8 text-green-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-gray-400">Nenhuma parcela atrasada</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 overflow-y-auto" style="max-height: 280px;">
                    @foreach($ultimasAtrasadas as $parcela)
                        <li class="px-5 py-3 flex items-center justify-between hover:bg-red-50 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $parcela->cobranca->cliente->nome }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ $parcela->cobranca->descricao }}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0 text-right">
                                <p class="text-sm font-semibold text-red-600">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</p>
                                <p class="text-xs text-red-400">{{ $parcela->vencimento->format('d/m/Y') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Próximas a vencer --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Próximas a vencer <span class="text-gray-400 font-normal">(30 dias)</span></h2>
                @if($proximasVencer->isNotEmpty())
                    <a href="{{ route('parcelas.index') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-700">Ver todas</a>
                @endif
            </div>

            @if($proximasVencer->isEmpty())
                <div class="px-5 py-10 text-center">
                    <p class="text-sm text-gray-400">Nenhuma parcela nos próximos 30 dias</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100 overflow-y-auto" style="max-height: 280px;">
                    @foreach($proximasVencer as $parcela)
                        @php $diasRestantes = (int) now()->startOfDay()->diffInDays($parcela->vencimento->startOfDay(), false); @endphp
                        <li class="px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $parcela->cobranca->cliente->nome }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ $parcela->cobranca->descricao }}</p>
                            </div>
                            <div class="ml-4 flex-shrink-0 text-right">
                                <p class="text-sm font-semibold text-gray-800">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</p>
                                <p class="text-xs {{ $diasRestantes <= 3 ? 'text-orange-500' : 'text-gray-400' }}">
                                    {{ $parcela->vencimento->format('d/m/Y') }}
                                    @if($diasRestantes === 0)
                                        · hoje
                                    @elseif($diasRestantes <= 3)
                                        · {{ $diasRestantes }}d
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Recebimentos por mês (2/3) --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 min-w-0"
            x-data="{
                chart: null,
                periodoAtivo: {{ $periodoMeses }},
                dataInicio: '',
                dataFim: '',

                init() {
                    const dark = document.documentElement.classList.contains('dark');
                    const gridColor = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
                    const tickColor = dark ? '#64748b' : '#9ca3af';

                    this.chart = new Chart($refs.barChart, {
                        type: 'bar',
                        data: {
                            labels: @js($mesesLabels),
                            datasets: [{
                                data: @js($mesesValores),
                                backgroundColor: 'rgba(99,102,241,0.75)',
                                hoverBackgroundColor: 'rgba(99,102,241,1)',
                                borderRadius: 6,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            aspectRatio: 3.5,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ' R$ ' + ctx.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 })
                                    }
                                }
                            },
                            scales: {
                                x: { grid: { color: gridColor }, ticks: { color: tickColor } },
                                y: {
                                    grid: { color: gridColor },
                                    ticks: { color: tickColor, callback: v => 'R$ ' + v.toLocaleString('pt-BR') }
                                }
                            }
                        }
                    });
                },

                async aplicarFiltro() {
                    const data = await $wire.fetchChartData(this.dataInicio, this.dataFim);
                    this.chart.data.labels = data.labels;
                    this.chart.data.datasets[0].data = data.valores;
                    this.chart.update();
                },

                async setPeriodoRapido(meses) {
                    this.periodoAtivo = meses;
                    this.dataInicio   = '';
                    this.dataFim      = '';
                    await $wire.setPeriodo(meses);
                    const data = await $wire.fetchChartData('', '');
                    this.chart.data.labels = data.labels;
                    this.chart.data.datasets[0].data = data.valores;
                    this.chart.update();
                },

                limpar() {
                    this.dataInicio = '';
                    this.dataFim    = '';
                    this.aplicarFiltro();
                }
            }">

            {{-- Cabeçalho com filtros --}}
            <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                <h2 class="text-sm font-semibold text-gray-700 mt-1">Recebimentos</h2>

                <div class="flex flex-wrap items-center gap-3">
                    {{-- Atalhos rápidos --}}
                    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-medium">
                        @foreach([3 => '3m', 6 => '6m', 12 => '12m'] as $val => $label)
                            <button @click="setPeriodoRapido({{ $val }})"
                                :class="!dataInicio && periodoAtivo === {{ $val }} ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                                class="px-3 py-1.5 transition-colors">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Range customizado --}}
                    <div class="flex items-center gap-2">
                        <input x-model="dataInicio" @change="dataFim && aplicarFiltro()" type="date"
                            class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-600 focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400">
                        <span class="text-xs text-gray-400">—</span>
                        <input x-model="dataFim" @change="dataInicio && aplicarFiltro()" type="date"
                            class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-600 focus:ring-1 focus:ring-indigo-400 focus:border-indigo-400">
                        <button x-show="dataInicio || dataFim" @click="limpar()"
                            class="w-6 h-6 flex items-center justify-center rounded-full text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors" title="Limpar">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <canvas x-ref="barChart"></canvas>
        </div>

        {{-- Score dos clientes (1/3) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 min-w-0"
            x-data="{
                init() {
                    const dark = document.documentElement.classList.contains('dark');
                    new Chart($refs.donutChart, {
                        type: 'doughnut',
                        data: {
                            labels: @js($scoreLabels),
                            datasets: [{
                                data: @js($scoreValores),
                                backgroundColor: ['#22c55e', '#eab308', '#ef4444'],
                                hoverBackgroundColor: ['#16a34a', '#ca8a04', '#dc2626'],
                                borderWidth: 0,
                                hoverOffset: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            aspectRatio: 1.5,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: dark ? '#f1f5f9' : '#374151',
                                        padding: 16,
                                        font: { size: 12 },
                                        usePointStyle: true,
                                        pointStyleWidth: 8,
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ' ' + ctx.parsed + ' clientes'
                                    }
                                }
                            }
                        }
                    });
                }
            }">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Score dos clientes</h2>
            <canvas x-ref="donutChart"></canvas>
        </div>

    </div>

</div>
