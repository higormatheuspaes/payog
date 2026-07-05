<?php

use App\Livewire\Forms\LoginForm;
use App\Models\Plano;
use App\Models\User;
use App\Services\AbacatePayService;
use App\Services\RegistroService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $modo = 'login'; // 'login' | 'registro'

    // Login
    public LoginForm $form;

    // Registro
    public string $nome_empresa          = '';
    public string $cnpj_cpf              = '';
    public string $telefone              = '';
    public string $name                  = '';
    public string $email                 = '';
    public string $password              = '';
    public string $password_confirmation = '';
    public int    $plano_id              = 0;

    public function mount(string $modo = 'login'): void
    {
        $this->modo = $modo;
        $basico = Plano::where('nome', 'Básico')->first();
        $this->plano_id = $basico?->id ?? 0;
    }

    public function with(): array
    {
        return ['planos' => Plano::orderBy('valor_mensal')->get()];
    }

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function register(): void
    {
        $validated = $this->validate([
            'nome_empresa' => ['required', 'string', 'max:255'],
            'cnpj_cpf'     => ['required', 'string', 'max:18'],
            'telefone'     => ['required', 'string', 'max:20'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password'     => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
            'plano_id'     => ['required', 'exists:planos,id'],
        ]);

        $user = (new RegistroService)->registrar($validated);
        event(new Registered($user));
        Auth::login($user);

        try {
            $empresa = $user->empresa;
            $plano   = $empresa->plano;
            $abacate = new AbacatePayService;

            $customer = $abacate->criarCustomer(
                $user->name, $user->email, $empresa->cnpj_cpf, $empresa->telefone,
            );

            $subscription = $abacate->criarAssinatura(
                customerId:    $customer['id'],
                productId:     $plano->abacatepay_product_id,
                externalId:    'payog-empresa-' . $empresa->id,
                completionUrl: route('assinatura.aguardando', absolute: true),
            );

            $empresa->update([
                'abacatepay_customer_id'     => $customer['id'],
                'abacatepay_subscription_id' => $subscription['id'],
            ]);

            $empresa->assinatura?->update([
                'gateway_assinatura_id_externo' => $subscription['id'],
            ]);

            $this->redirect($subscription['url']);
        } catch (\Exception $e) {
            Log::error('AbacatePay registro falhou', [
                'empresa_id' => $user->empresa_id,
                'error'      => $e->getMessage(),
            ]);
            $this->redirect(route('assinatura.aguardando', absolute: false));
        }
    }
}; ?>

<div x-data="{
    modo: '{{ $modo }}',
    async trocar(novo) {
        if (this.modo === novo) return;
        const el = this.$refs.forms;
        el.style.transition = 'opacity 0.15s ease, transform 0.15s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(6px)';
        await new Promise(r => setTimeout(r, 160));
        this.modo = novo;
        await this.$nextTick();
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    }
}">

    {{-- Toggle --}}
    <div class="flex mb-8 bg-gray-100 rounded-xl p-1">
        <button @click="trocar('login')"
            :class="modo === 'login'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-500 hover:text-gray-700'"
            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
            Entrar
        </button>
        <button @click="trocar('registro')"
            :class="modo === 'registro'
                ? 'bg-white text-gray-900 shadow-sm'
                : 'text-gray-500 hover:text-gray-700'"
            class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-all duration-200">
            Criar conta
        </button>
    </div>

    {{-- Wrapper animado --}}
    <div x-ref="forms" style="opacity:1; transform:translateY(0)">

    {{-- ── LOGIN ── --}}
    <div x-show="modo === 'login'">

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Bem-vindo de volta</h2>
            <p class="text-sm text-gray-500 mt-1">Entre na sua conta para continuar</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form wire:submit="login" class="space-y-4">
            <div>
                <x-input-label for="login_email" value="E-mail" />
                <x-text-input wire:model="form.email" id="login_email"
                    class="block mt-1 w-full" type="email" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <x-input-label for="login_password" value="Senha" />
                    @if(Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate
                            class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                            Esqueceu a senha?
                        </a>
                    @endif
                </div>
                <x-text-input wire:model="form.password" id="login_password"
                    class="block w-full" type="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
            </div>

            <div class="flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="remember" class="ms-2 text-sm text-gray-600">Lembrar de mim</label>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg
                       hover:bg-indigo-700 active:bg-indigo-800 transition-colors disabled:opacity-50 mt-2">
                <span wire:loading.remove wire:target="login">Entrar</span>
                <span wire:loading wire:target="login">Entrando...</span>
            </button>
        </form>
    </div>

    {{-- ── REGISTRO ── --}}
    <div x-show="modo === 'registro'">

        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Crie sua conta</h2>
            <p class="text-sm text-gray-500 mt-1">Comece agora, cancele quando quiser</p>
        </div>

        <form wire:submit="register" class="space-y-5">

            {{-- Plano --}}
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Escolha seu plano</p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($planos as $plano)
                        <label
                            class="flex flex-col items-center text-center p-3 rounded-xl border-2 cursor-pointer transition-all duration-150
                                {{ $plano_id === $plano->id
                                    ? 'border-indigo-500 bg-indigo-50'
                                    : 'border-gray-200 hover:border-indigo-200 bg-white' }}">
                            <input type="radio" wire:model.live="plano_id" value="{{ $plano->id }}" class="sr-only">

                            <span class="text-xs font-semibold {{ $plano_id === $plano->id ? 'text-indigo-700' : 'text-gray-600' }}">
                                {{ $plano->nome }}
                            </span>
                            <span class="text-base font-bold mt-1 {{ $plano_id === $plano->id ? 'text-indigo-600' : 'text-gray-900' }}">
                                R$ {{ number_format($plano->valor_mensal, 2, ',', '.') }}
                            </span>
                            <span class="text-xs text-gray-400 mb-1">/mês</span>

                            {{-- Indicador de seleção --}}
                            <div class="h-4 flex items-center justify-center">
                                @if($plano_id === $plano->id)
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('plano_id')" class="mt-1" />
            </div>

            <div class="border-t border-gray-100"></div>

            {{-- Empresa --}}
            <div class="space-y-4">
                <div>
                    <x-input-label for="nome_empresa" value="Nome da empresa" />
                    <x-text-input wire:model="nome_empresa" id="nome_empresa" class="block mt-1 w-full" type="text" required />
                    <x-input-error :messages="$errors->get('nome_empresa')" class="mt-1" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="cnpj_cpf" value="CPF / CNPJ" />
                        <x-text-input wire:model="cnpj_cpf" id="cnpj_cpf" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('cnpj_cpf')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="telefone" value="WhatsApp" />
                        <x-text-input wire:model="telefone" id="telefone" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('telefone')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100"></div>

            {{-- Responsável --}}
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="name" value="Seu nome" />
                        <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="reg_email" value="E-mail" />
                        <x-text-input wire:model="email" id="reg_email" class="block mt-1 w-full" type="email" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="password" value="Senha" />
                        <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="password_confirmation" value="Confirmar" />
                        <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" required />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg
                       hover:bg-indigo-700 active:bg-indigo-800 transition-colors disabled:opacity-50">
                <span wire:loading.remove wire:target="register">Criar conta e pagar</span>
                <span wire:loading wire:target="register">Processando...</span>
            </button>

        </form>
    </div>

    </div>{{-- /x-ref="forms" --}}

</div>
