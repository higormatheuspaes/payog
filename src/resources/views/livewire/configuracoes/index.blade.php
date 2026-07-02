<?php

use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;
    public string $aba = 'empresa';

    // Aba Empresa
    public string $nome               = '';
    public string $cnpj_cpf           = '';
    public string $email              = '';
    public string $telefone           = '';
    public string $plano_nome         = '';
    public string $status_assinatura  = '';
    public ?string $logo_url          = null;
    public $logo                      = null;

    // Aba Notificações
    public bool   $notificacoes_ativas      = false;
    public int    $dias_antes_vencimento    = 3;
    public string $frequencia_aviso_atraso  = 'semanal';

    // Controle de consumo
    public string $teto_gasto_excedente = '';

    // Aba Integrações — Asaas
    public string $asaas_api_key = '';

    public string $mensagem = '';
    public string $mensagemTipo = 'sucesso';

    public function mount(): void
    {
        $empresa = Auth::user()->empresa;

        $this->nome              = $empresa->nome;
        $this->cnpj_cpf          = $empresa->cnpj_cpf;
        $this->email             = $empresa->email;
        $this->telefone          = $empresa->telefone;
        $this->plano_nome        = $empresa->plano?->nome ?? 'Trial';
        $this->status_assinatura = $empresa->status_assinatura ?? 'trial';
        $this->logo_url          = $empresa->logo_path
            ? Storage::disk('r2')->temporaryUrl($empresa->logo_path, now()->addHours(1))
            : null;

        $this->notificacoes_ativas     = $empresa->notificacoes_ativas;
        $this->dias_antes_vencimento   = $empresa->dias_antes_vencimento ?? 3;
        $this->frequencia_aviso_atraso = $empresa->frequencia_aviso_atraso ?? 'semanal';
        $this->teto_gasto_excedente    = $empresa->teto_gasto_excedente ? (string) $empresa->teto_gasto_excedente : '';

        // Asaas — só indica se já tem chave configurada (não exibe o valor)
        $integracao = $empresa->integracoesGateway()->where('gateway', 'asaas')->where('ativo', true)->first();
        $this->asaas_api_key = $integracao ? '••••••••••••••••' : '';
    }

    public function salvarEmpresa(): void
    {
        $this->validate([
            'nome'     => 'required|string|max:255',
            'cnpj_cpf' => 'required|string|max:18',
            'email'    => 'required|email|max:255',
            'telefone' => 'required|string|max:20',
            'logo'     => 'nullable|image|max:2048',
        ]);

        $empresa = Auth::user()->empresa;
        $data    = ['nome' => $this->nome, 'cnpj_cpf' => $this->cnpj_cpf, 'email' => $this->email, 'telefone' => $this->telefone];

        if ($this->logo) {
            if ($empresa->logo_path) {
                Storage::disk('r2')->delete($empresa->logo_path);
            }
            $path = $this->logo->storeAs('logos', 'empresa-' . $empresa->id . '.' . $this->logo->getClientOriginalExtension(), 'r2');
            $data['logo_path'] = $path;
            $this->logo_url    = Storage::disk('r2')->temporaryUrl($path, now()->addHours(1));
            $this->logo        = null;
        }

        $empresa->update($data);

        $this->mensagem     = 'Dados da empresa salvos com sucesso.';
        $this->mensagemTipo = 'sucesso';
    }

    public function salvarNotificacoes(): void
    {
        $this->validate([
            'dias_antes_vencimento'   => 'required|integer|min:1|max:30',
            'frequencia_aviso_atraso' => 'required|in:diaria,semanal,mensal',
            'teto_gasto_excedente'    => 'nullable|numeric|min:1',
        ]);

        $teto    = $this->teto_gasto_excedente !== '' ? (float) $this->teto_gasto_excedente : null;
        $empresa = Auth::user()->empresa;

        $empresa->update([
            'notificacoes_ativas'      => $this->notificacoes_ativas,
            'dias_antes_vencimento'    => $this->dias_antes_vencimento,
            'frequencia_aviso_atraso'  => $this->frequencia_aviso_atraso,
            'teto_gasto_excedente'     => $teto,
        ]);

        // Sincroniza o teto no registro de consumo do mês atual
        $empresa->consumoMensagensMes()
            ->where('ciclo_referencia', now()->startOfMonth()->toDateString())
            ->update(['teto_gasto_excedente' => $teto]);

        $this->mensagem     = 'Configurações de notificação salvas.';
        $this->mensagemTipo = 'sucesso';
    }

    public function salvarAsaas(): void
    {
        $this->validate([
            'asaas_api_key' => 'required|string|min:10',
        ]);

        if ($this->asaas_api_key === '••••••••••••••••') {
            $this->mensagem     = 'Chave do Asaas não alterada.';
            $this->mensagemTipo = 'sucesso';
            return;
        }

        $empresa = Auth::user()->empresa;

        $empresa->integracoesGateway()->updateOrCreate(
            ['gateway' => 'asaas'],
            ['credenciais_criptografadas' => encrypt($this->asaas_api_key), 'ativo' => true]
        );

        $this->asaas_api_key = '••••••••••••••••';
        $this->mensagem      = 'Chave do Asaas salva com sucesso.';
        $this->mensagemTipo  = 'sucesso';
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Configurações</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gerencie os dados da sua conta e integrações</p>
    </div>

    {{-- Abas --}}
    <div class="flex gap-1 mb-6 border-b border-gray-200">
        <button wire:click="$set('aba', 'empresa')"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px {{ $aba === 'empresa' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Empresa
        </button>
        <button wire:click="$set('aba', 'notificacoes')"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px {{ $aba === 'notificacoes' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Notificações
        </button>
        <button wire:click="$set('aba', 'integracoes')"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px {{ $aba === 'integracoes' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
            Integrações
        </button>
    </div>

    {{-- Mensagem de feedback --}}
    @if($mensagem)
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium {{ $mensagemTipo === 'sucesso' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $mensagem }}
        </div>
    @endif

    {{-- Aba: Empresa --}}
    @if($aba === 'empresa')
        @php
            $iniciais = collect(explode(' ', trim($nome)))->take(2)->map(fn($p) => strtoupper(mb_substr($p, 0, 1)))->implode('');
            $statusLabel = match($status_assinatura) {
                'ativo'     => ['label' => 'Ativo',     'class' => 'bg-green-100 text-green-700'],
                'trial'     => ['label' => 'Trial',     'class' => 'bg-indigo-100 text-indigo-700'],
                'suspenso'  => ['label' => 'Suspenso',  'class' => 'bg-red-100 text-red-700'],
                'cancelado' => ['label' => 'Cancelado', 'class' => 'bg-gray-100 text-gray-500'],
                default     => ['label' => 'Trial',     'class' => 'bg-indigo-100 text-indigo-700'],
            };
        @endphp

        <div class="space-y-5">

            {{-- Header da empresa --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center gap-4">
                    {{-- Avatar / Logo --}}
                    <label for="logo-upload" class="relative group cursor-pointer flex-shrink-0">
                        <div class="w-16 h-16 rounded-2xl overflow-hidden bg-indigo-100 flex items-center justify-center ring-2 ring-transparent group-hover:ring-indigo-400 transition-all">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-cover" alt="Preview">
                            @elseif($logo_url)
                                <img src="{{ $logo_url }}" class="w-full h-full object-cover" alt="Logo">
                            @else
                                <span class="text-xl font-bold text-indigo-600">{{ $iniciais ?: '?' }}</span>
                            @endif
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 border-2 border-white flex items-center justify-center group-hover:bg-indigo-700 transition-colors">
                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                        <input id="logo-upload" wire:model="logo" type="file" accept="image/*" class="sr-only">
                    </label>

                    <div class="flex-1 min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 truncate">{{ $nome ?: 'Minha Empresa' }}</h2>
                        <p class="text-sm text-gray-500 truncate">{{ $email }}</p>
                        @if($logo)
                            <p class="text-xs text-indigo-600 mt-0.5">Nova logo selecionada — salve para confirmar</p>
                        @else
                            <p class="text-xs text-gray-400 mt-0.5">Clique na imagem para trocar a logo</p>
                        @endif
                    </div>
                    <span class="text-xs font-medium px-2.5 py-1 rounded-full flex-shrink-0 {{ $statusLabel['class'] }}">
                        {{ $statusLabel['label'] }}
                    </span>
                </div>
            </div>

            {{-- Grid principal --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {{-- Formulário — ocupa 2/3 --}}
                <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-5">Dados cadastrais</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Nome / Razão social</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <input wire:model.live="nome" type="text" placeholder="Minha Empresa Ltda."
                                    class="w-full pl-9 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            @error('nome') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">CPF / CNPJ</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                    </svg>
                                </div>
                                <input wire:model="cnpj_cpf" type="text" placeholder="000.000.000-00"
                                    class="w-full pl-9 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            @error('cnpj_cpf') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">E-mail de contato</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <input wire:model="email" type="email" placeholder="empresa@email.com"
                                        class="w-full pl-9 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Telefone / WhatsApp</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                    </div>
                                    <input wire:model="telefone" type="text" placeholder="(11) 99999-9999"
                                        class="w-full pl-9 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                @error('telefone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button wire:click="salvarEmpresa" wire:loading.attr="disabled"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="salvarEmpresa">Salvar alterações</span>
                            <span wire:loading wire:target="salvarEmpresa">Salvando...</span>
                        </button>
                    </div>
                </div>

                {{-- Conta & Plano — ocupa 1/3 --}}
                <div class="flex flex-col gap-4">

                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Plano atual</h3>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-base font-bold text-gray-900">{{ $plano_nome }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span>
                        </div>
                        <div class="space-y-2 text-xs text-gray-500">
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Notificações via WhatsApp
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Cobranças ilimitadas
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Score de clientes
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Integração com Asaas
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <a href="#" class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-indigo-200 text-indigo-600 text-xs font-medium hover:bg-indigo-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                                Ver planos
                            </a>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 p-5">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Acesso</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-400 mb-0.5">Usuário logado</p>
                                <p class="text-sm font-medium text-gray-700 truncate">{{ auth()->user()->name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 mb-0.5">E-mail da conta</p>
                                <p class="text-sm font-medium text-gray-700 truncate">{{ auth()->user()->email }}</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <a href="{{ route('profile') }}" wire:navigate class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-gray-600 text-xs font-medium hover:bg-gray-50 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                Editar senha / e-mail
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- Aba: Notificações --}}
    @if($aba === 'notificacoes')
        @php
            $frequenciaLabel = match($frequencia_aviso_atraso) {
                'diaria'  => 'todos os dias',
                'semanal' => 'uma vez por semana',
                'mensal'  => 'uma vez por mês',
                default   => 'uma vez por semana',
            };
        @endphp

        <div class="space-y-5">

            {{-- Toggle principal --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Notificações automáticas via WhatsApp</p>
                        <p class="text-xs text-gray-500 mt-0.5">Quando desativado, nenhuma mensagem é enviada — o sistema opera em modo manual puro.</p>
                    </div>
                    <button wire:click="$toggle('notificacoes_ativas')"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors flex-shrink-0 {{ $notificacoes_ativas ? 'bg-indigo-600' : 'bg-gray-200' }}">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform {{ $notificacoes_ativas ? 'translate-x-6' : 'translate-x-1' }}"></span>
                    </button>
                </div>
            </div>

            {{-- 4 cards de notificação em grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- Lembrete antes do vencimento --}}
                <div class="bg-white rounded-xl border {{ $notificacoes_ativas ? 'border-gray-200' : 'border-gray-100' }} p-5 flex flex-col gap-4 transition-opacity {{ $notificacoes_ativas ? '' : 'opacity-50' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Lembrete de vencimento</p>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">1 msg por parcela</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Avisa o cliente que uma parcela está prestes a vencer, dando tempo para se organizar antes da data.</p>
                    <div class="mt-auto pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Enviar quantos dias antes?</p>
                        <div class="flex items-center gap-2">
                            <input wire:model.live="dias_antes_vencimento" type="number" min="1" max="30"
                                {{ $notificacoes_ativas ? '' : 'disabled' }}
                                class="w-16 border border-gray-300 rounded-lg px-2 py-2 text-sm text-center font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:text-gray-400">
                            <span class="text-sm text-gray-500">dias antes do vencimento</span>
                        </div>
                        @error('dias_antes_vencimento') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Lembrete no vencimento --}}
                <div class="bg-white rounded-xl border {{ $notificacoes_ativas ? 'border-gray-200' : 'border-gray-100' }} p-5 flex flex-col gap-4 transition-opacity {{ $notificacoes_ativas ? '' : 'opacity-50' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Lembrete no vencimento</p>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">1 msg por parcela</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Enviado automaticamente no dia do vencimento para clientes com parcela ainda em aberto, servindo como último aviso antes do atraso.</p>
                    <div class="mt-auto pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400 italic">Sem configuração adicional — dispara sempre no dia do vencimento.</p>
                    </div>
                </div>

                {{-- Aviso de atraso --}}
                <div class="bg-white rounded-xl border {{ $notificacoes_ativas ? 'border-gray-200' : 'border-gray-100' }} p-5 flex flex-col gap-4 transition-opacity {{ $notificacoes_ativas ? '' : 'opacity-50' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Aviso de atraso</p>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">Recorrente</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Reenviado enquanto a parcela estiver em atraso, sem limite de repetições. A frequência controla o intervalo entre cada reenvio.</p>
                    <div class="mt-auto pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Frequência de reenvio:</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['diaria' => ['label' => 'Diária', 'sub' => '↑ custo', 'subClass' => 'text-orange-500'], 'semanal' => ['label' => 'Semanal', 'sub' => 'Recomendado', 'subClass' => 'text-green-600'], 'mensal' => ['label' => 'Mensal', 'sub' => '↓ custo', 'subClass' => 'text-blue-500']] as $valor => $info)
                                <label class="flex flex-col items-center text-center px-2 py-2.5 rounded-lg border cursor-pointer transition-colors
                                    {{ $frequencia_aviso_atraso === $valor ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}
                                    {{ !$notificacoes_ativas ? 'pointer-events-none' : '' }}">
                                    <input wire:model.live="frequencia_aviso_atraso" type="radio" value="{{ $valor }}" class="sr-only" {{ $notificacoes_ativas ? '' : 'disabled' }}>
                                    <span class="text-xs font-semibold {{ $frequencia_aviso_atraso === $valor ? 'text-indigo-700' : 'text-gray-700' }}">{{ $info['label'] }}</span>
                                    <span class="text-xs mt-0.5 {{ $frequencia_aviso_atraso === $valor ? $info['subClass'] : 'text-gray-400' }}">{{ $info['sub'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Confirmação de pagamento --}}
                <div class="bg-white rounded-xl border {{ $notificacoes_ativas ? 'border-gray-200' : 'border-gray-100' }} p-5 flex flex-col gap-4 transition-opacity {{ $notificacoes_ativas ? '' : 'opacity-50' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Confirmação de pagamento</p>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">1 msg por parcela</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 leading-relaxed">Enviado imediatamente após a confirmação de pagamento — seja registrada manualmente pelo empresário ou automaticamente via gateway.</p>
                    <div class="mt-auto pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400 italic">Sem configuração adicional — dispara automaticamente a cada baixa.</p>
                    </div>
                </div>

            </div>

            {{-- Limite de custo com excedente --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-900">Limite de gasto com excedente</p>
                        <p class="text-xs text-gray-500 mt-0.5">Quando o custo das mensagens acima do plano atingir este valor, novos envios são pausados automaticamente. Deixe vazio para sem limite.</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-sm text-gray-500">R$</span>
                        <input wire:model="teto_gasto_excedente" type="number" min="1" step="1" placeholder="Sem limite"
                            class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                @error('teto_gasto_excedente') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Resumo dinâmico --}}
            @if($notificacoes_ativas)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5">
                        <p class="text-sm font-semibold text-indigo-800 mb-3">Resumo — o que cada cliente receberá:</p>
                        <ul class="space-y-2">
                            @foreach([
                                "1 lembrete {$dias_antes_vencimento} dia(s) antes do vencimento",
                                "1 lembrete no dia do vencimento",
                                "Aviso de cobrança {$frequenciaLabel} enquanto estiver em atraso",
                                "1 confirmação ao registrar o pagamento",
                            ] as $item)
                                <li class="flex items-start gap-2 text-xs text-indigo-700">
                                    <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if($frequencia_aviso_atraso === 'diaria')
                        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex flex-col justify-center">
                            <p class="text-sm font-semibold text-yellow-800 mb-2">Atenção ao consumo</p>
                            <p class="text-xs text-yellow-700 leading-relaxed">Com avisos diários, clientes inadimplentes podem gerar dezenas de mensagens por mês. Cada mensagem acima do limite do seu plano custa <strong>R$ 0,20</strong>. Considere a frequência semanal para equilibrar cobrança e custo.</p>
                        </div>
                    @else
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 flex flex-col justify-center">
                            <p class="text-sm font-semibold text-gray-700 mb-2">Consumo estimado</p>
                            <p class="text-xs text-gray-500 leading-relaxed">Clientes em dia geram em média <strong>2–3 mensagens/mês</strong>. Clientes em atraso geram mensagens adicionais conforme a frequência configurada. Acompanhe o consumo no dashboard.</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-6 text-center">
                    <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <p class="text-sm font-medium text-gray-500">Notificações desativadas</p>
                    <p class="text-xs text-gray-400 mt-1">O sistema opera em modo manual. Ative o toggle acima para habilitar o envio automático.</p>
                </div>
            @endif

            <div class="flex justify-end">
                <button wire:click="salvarNotificacoes" wire:loading.attr="disabled"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="salvarNotificacoes">Salvar configurações</span>
                    <span wire:loading wire:target="salvarNotificacoes">Salvando...</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Aba: Integrações --}}
    @if($aba === 'integracoes')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- WhatsApp --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">WhatsApp</h2>
                        <p class="text-xs text-gray-500">Notificações automáticas para seus clientes</p>
                    </div>
                    @if($notificacoes_ativas)
                        <span class="ml-auto text-xs font-medium px-2 py-1 bg-green-100 text-green-700 rounded-full">Ativo</span>
                    @else
                        <span class="ml-auto text-xs font-medium px-2 py-1 bg-gray-100 text-gray-500 rounded-full">Inativo</span>
                    @endif
                </div>

                <p class="text-sm text-gray-600 mb-4 flex-1">
                    O Payog cuida do envio de todas as notificações. Você não precisa criar conta em nenhum provedor — basta ativar e configurar as preferências na aba <strong>Notificações</strong>.
                </p>

                <div class="space-y-2 text-sm text-gray-500 mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Lembrete antes do vencimento
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Aviso de atraso recorrente
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Confirmação de pagamento
                    </div>
                </div>

                <a href="#" wire:click.prevent="$set('aba', 'notificacoes')"
                    class="w-full text-center px-4 py-2 border border-indigo-600 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-50 transition-colors">
                    Configurar notificações
                </a>
            </div>

            {{-- Gateway Asaas --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Gateway de Pagamento (Asaas)</h2>
                        <p class="text-xs text-gray-500">Boleto e PIX automáticos para seus clientes</p>
                    </div>
                    @if($asaas_api_key)
                        <span class="ml-auto text-xs font-medium px-2 py-1 bg-green-100 text-green-700 rounded-full">Conectado</span>
                    @else
                        <span class="ml-auto text-xs font-medium px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">Não configurado</span>
                    @endif
                </div>

                <p class="text-sm text-gray-600 mb-4 flex-1">
                    Conecte sua conta do Asaas para gerar boletos e cobranças via PIX automaticamente. Sua chave é armazenada de forma criptografada.
                </p>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Chave de API do Asaas</label>
                    <input wire:model="asaas_api_key" type="password"
                        placeholder="{{ $asaas_api_key ? '••••••••••••••••' : 'Cole sua chave aqui' }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('asaas_api_key') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-400 mt-1">Encontre sua chave em Minha conta → Integrações no painel do Asaas.</p>
                </div>

                <button wire:click="salvarAsaas" wire:loading.attr="disabled"
                    class="w-full px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50">
                    <span wire:loading.remove wire:target="salvarAsaas">Salvar chave</span>
                    <span wire:loading wire:target="salvarAsaas">Salvando...</span>
                </button>
            </div>

        </div>
    @endif
</div>
