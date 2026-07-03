<?php

use App\Models\Plano;
use App\Models\User;
use App\Services\AbacatePayService;
use App\Services\RegistroService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $nome_empresa = '';
    public string $cnpj_cpf    = '';
    public string $telefone    = '';
    public string $name        = '';
    public string $email       = '';
    public string $password    = '';
    public string $password_confirmation = '';
    public int    $plano_id    = 0;

    public function mount(): void
    {
        $basico = Plano::where('nome', 'Básico')->first();
        $this->plano_id = $basico?->id ?? 0;
    }

    public function with(): array
    {
        return ['planos' => Plano::orderBy('valor_mensal')->get()];
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

        // Cria customer e assinatura no AbacatePay
        try {
            $empresa  = $user->empresa;
            $plano    = $empresa->plano;
            $abacate  = new AbacatePayService;

            $customer = $abacate->criarCustomer(
                $user->name,
                $user->email,
                $empresa->cnpj_cpf,
                $empresa->telefone,
            );

            $subscription = $abacate->criarAssinatura(
                customerId:    $customer['id'],
                productId:     $plano->abacatepay_product_id,
                externalId:    'payog-empresa-' . $empresa->id,
                completionUrl: route('assinatura.aguardando', absolute: true),
            );

            $empresa->update([
                'abacatepay_customer_id'      => $customer['id'],
                'abacatepay_subscription_id'  => $subscription['id'],
            ]);

            $empresa->assinatura?->update([
                'gateway_assinatura_id_externo' => $subscription['id'],
            ]);

            $this->redirect($subscription['url']);
        } catch (\Exception $e) {
            // Se AbacatePay falhar, manda pro dashboard com aviso
            // A conta existe e o admin pode regularizar manualmente
            \Illuminate\Support\Facades\Log::error('AbacatePay registro falhou', [
                'empresa_id' => $user->empresa_id,
                'error'      => $e->getMessage(),
            ]);
            $this->redirect(route('assinatura.aguardando', absolute: false));
        }
    }
}; ?>

<div>
    <form wire:submit="register" class="space-y-5">

        {{-- Seletor de plano --}}
        <div>
            <p class="text-sm font-medium text-gray-700 mb-3">Escolha seu plano</p>
            <div class="grid grid-cols-3 gap-2">
                @foreach($planos as $plano)
                    <label class="relative flex flex-col items-center text-center p-3 rounded-xl border-2 cursor-pointer transition-all
                        {{ $plano_id === $plano->id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 bg-white' }}">
                        <input type="radio" wire:model.live="plano_id" value="{{ $plano->id }}" class="sr-only">
                        <span class="text-xs font-semibold {{ $plano_id === $plano->id ? 'text-indigo-700' : 'text-gray-700' }}">
                            {{ $plano->nome }}
                        </span>
                        <span class="text-sm font-bold mt-0.5 {{ $plano_id === $plano->id ? 'text-indigo-600' : 'text-gray-900' }}">
                            R$ {{ number_format($plano->valor_mensal, 2, ',', '.') }}
                        </span>
                        <span class="text-xs text-gray-400">/mês</span>
                        @if($plano_id === $plano->id)
                            <div class="absolute top-1.5 right-1.5 w-3.5 h-3.5 rounded-full bg-indigo-500 flex items-center justify-center">
                                <svg class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        @endif
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plano_id')" class="mt-1" />
        </div>

        <hr class="border-gray-100">

        {{-- Empresa --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <x-input-label for="nome_empresa" value="Nome da empresa" />
                <x-text-input wire:model="nome_empresa" id="nome_empresa" class="block mt-1 w-full" type="text" required autofocus />
                <x-input-error :messages="$errors->get('nome_empresa')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="cnpj_cpf" value="CPF / CNPJ" />
                <x-text-input wire:model="cnpj_cpf" id="cnpj_cpf" class="block mt-1 w-full" type="text" required />
                <x-input-error :messages="$errors->get('cnpj_cpf')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="telefone" value="Telefone / WhatsApp" />
                <x-text-input wire:model="telefone" id="telefone" class="block mt-1 w-full" type="text" required />
                <x-input-error :messages="$errors->get('telefone')" class="mt-1" />
            </div>
        </div>

        <hr class="border-gray-100">

        {{-- Responsável --}}
        <div class="space-y-4">
            <div>
                <x-input-label for="name" value="Seu nome" />
                <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="email" value="E-mail" />
                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="password" value="Senha" />
                    <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirmar senha" />
                    <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" required />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-1">
            <a class="text-sm text-gray-500 hover:text-gray-700 underline" href="{{ route('login') }}" wire:navigate>
                Já tem uma conta?
            </a>
            <x-primary-button wire:loading.attr="disabled" wire:loading.class="opacity-75">
                <span wire:loading.remove>Criar conta e pagar</span>
                <span wire:loading>Processando...</span>
            </x-primary-button>
        </div>

    </form>
</div>
