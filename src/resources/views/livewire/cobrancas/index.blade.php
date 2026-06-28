<?php

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Services\CobrancaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $busca = '';
    public string $tipo  = '';

    public bool $showModal = false;

    public string $cliente_id              = '';
    public string $descricao               = '';
    public string $tipo_form               = 'avulsa';
    public string $valor_total             = '';
    public int    $numero_parcelas         = 1;
    public string $data_primeiro_vencimento = '';

    public function with(): array
    {
        return [
            'cobrancas' => (new CobrancaService)->listar($this->busca, $this->tipo),
            'clientes'  => Cliente::where('empresa_id', Auth::user()->empresa_id)
                               ->orderBy('nome')
                               ->get(['id', 'nome']),
        ];
    }

    public function updatedBusca(): void { $this->resetPage(); }
    public function updatedTipo(): void { $this->resetPage(); }

    public function updatedTipoForm(): void
    {
        $this->numero_parcelas = 1;
    }

    public function novo(): void
    {
        $this->reset(['cliente_id', 'descricao', 'valor_total', 'data_primeiro_vencimento']);
        $this->tipo_form       = 'avulsa';
        $this->numero_parcelas = 1;
        $this->showModal       = true;
    }

    public function salvar(): void
    {
        $dados = $this->validate([
            'cliente_id'               => ['required', 'exists:clientes,id'],
            'descricao'                => ['required', 'string', 'max:255'],
            'tipo_form'                => ['required', 'in:avulsa,recorrente'],
            'valor_total'              => ['required', 'numeric', 'min:0.01'],
            'numero_parcelas'          => ['required', 'integer', 'min:1', 'max:360'],
            'data_primeiro_vencimento' => ['required', 'date'],
        ], [], [
            'cliente_id'               => 'cliente',
            'tipo_form'                => 'tipo',
            'valor_total'              => 'valor total',
            'numero_parcelas'          => 'número de parcelas',
            'data_primeiro_vencimento' => 'data do 1º vencimento',
        ]);

        if ($dados['tipo_form'] === 'avulsa') {
            $dados['numero_parcelas'] = 1;
        }

        (new CobrancaService)->criar([
            'cliente_id'               => $dados['cliente_id'],
            'descricao'                => $dados['descricao'],
            'tipo'                     => $dados['tipo_form'],
            'valor_total'              => $dados['valor_total'],
            'numero_parcelas'          => $dados['numero_parcelas'],
            'data_primeiro_vencimento' => $dados['data_primeiro_vencimento'],
        ]);

        $this->showModal = false;
        $this->reset(['cliente_id', 'descricao', 'valor_total', 'data_primeiro_vencimento']);
        $this->tipo_form       = 'avulsa';
        $this->numero_parcelas = 1;
    }

    public function cancelar(int $id): void
    {
        $cobranca = Cobranca::where('empresa_id', Auth::user()->empresa_id)->findOrFail($id);
        (new CobrancaService)->cancelar($cobranca);
    }
}; ?>

<div>
    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Cobranças</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie as cobranças da sua empresa</p>
        </div>
        <a href="{{ route('cobrancas.create') }}" wire:navigate
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors shadow-sm w-full sm:w-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nova Cobrança
        </a>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input wire:model.live.debounce.300ms="busca" type="text" placeholder="Buscar por cliente..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" />
        </div>
        <select wire:model.live="tipo"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
            <option value="">Todos os tipos</option>
            <option value="avulsa">Avulsa</option>
            <option value="recorrente">Recorrente</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Cliente</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Descrição</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Tipo</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Valor</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Parcelas</th>
                        <th class="px-4 py-3 w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($cobrancas as $cobranca)
                        <tr class="hover:bg-gray-50 transition-colors cursor-pointer" wire:navigate
                            onclick="window.location='{{ route('cobrancas.show', $cobranca) }}'">
                            <td class="px-4 py-3.5 font-medium text-gray-900">{{ $cobranca->cliente->nome }}</td>
                            <td class="px-4 py-3.5 text-gray-600 max-w-[200px] truncate">{{ $cobranca->descricao }}</td>
                            <td class="px-4 py-3.5">
                                @if($cobranca->tipo === 'avulsa')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Avulsa</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Recorrente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-gray-700 font-medium">
                                R$ {{ number_format($cobranca->valor_total, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-gray-500">
                                @php
                                    $pagas    = $cobranca->parcelas->where('status', 'pago')->count();
                                    $total    = $cobranca->parcelas->count();
                                    $atrasada = $cobranca->parcelas->where('status', 'atrasado')->count();
                                @endphp
                                <span class="{{ $atrasada > 0 ? 'text-red-600 font-medium' : '' }}">
                                    {{ $pagas }}/{{ $total }} pagas
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <button wire:click="cancelar({{ $cobranca->id }})"
                                    wire:confirm="Cancelar as parcelas pendentes desta cobrança?"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Cancelar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm text-gray-400">Nenhuma cobrança encontrada.</p>
                                <button wire:click="novo" class="mt-3 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                    Criar primeira cobrança →
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cobrancas->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $cobrancas->links() }}
            </div>
        @endif
    </div>

    {{-- Modal nova cobrança --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">Nova Cobrança</h2>

                <form wire:submit="salvar" class="space-y-4">

                    <div>
                        <x-input-label for="cliente_id" value="Cliente *" />
                        <select wire:model="cliente_id" id="cliente_id"
                            class="block mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                            <option value="">Selecione um cliente</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('cliente_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="descricao" value="Descrição *" />
                        <x-text-input wire:model="descricao" id="descricao" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('descricao')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="tipo_form" value="Tipo *" />
                            <select wire:model.live="tipo_form" id="tipo_form"
                                class="block mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                <option value="avulsa">Avulsa</option>
                                <option value="recorrente">Recorrente</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="valor_total" value="Valor total (R$) *" />
                            <x-text-input wire:model="valor_total" id="valor_total" class="block mt-1 w-full" type="number" step="0.01" min="0.01" required />
                            <x-input-error :messages="$errors->get('valor_total')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        @if($tipo_form === 'recorrente')
                            <div>
                                <x-input-label for="numero_parcelas" value="Nº de parcelas *" />
                                <x-text-input wire:model="numero_parcelas" id="numero_parcelas" class="block mt-1 w-full" type="number" min="2" max="360" required />
                                <x-input-error :messages="$errors->get('numero_parcelas')" class="mt-1" />
                            </div>
                        @else
                            <div>
                                <x-input-label value="Nº de parcelas" />
                                <x-text-input value="1" class="block mt-1 w-full bg-gray-50" type="number" disabled />
                            </div>
                        @endif
                        <div>
                            <x-input-label for="data_primeiro_vencimento" value="1º vencimento *" />
                            <x-text-input wire:model="data_primeiro_vencimento" id="data_primeiro_vencimento" class="block mt-1 w-full" type="date" required />
                            <x-input-error :messages="$errors->get('data_primeiro_vencimento')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            Criar cobrança
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
