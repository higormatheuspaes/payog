<?php

use App\Models\Cobranca;
use App\Models\Parcela;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Cobranca $cobranca;

    public ?int    $editandoParcelaId  = null;
    public string  $editValor          = '';
    public string  $editVencimento     = '';

    public function mount(Cobranca $cobranca): void
    {
        abort_if($cobranca->empresa_id !== Auth::user()->empresa_id, 403);
        $this->cobranca = $cobranca->load(['cliente', 'parcelas' => fn($q) => $q->orderBy('numero')]);
    }

    public function with(): array
    {
        return [
            'parcelas' => $this->cobranca->parcelas->sortBy('numero'),
        ];
    }

    public function marcarPago(int $parcelaId): void
    {
        $parcela = $this->cobranca->parcelas()->findOrFail($parcelaId);
        $parcela->update([
            'status'          => 'pago',
            'data_pagamento'  => now()->toDateString(),
        ]);
        $this->cobranca->refresh()->load('parcelas');
    }

    public function abrirEdicao(int $parcelaId): void
    {
        $parcela = $this->cobranca->parcelas()->findOrFail($parcelaId);
        $this->editandoParcelaId = $parcelaId;
        $this->editValor         = $parcela->valor;
        $this->editVencimento    = $parcela->vencimento;
    }

    public function salvarEdicao(): void
    {
        $this->validate([
            'editValor'      => ['required', 'numeric', 'min:0.01'],
            'editVencimento' => ['required', 'date'],
        ]);

        $this->cobranca->parcelas()->findOrFail($this->editandoParcelaId)->update([
            'valor'      => $this->editValor,
            'vencimento' => $this->editVencimento,
            'origem'     => 'manual',
        ]);

        $this->editandoParcelaId = null;
        $this->cobranca->refresh()->load('parcelas');
    }

    public function cancelarParcela(int $parcelaId): void
    {
        $this->cobranca->parcelas()->where('status', 'pendente')->findOrFail($parcelaId)
            ->update(['status' => 'cancelado']);
        $this->cobranca->refresh()->load('parcelas');
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
            <h1 class="text-xl font-semibold text-gray-900">{{ $cobranca->descricao }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $cobranca->cliente->nome }}</p>
        </div>
    </div>

    {{-- Resumo da cobrança --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Tipo</p>
                <p class="mt-1 text-sm font-semibold text-gray-800 capitalize">{{ $cobranca->tipo }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Valor total</p>
                <p class="mt-1 text-sm font-semibold text-gray-800">R$ {{ number_format($cobranca->valor_total, 2, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Parcelas</p>
                @php
                    $pagas    = $cobranca->parcelas->where('status', 'pago')->count();
                    $total    = $cobranca->parcelas->count();
                    $atrasada = $cobranca->parcelas->where('status', 'atrasado')->count();
                @endphp
                <p class="mt-1 text-sm font-semibold {{ $atrasada > 0 ? 'text-red-600' : 'text-gray-800' }}">
                    {{ $pagas }}/{{ $total }} pagas
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Valor pago</p>
                <p class="mt-1 text-sm font-semibold text-green-600">
                    R$ {{ number_format($cobranca->parcelas->where('status', 'pago')->sum('valor'), 2, ',', '.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Parcelas --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Parcelas</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-12">#</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Vencimento</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Valor</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Pagamento</th>
                        <th class="px-4 py-3 w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($parcelas as $parcela)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-xs font-medium">
                                    {{ $parcela->numero }}
                                </span>
                            </td>

                            {{-- Vencimento (editável) --}}
                            <td class="px-4 py-3">
                                @if($editandoParcelaId === $parcela->id)
                                    <input wire:model="editVencimento" type="date"
                                        class="border border-gray-200 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-36" />
                                @else
                                    <span class="{{ $parcela->status === 'atrasado' ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                        {{ \Carbon\Carbon::parse($parcela->vencimento)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </td>

                            {{-- Valor (editável) --}}
                            <td class="px-4 py-3">
                                @if($editandoParcelaId === $parcela->id)
                                    <input wire:model="editValor" type="number" step="0.01" min="0.01"
                                        class="border border-gray-200 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-28" />
                                @else
                                    <span class="text-gray-700">R$ {{ number_format($parcela->valor, 2, ',', '.') }}</span>
                                    @if($parcela->origem === 'manual')
                                        <span class="ml-1 text-xs text-orange-500">editado</span>
                                    @endif
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3">
                                @php
                                    $statusClass = match($parcela->status) {
                                        'pago'      => 'bg-green-100 text-green-700',
                                        'atrasado'  => 'bg-red-100 text-red-700',
                                        'cancelado' => 'bg-gray-100 text-gray-500',
                                        default     => 'bg-yellow-100 text-yellow-700',
                                    };
                                    $statusLabel = match($parcela->status) {
                                        'pago'      => 'Pago',
                                        'atrasado'  => 'Atrasado',
                                        'cancelado' => 'Cancelado',
                                        default     => 'Pendente',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>

                            {{-- Data pagamento --}}
                            <td class="px-4 py-3 text-gray-500 text-sm">
                                {{ $parcela->data_pagamento ? \Carbon\Carbon::parse($parcela->data_pagamento)->format('d/m/Y') : '—' }}
                            </td>

                            {{-- Ações --}}
                            <td class="px-4 py-3">
                                @if($editandoParcelaId === $parcela->id)
                                    <div class="flex items-center gap-1">
                                        <button wire:click="salvarEdicao"
                                            class="p-1.5 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors" title="Salvar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button wire:click="$set('editandoParcelaId', null)"
                                            class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors" title="Cancelar">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                @elseif($parcela->status === 'pendente')
                                    <div class="flex items-center gap-1">
                                        <button wire:click="marcarPago({{ $parcela->id }})"
                                            wire:confirm="Confirmar pagamento desta parcela?"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors" title="Marcar como pago">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                        <button wire:click="abrirEdicao({{ $parcela->id }})"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button wire:click="cancelarParcela({{ $parcela->id }})"
                                            wire:confirm="Cancelar esta parcela?"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Cancelar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
