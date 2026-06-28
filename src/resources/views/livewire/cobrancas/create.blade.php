<?php

use App\Models\Cliente;
use App\Services\CobrancaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $cliente_id               = '';
    public string $descricao                = '';
    public string $tipo                     = 'avulsa';
    public string $valor_total              = '';
    public string $numero_parcelas          = '1';
    public string $data_primeiro_vencimento = '';

    public array $parcelasPreview = [];

    public function with(): array
    {
        return [
            'clientes' => Cliente::where('empresa_id', Auth::user()->empresa_id)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ];
    }

    public function updatedValorTotal(): void             { $this->calcularPreview(); }
    public function updatedNumeroParcelas(): void         { $this->calcularPreview(); }
    public function updatedDataPrimeiroVencimento(): void { $this->calcularPreview(); }

    public function updatedTipo(): void
    {
        $this->numero_parcelas = '1';
        $this->calcularPreview();
    }

    private function calcularPreview(): void
    {
        $valor = (float) $this->valor_total;
        $n     = $this->tipo === 'avulsa' ? 1 : max(1, (int) $this->numero_parcelas);

        if ($valor <= 0 || !$this->data_primeiro_vencimento) {
            $this->parcelasPreview = [];
            return;
        }

        $valorParcela = round($valor / $n, 2);
        $totalGerado  = $valorParcela * ($n - 1);
        $ultimoValor  = round($valor - $totalGerado, 2);

        $vencimento = Carbon::parse($this->data_primeiro_vencimento);
        $preview    = [];

        for ($i = 1; $i <= $n; $i++) {
            $preview[] = [
                'numero'     => $i,
                'valor'      => $i === $n ? $ultimoValor : $valorParcela,
                'vencimento' => $vencimento->format('Y-m-d'),
            ];
            $vencimento->addMonth();
        }

        $this->parcelasPreview = $preview;
    }

    public function salvar(): void
    {
        $this->validate([
            'cliente_id'               => ['required', 'exists:clientes,id'],
            'descricao'                => ['required', 'string', 'max:255'],
            'tipo'                     => ['required', 'in:avulsa,recorrente'],
            'valor_total'              => ['required', 'numeric', 'min:0.01'],
            'numero_parcelas'          => ['required', 'integer', 'min:1', 'max:360'],
            'data_primeiro_vencimento' => ['required', 'date'],
        ], [], [
            'cliente_id'               => 'cliente',
            'valor_total'              => 'valor total',
            'numero_parcelas'          => 'número de parcelas',
            'data_primeiro_vencimento' => 'data do 1º vencimento',
        ]);

        if (empty($this->parcelasPreview)) {
            $this->calcularPreview();
        }

        $cobranca = (new CobrancaService)->criar([
            'cliente_id'               => $this->cliente_id,
            'descricao'                => $this->descricao,
            'tipo'                     => $this->tipo,
            'valor_total'              => $this->valor_total,
            'numero_parcelas'          => $this->tipo === 'avulsa' ? 1 : (int) $this->numero_parcelas,
            'data_primeiro_vencimento' => $this->data_primeiro_vencimento,
            'parcelas'                 => $this->parcelasPreview,
        ]);

        $this->redirect(route('cobrancas.show', $cobranca), navigate: true);
    }
}; ?>

<div class="max-w-4xl mx-auto">

    {{-- Cabeçalho --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('cobrancas.index') }}" wire:navigate
            class="p-2 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Nova Cobrança</h1>
            <p class="text-sm text-gray-500 mt-0.5">Preencha os dados e ajuste as parcelas antes de confirmar</p>
        </div>
    </div>

    <form wire:submit="salvar" class="space-y-6">

        {{-- Dados da cobrança --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Dados da cobrança</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                <div class="sm:col-span-2">
                    <x-input-label for="cliente_id" value="Cliente *" />
                    <select wire:model="cliente_id" id="cliente_id"
                        class="block mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Selecione um cliente</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="descricao" value="Descrição *" />
                    <x-text-input wire:model="descricao" id="descricao" class="block mt-1 w-full" type="text" placeholder="Ex: Mensalidade academia" required />
                    <x-input-error :messages="$errors->get('descricao')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="tipo" value="Tipo *" />
                    <select wire:model.live="tipo" id="tipo"
                        class="block mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="avulsa">Avulsa (única)</option>
                        <option value="recorrente">Recorrente (parcelada)</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="valor_total" value="Valor total (R$) *" />
                    <x-text-input wire:model.live.debounce.500ms="valor_total" id="valor_total"
                        class="block mt-1 w-full" type="number" step="0.01" min="0.01"
                        placeholder="0,00" required />
                    <x-input-error :messages="$errors->get('valor_total')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="numero_parcelas" value="Nº de parcelas *" />
                    @if($tipo === 'avulsa')
                        <x-text-input value="1" class="block mt-1 w-full bg-gray-50 text-gray-400" type="number" disabled />
                    @else
                        <x-text-input wire:model.live.debounce.500ms="numero_parcelas" id="numero_parcelas"
                            class="block mt-1 w-full" type="number" min="2" max="360" required />
                        <x-input-error :messages="$errors->get('numero_parcelas')" class="mt-1" />
                    @endif
                </div>

                <div>
                    <x-input-label for="data_primeiro_vencimento" value="Data do 1º vencimento *" />
                    <x-text-input wire:model.live="data_primeiro_vencimento" id="data_primeiro_vencimento"
                        class="block mt-1 w-full" type="date" required />
                    <x-input-error :messages="$errors->get('data_primeiro_vencimento')" class="mt-1" />
                </div>

            </div>
        </div>

        {{-- Preview de parcelas --}}
        @if(!empty($parcelasPreview))
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Parcelas</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Ajuste valores e datas conforme necessário</p>
                    </div>
                    <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full">
                        {{ count($parcelasPreview) }} {{ count($parcelasPreview) === 1 ? 'parcela' : 'parcelas' }}
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-16">#</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Vencimento</th>
                                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Valor (R$)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($parcelasPreview as $i => $parcela)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-medium">
                                            {{ $parcela['numero'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input wire:model="parcelasPreview.{{ $i }}.vencimento" type="date"
                                            class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full max-w-[160px]" />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-1.5 max-w-[160px]">
                                            <span class="text-sm text-gray-400 flex-shrink-0">R$</span>
                                            <input wire:model="parcelasPreview.{{ $i }}.valor" type="number" step="0.01" min="0.01"
                                                class="border border-gray-200 rounded-lg px-2.5 py-1.5 text-sm text-gray-700 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-sm font-medium text-gray-600">Total</td>
                                <td class="px-4 py-3">
                                    <span class="text-gray-400 text-sm mr-1">R$</span>
                                    <span class="text-sm font-semibold text-gray-900">{{ number_format(collect($parcelasPreview)->sum('valor'), 2, ',', '.') }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl border border-dashed border-gray-300 p-10 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-sm text-gray-400">Preencha o valor e a data de vencimento para ver o preview das parcelas</p>
            </div>
        @endif

        {{-- Ações --}}
        <div class="flex items-center justify-end gap-3 pb-6">
            <a href="{{ route('cobrancas.index') }}" wire:navigate
                class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit" @disabled(empty($parcelasPreview))
                class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm">
                Criar cobrança
            </button>
        </div>

    </form>

</div>
