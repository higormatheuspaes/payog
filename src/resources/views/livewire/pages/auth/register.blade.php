<?php

use App\Models\User;
use App\Services\RegistroService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $nome_empresa = '';
    public string $cnpj_cpf = '';
    public string $telefone = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'nome_empresa' => ['required', 'string', 'max:255'],
            'cnpj_cpf'     => ['required', 'string', 'max:18'],
            'telefone'     => ['required', 'string', 'max:20'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'     => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $service = new RegistroService();
        $user = $service->registrar($validated);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Nome da Empresa -->
        <div>
            <x-input-label for="nome_empresa" :value="__('Nome da Empresa')" />
            <x-text-input wire:model="nome_empresa" id="nome_empresa" class="block mt-1 w-full" type="text" name="nome_empresa" required autofocus />
            <x-input-error :messages="$errors->get('nome_empresa')" class="mt-2" />
        </div>

        <!-- CNPJ / CPF -->
        <div class="mt-4">
            <x-input-label for="cnpj_cpf" :value="__('CNPJ / CPF')" />
            <x-text-input wire:model="cnpj_cpf" id="cnpj_cpf" class="block mt-1 w-full" type="text" name="cnpj_cpf" required />
            <x-input-error :messages="$errors->get('cnpj_cpf')" class="mt-2" />
        </div>

        <!-- Telefone -->
        <div class="mt-4">
            <x-input-label for="telefone" :value="__('Telefone')" />
            <x-text-input wire:model="telefone" id="telefone" class="block mt-1 w-full" type="text" name="telefone" required />
            <x-input-error :messages="$errors->get('telefone')" class="mt-2" />
        </div>

        <!-- Nome do responsável -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Seu nome')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Senha -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirmar Senha -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirmar Senha')" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Já tem uma conta?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Criar conta') }}
            </x-primary-button>
        </div>
    </form>
</div>
