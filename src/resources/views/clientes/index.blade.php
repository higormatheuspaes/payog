<x-app-layout>
    <div
        x-data="{
            showModal: false,
            editId: null,
            nome: '',
            telefone: '',
            cpf_cnpj: '',
            email: '',
            openCreate() {
                this.editId = null;
                this.nome = '';
                this.telefone = '';
                this.cpf_cnpj = '';
                this.email = '';
                this.showModal = true;
            },
            openEdit(id, nome, telefone, cpf_cnpj, email) {
                this.editId = id;
                this.nome = nome;
                this.telefone = telefone;
                this.cpf_cnpj = cpf_cnpj ?? '';
                this.email = email ?? '';
                this.showModal = true;
            }
        }"
    >

        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Clientes</h1>
                <p class="text-sm text-gray-500 mt-1">Gerencie os clientes da sua empresa</p>
            </div>
            <button @click="openCreate()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Novo Cliente
            </button>
        </div>

        {{-- Alertas --}}
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->has('excluir'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                {{ $errors->first('excluir') }}
            </div>
        @endif

        {{-- Filtros --}}
        <form method="GET" action="{{ route('clientes.index') }}" class="flex gap-3 mb-4">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" name="busca" value="{{ request('busca') }}"
                    placeholder="Buscar por nome, telefone ou CPF/CNPJ..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
            </div>
            <select name="categoria" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Todos os scores</option>
                <option value="bom_pagador" @selected(request('categoria') === 'bom_pagador')>Bom pagador</option>
                <option value="atencao" @selected(request('categoria') === 'atencao')>Atenção</option>
                <option value="risco" @selected(request('categoria') === 'risco')>Risco</option>
            </select>
            <button type="submit"
                class="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-200 transition-colors">
                Buscar
            </button>
        </form>

        {{-- Tabela --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Nome</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Telefone</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">CPF/CNPJ</th>
                        <th class="text-left px-4 py-3 font-medium text-gray-600">Score</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($clientes as $cliente)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $cliente->nome }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $cliente->telefone }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $cliente->cpf_cnpj ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badgeClass = match($cliente->score_categoria) {
                                        'bom_pagador' => 'bg-green-100 text-green-700',
                                        'atencao'     => 'bg-yellow-100 text-yellow-700',
                                        'risco'       => 'bg-red-100 text-red-700',
                                        default       => 'bg-gray-100 text-gray-700',
                                    };
                                    $badgeLabel = match($cliente->score_categoria) {
                                        'bom_pagador' => 'Bom pagador',
                                        'atencao'     => 'Atenção',
                                        'risco'       => 'Risco',
                                        default       => '—',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }} · {{ $cliente->score_atual }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit({{ $cliente->id }}, '{{ addslashes($cliente->nome) }}', '{{ addslashes($cliente->telefone) }}', '{{ addslashes($cliente->cpf_cnpj ?? '') }}', '{{ addslashes($cliente->email ?? '') }}')"
                                        class="text-gray-400 hover:text-indigo-600 transition-colors" title="Editar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <form method="POST" action="{{ route('clientes.destroy', $cliente) }}"
                                        onsubmit="return confirm('Tem certeza que deseja excluir este cliente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors" title="Excluir">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400">
                                Nenhum cliente encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($clientes->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $clientes->links() }}
                </div>
            @endif
        </div>

        {{-- Modal criar/editar --}}
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-5" x-text="editId ? 'Editar Cliente' : 'Novo Cliente'"></h2>

                <form
                    method="POST"
                    :action="editId ? `/clientes/${editId}` : '{{ route('clientes.store') }}'"
                    class="space-y-4"
                >
                    @csrf
                    <template x-if="editId">
                        <input type="hidden" name="_method" value="PUT" />
                    </template>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                        <input type="text" name="nome" x-model="nome" required autofocus
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('nome') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Telefone *</label>
                        <input type="text" name="telefone" x-model="telefone" required
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('telefone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CPF / CNPJ</label>
                        <input type="text" name="cpf_cnpj" x-model="cpf_cnpj"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('cpf_cnpj') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                        <input type="email" name="email" x-model="email"
                            class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                            x-text="editId ? 'Salvar alterações' : 'Criar cliente'">
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
