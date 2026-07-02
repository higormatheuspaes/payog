<?php

use App\Models\Cliente;
use App\Models\Parcela;
use App\Services\ScoreService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $status    = '';
    public string $periodo   = '';
    public string $clienteId = '';

    public function with(): array
    {
        $empresaId = Auth::user()->empresa_id;

        $query = Parcela::with(['cobranca.cliente'])
            ->whereHas('cobranca', fn($q) => $q->where('empresa_id', $empresaId))
            ->when($this->status === 'atrasada', fn($q) => $q->where(fn($q) => $q->where('status', 'atrasado')->orWhere(fn($q) => $q->where('status', 'pendente')->whereDate('vencimento', '<', today()))))
            ->when($this->status && $this->status !== 'atrasada', fn($q) => $q->where('status', $this->status))
            ->when($this->clienteId, fn($q) => $q->whereHas('cobranca', fn($q) =>
                $q->where('cliente_id', $this->clienteId)
            ))
            ->when($this->periodo === 'hoje',      fn($q) => $q->whereDate('vencimento', now()->toDateString()))
            ->when($this->periodo === 'semana',    fn($q) => $q->whereBetween('vencimento', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]))
            ->when($this->periodo === 'mes',       fn($q) => $q->whereBetween('vencimento', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]))
;

        return [
            'parcelas' => $query->orderBy('vencimento')->paginate(20),
            'clientes' => Cliente::where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']),
        ];
    }

    public function updatedStatus(): void    { $this->resetPage(); }
    public function updatedPeriodo(): void   { $this->resetPage(); }
    public function updatedClienteId(): void { $this->resetPage(); }

    public ?int $editandoId     = null;
    public string $codigoBoleto = '';
    public bool $showModal      = false;

    public function editarCodigo(int $parcelaId): void
    {
        $parcela = Parcela::whereHas('cobranca', fn($q) =>
            $q->where('empresa_id', Auth::user()->empresa_id)
        )->findOrFail($parcelaId);

        $this->editandoId   = $parcelaId;
        $this->codigoBoleto = $parcela->codigo_boleto ?? '';
        $this->showModal    = true;
    }

    public function salvarCodigo(): void
    {
        $parcela = Parcela::whereHas('cobranca', fn($q) =>
            $q->where('empresa_id', Auth::user()->empresa_id)
        )->findOrFail($this->editandoId);

        $parcela->update(['codigo_boleto' => $this->codigoBoleto ?: null]);
        $this->showModal = false;
        $this->reset(['editandoId', 'codigoBoleto']);
    }

    public function marcarPago(int $parcelaId): void
    {
        $parcela = Parcela::whereHas('cobranca', fn($q) =>
            $q->where('empresa_id', Auth::user()->empresa_id)
        )->findOrFail($parcelaId);

        $parcela->update([
            'status'         => 'pago',
            'data_pagamento' => now()->toDateString(),
        ]);
        $parcela->refresh()->load('cobranca.cliente');
        (new ScoreService)->aplicarPagamento($parcela);
    }
}; ?>

<x-data-table :paginator="$parcelas">
    <x-slot:header>
        <h1 class="text-xl font-semibold text-gray-900">Parcelas</h1>
        <p class="text-sm text-gray-500 mt-0.5">Todas as parcelas das suas cobranças</p>
    </x-slot:header>

    <x-slot:filters>
        <div class="flex flex-col sm:flex-row gap-2">
            <select wire:model.live="clienteId"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
                <option value="">Todos os clientes</option>
                @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
                @endforeach
            </select>

            <select wire:model.live="status"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
                <option value="">Todos os status</option>
                <option value="pendente">Pendente</option>
                <option value="atrasada">Atrasada</option>
                <option value="pago">Pago</option>
                <option value="cancelado">Cancelado</option>
            </select>

            <select wire:model.live="periodo"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
                <option value="">Qualquer vencimento</option>
                <option value="hoje">Vence hoje</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mês</option>
            </select>
        </div>
    </x-slot:filters>

    <table class="w-full table-fixed text-sm min-w-[460px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide hidden sm:table-cell">Cobrança</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[42px] hidden sm:table-cell">#</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[96px]">Vencimento</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[88px]">Valor</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[90px]">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[88px] hidden sm:table-cell">Pago em</th>
                        <th class="px-4 py-3 w-[56px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($parcelas as $parcela)
                        @php
                            $vencida = $parcela->status === 'atrasado' || ($parcela->status === 'pendente' && $parcela->vencimento->lt(today()));
                            $statusClass = match($parcela->status) {
                                'pago'      => 'bg-green-100 text-green-700',
                                'atrasado'  => 'bg-red-100 text-red-700',
                                'cancelado' => 'bg-gray-100 text-gray-500',
                                default     => $vencida ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700',
                            };
                            $statusLabel = match($parcela->status) {
                                'pago'      => 'Pago',
                                'atrasado'  => 'Atrasado',
                                'cancelado' => 'Cancelado',
                                default     => $vencida ? 'Atrasada' : 'Pendente',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ $parcela->cobranca->cliente->nome }}
                            </td>
                            <td class="px-4 py-3 text-gray-600 truncate hidden sm:table-cell">
                                <a href="{{ route('cobrancas.show', $parcela->cobranca_id) }}" wire:navigate
                                    class="hover:text-indigo-600 transition-colors">
                                    {{ $parcela->cobranca->descricao }}
                                </a>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-medium">
                                    {{ $parcela->numero }}
                                </span>
                            </td>
                            <td class="px-4 py-3 {{ $vencida ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                {{ $parcela->vencimento->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-medium">
                                R$ {{ number_format($parcela->valor, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-sm hidden sm:table-cell">
                                {{ $parcela->data_pagamento ? $parcela->data_pagamento->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button wire:click="editarCodigo({{ $parcela->id }})"
                                        class="p-1.5 rounded-lg transition-colors {{ $parcela->codigo_boleto ? 'text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50' : 'text-gray-400 hover:text-indigo-600 hover:bg-indigo-50' }}"
                                        title="{{ $parcela->codigo_boleto ? 'Editar código do boleto' : 'Adicionar código do boleto' }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="square" stroke-width="1.5" d="M4 5v14M7 5v14M9 5v14M12 5v14M14 5v14M17 5v14M20 5v14" />
                                        </svg>
                                    </button>
                                    @if($parcela->status === 'pendente' || $parcela->status === 'atrasado')
                                        <button wire:click="marcarPago({{ $parcela->id }})"
                                            wire:confirm="Confirmar pagamento desta parcela?"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="Marcar como pago">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-sm text-gray-400">Nenhuma parcela encontrada.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

    <x-slot:modal>
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Código do boleto / referência</h2>
                <p class="text-sm text-gray-500 mb-4">Será incluído automaticamente nas notificações enviadas ao cliente.</p>

                <input wire:model="codigoBoleto" type="text"
                    placeholder="Ex: 12345.67890 12345.678901 12345.678901 1 12340000010000"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    autofocus />

                <div class="flex justify-end gap-3 mt-5">
                    <button wire:click="$set('showModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button wire:click="salvarCodigo"
                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    @endif
    </x-slot:modal>
</x-data-table>
