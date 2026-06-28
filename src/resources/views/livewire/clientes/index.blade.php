<?php

use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $busca = '';
    public string $categoria = '';

    public bool $showModal = false;

    public ?int $editandoId = null;
    public string $nome = '';
    public string $telefone = '';
    public string $cpf_cnpj = '';
    public string $email = '';

    public function with(): array
    {
        return [
            'clientes' => (new ClienteService)->listar($this->busca, $this->categoria),
        ];
    }

    public function updatedBusca(): void { $this->resetPage(); }
    public function updatedCategoria(): void { $this->resetPage(); }

    public function novo(): void
    {
        $this->reset(['editandoId', 'nome', 'telefone', 'cpf_cnpj', 'email']);
        $this->showModal = true;
    }

    public function editar(int $id): void
    {
        $cliente = Cliente::where('empresa_id', Auth::user()->empresa_id)->findOrFail($id);

        $this->editandoId = $cliente->id;
        $this->nome       = $cliente->nome;
        $this->telefone   = $cliente->telefone;
        $this->cpf_cnpj   = $cliente->cpf_cnpj ?? '';
        $this->email      = $cliente->email ?? '';
        $this->showModal  = true;
    }

    public function salvar(): void
    {
        $dados = $this->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'email'    => ['nullable', 'email', 'max:255'],
        ]);

        $service = new ClienteService;

        if ($this->editandoId) {
            $cliente = Cliente::where('empresa_id', Auth::user()->empresa_id)->findOrFail($this->editandoId);
            $service->atualizar($cliente, $dados);
        } else {
            $service->criar($dados);
        }

        $this->showModal = false;
        $this->reset(['editandoId', 'nome', 'telefone', 'cpf_cnpj', 'email']);
    }

    public function excluir(int $id): void
    {
        $cliente = Cliente::where('empresa_id', Auth::user()->empresa_id)->findOrFail($id);

        try {
            (new ClienteService)->excluir($cliente);
        } catch (\RuntimeException $e) {
            $this->addError('excluir', $e->getMessage());
        }
    }
}; ?>

<div>
    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Clientes</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gerencie os clientes da sua empresa</p>
        </div>
        <button wire:click="novo"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 active:bg-indigo-800 transition-colors shadow-sm w-full sm:w-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Cliente
        </button>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-col sm:flex-row gap-2 mb-4">
        <div class="flex-1 relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input wire:model.live.debounce.300ms="busca" type="text" placeholder="Buscar por nome, telefone ou CPF/CNPJ..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white" />
        </div>
        <select wire:model.live="categoria"
            class="border border-gray-300 rounded-lg text-sm px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white text-gray-700">
            <option value="">Todos os scores</option>
            <option value="bom_pagador">Bom pagador</option>
            <option value="atencao">Atenção</option>
            <option value="risco">Risco</option>
        </select>
    </div>

    {{-- Erro de exclusão --}}
    @error('excluir')
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $message }}
        </div>
    @enderror

    {{-- Tabela --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[600px]">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Nome</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Telefone</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs hidden md:table-cell">CPF/CNPJ</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-500 uppercase tracking-wide text-xs">Score</th>
                        <th class="px-4 py-3 w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3.5 font-medium text-gray-900">{{ $cliente->nome }}</td>
                            <td class="px-4 py-3.5 text-gray-600">{{ $cliente->telefone }}</td>
                            <td class="px-4 py-3.5 text-gray-500 hidden md:table-cell">{{ $cliente->cpf_cnpj ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                @php
                                    $badgeClass = match($cliente->score_categoria) {
                                        'bom_pagador' => 'bg-green-100 text-green-700',
                                        'atencao'     => 'bg-yellow-100 text-yellow-700',
                                        'risco'       => 'bg-red-100 text-red-700',
                                        default       => 'bg-gray-100 text-gray-600',
                                    };
                                    $badgeLabel = match($cliente->score_categoria) {
                                        'bom_pagador' => 'Bom',
                                        'atencao'     => 'Atenção',
                                        'risco'       => 'Risco',
                                        default       => '—',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }} · {{ $cliente->score_atual }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <button wire:click="editar({{ $cliente->id }})"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button wire:click="excluir({{ $cliente->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este cliente?"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Excluir">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <p class="text-sm text-gray-400">Nenhum cliente encontrado.</p>
                                <button wire:click="novo" class="mt-3 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                    Cadastrar primeiro cliente →
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clientes->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    {{-- Modal criar/editar --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">
                    {{ $editandoId ? 'Editar Cliente' : 'Novo Cliente' }}
                </h2>

                <form wire:submit="salvar" class="space-y-4">
                    <div>
                        <x-input-label for="nome" value="Nome *" />
                        <x-text-input wire:model="nome" id="nome" class="block mt-1 w-full" type="text" required autofocus />
                        <x-input-error :messages="$errors->get('nome')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="telefone" value="Telefone *" />
                        <x-text-input wire:model="telefone" id="telefone" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('telefone')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="cpf_cnpj" value="CPF / CNPJ" />
                        <x-text-input wire:model="cpf_cnpj" id="cpf_cnpj" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('cpf_cnpj')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" value="E-mail" />
                        <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            {{ $editandoId ? 'Salvar alterações' : 'Criar cliente' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
