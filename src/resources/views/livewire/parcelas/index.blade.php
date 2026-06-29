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
            ->when($this->status === 'atrasada', fn($q) => $q->where('status', 'pendente')->whereDate('vencimento', '<', now()->toDateString()))
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

<div>
    {{-- Cabeçalho --}}
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Parcelas</h1>
        <p class="text-sm text-gray-500 mt-0.5">Todas as parcelas das suas cobranças</p>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <select wire:model.live="clienteId"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
            <option value="">Todos os clientes</option>
            @foreach($clientes as $cliente)
                <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
            @endforeach
        </select>

        <select wire:model.live="status"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
            <option value="">Todos os status</option>
            <option value="pendente">Pendente</option>
            <option value="atrasada">Atrasada</option>
            <option value="pago">Pago</option>
            <option value="cancelado">Cancelado</option>
        </select>

        <select wire:model.live="periodo"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
            <option value="">Qualquer vencimento</option>
            <option value="hoje">Vence hoje</option>
            <option value="semana">Esta semana</option>
            <option value="mes">Este mês</option>
        </select>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cliente</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Cobrança</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-12">#</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Vencimento</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Valor</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Pago em</th>
                        <th class="px-4 py-3 w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($parcelas as $parcela)
                        @php
                            $vencida = $parcela->status === 'pendente' && $parcela->vencimento->isPast();
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
                            <td class="px-4 py-3 text-gray-600 max-w-[180px] truncate">
                                <a href="{{ route('cobrancas.show', $parcela->cobranca_id) }}" wire:navigate
                                    class="hover:text-indigo-600 transition-colors">
                                    {{ $parcela->cobranca->descricao }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3 text-gray-500 text-sm">
                                {{ $parcela->data_pagamento ? $parcela->data_pagamento->format('d/m/Y') : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($parcela->status === 'pendente')
                                    <button wire:click="marcarPago({{ $parcela->id }})"
                                        wire:confirm="Confirmar pagamento desta parcela?"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="Marcar como pago">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                @endif
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
        </div>

        @if($parcelas->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $parcelas->links() }}
            </div>
        @endif
    </div>
</div>
