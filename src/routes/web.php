<?php

use App\Http\Controllers\ClienteController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('clientes', 'clientes/index')->name('clientes.index');
    Volt::route('cobrancas', 'cobrancas/index')->name('cobrancas.index');
    Volt::route('cobrancas/create', 'cobrancas/create')->name('cobrancas.create');
    Volt::route('cobrancas/{cobranca}', 'cobrancas/show')->name('cobrancas.show');
    Route::view('parcelas', 'livewire.parcelas.index')->name('parcelas.index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
