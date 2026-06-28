<?php

use App\Http\Controllers\ClienteController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('clientes', 'clientes/index')->name('clientes.index');
    Route::view('cobrancas', 'livewire.cobrancas.index')->name('cobrancas.index');
    Route::view('parcelas', 'livewire.parcelas.index')->name('parcelas.index');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
