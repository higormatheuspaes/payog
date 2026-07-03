<?php

use App\Models\Cliente;
use App\Models\LogMensagem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $tipo      = '';
    public string $status    = '';
    public string $clienteId = '';

    public function with(): array
    {
        $empresaId = Auth::user()->empresa_id;

        return [
            'mensagens' => LogMensagem::with('cliente')
                ->where('empresa_id', $empresaId)
                ->when($this->tipo,      fn($q) => $q->where('tipo', $this->tipo))
                ->when($this->status,    fn($q) => $q->where('status', $this->status))
                ->when($this->clienteId, fn($q) => $q->where('cliente_id', $this->clienteId))
                ->orderByDesc('enviado_em')
                ->paginate(20),
            'clientes' => Cliente::where('empresa_id', $empresaId)->orderBy('nome')->get(['id', 'nome']),
        ];
    }

    public function updatedTipo(): void      { $this->resetPage(); }
    public function updatedStatus(): void    { $this->resetPage(); }
    public function updatedClienteId(): void { $this->resetPage(); }
}; ?>

<x-data-table :paginator="$mensagens">
    <x-slot:header>
        <h1 class="text-xl font-semibold text-gray-900">Mensagens enviadas</h1>
        <p class="text-sm text-gray-500 mt-0.5">Histórico de notificações WhatsApp</p>
    </x-slot:header>

    <x-slot:filters>
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="clienteId"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700">
                <option value="">Todos os clientes</option>
                @foreach($clientes as $c)
                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                @endforeach
            </select>

            <select wire:model.live="tipo"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700">
                <option value="">Todos os tipos</option>
                <option value="lembrete_antes">Lembrete antes</option>
                <option value="lembrete_dia">Lembrete no dia</option>
                <option value="aviso_atraso">Aviso de atraso</option>
                <option value="confirmacao_pagamento">Confirmação de pagamento</option>
            </select>

            <select wire:model.live="status"
                class="border border-gray-300 rounded-lg text-sm pl-3 pr-8 py-2.5 focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700">
                <option value="">Todos os status</option>
                <option value="enviado">Enviado</option>
                <option value="erro">Erro</option>
            </select>
        </div>
    </x-slot:filters>

    <table class="w-full table-fixed text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[132px] hidden md:table-cell">Data</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[180px] hidden sm:table-cell">Cliente</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[130px] hidden lg:table-cell">Tipo</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[140px] hidden lg:table-cell">Telefone</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Mensagem</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide w-[90px]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($mensagens as $msg)
                        @php
                            $tipoLabel = match($msg->tipo) {
                                'lembrete_antes'        => 'Lembrete antes',
                                'lembrete_dia'          => 'Lembrete no dia',
                                'aviso_atraso'          => 'Aviso de atraso',
                                'confirmacao_pagamento' => 'Confirmação',
                            };
                            $tipoClass = match($msg->tipo) {
                                'lembrete_antes', 'lembrete_dia' => 'bg-blue-50 text-blue-700',
                                'aviso_atraso'                   => 'bg-red-50 text-red-700',
                                'confirmacao_pagamento'          => 'bg-green-50 text-green-700',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors" x-data="{ open: false }">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap hidden md:table-cell">
                                {{ $msg->enviado_em->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900 hidden sm:table-cell">
                                {{ $msg->cliente->nome ?? '—' }}
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tipoClass }}">
                                    {{ $tipoLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ $msg->telefone }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                <button @click="open = !open" class="text-left w-full">
                                    <span x-show="!open" class="truncate block text-xs sm:text-sm">{{ Str::limit($msg->mensagem, 60) }}</span>
                                    <span x-show="open" class="whitespace-pre-line text-xs">{{ $msg->mensagem }}</span>
                                    {{-- Subtítulo mobile --}}
                                    <span class="sm:hidden block text-xs text-gray-400 mt-0.5 truncate">
                                        {{ $msg->cliente->nome ?? '' }} · {{ $msg->enviado_em->format('d/m H:i') }}
                                    </span>
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                @if($msg->status === 'enviado')
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span class="hidden sm:inline">Enviado</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-red-600" title="{{ $msg->erro_detalhes }}">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span class="hidden sm:inline">Erro</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p class="text-sm text-gray-400">Nenhuma mensagem encontrada.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

</x-data-table>
