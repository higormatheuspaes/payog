<?php

use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\WebhookAbacatePayController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

// Webhook AbacatePay (sem CSRF)
Route::post('webhook/abacatepay', [WebhookAbacatePayController::class, 'handle'])
    ->name('webhook.abacatepay');

// Páginas de assinatura (auth obrigatório, mas sem middleware de assinatura para evitar loop)
Route::middleware('auth')->group(function () {
    Route::get('assinatura/aguardando', fn () => view('assinatura.aguardando'))->name('assinatura.aguardando');
    Route::get('assinatura/pendente',   fn () => view('assinatura.pendente'))->name('assinatura.pendente');
    Route::get('assinatura/suspensa',   fn () => view('assinatura.suspensa'))->name('assinatura.suspensa');
    Route::get('assinatura/cancelada',  fn () => view('assinatura.cancelada'))->name('assinatura.cancelada');
    Route::post('assinatura/checkout',  [AssinaturaController::class, 'gerarCheckout'])->name('assinatura.checkout');

    Route::post('sair', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('logout');
});

Route::middleware(['auth', 'verified', 'assinatura'])->group(function () {
    Volt::route('assinatura/planos', 'assinatura/planos')->name('assinatura.planos');
    Volt::route('dashboard', 'dashboard')->name('dashboard');
    Volt::route('clientes', 'clientes/index')->name('clientes.index');
    Volt::route('cobrancas', 'cobrancas/index')->name('cobrancas.index');
    Volt::route('cobrancas/create', 'cobrancas/create')->name('cobrancas.create');
    Volt::route('cobrancas/{cobranca}', 'cobrancas/show')->name('cobrancas.show');
    Volt::route('parcelas', 'parcelas/index')->name('parcelas.index');
    Volt::route('relatorios', 'relatorios/index')->name('relatorios.index');
    Volt::route('configuracoes', 'configuracoes/index')->name('configuracoes.index');
    Volt::route('clientes/{cliente}', 'clientes/show')->name('clientes.show');
    Volt::route('mensagens', 'mensagens/index')->name('mensagens.index');

    Route::get('relatorios/inadimplencia/csv',          [RelatorioController::class, 'inadimplenciaCsv'])->name('relatorios.inadimplencia.csv');
    Route::get('relatorios/inadimplencia/pdf',          [RelatorioController::class, 'inadimplenciaPdf'])->name('relatorios.inadimplencia.pdf');
    Route::get('relatorios/recebimentos/csv',           [RelatorioController::class, 'recebimentosCsv'])->name('relatorios.recebimentos.csv');
    Route::get('relatorios/recebimentos/pdf',           [RelatorioController::class, 'recebimentosPdf'])->name('relatorios.recebimentos.pdf');
    Route::get('relatorios/fluxo-caixa/csv',            [RelatorioController::class, 'fluxoCaixaCsv'])->name('relatorios.fluxo-caixa.csv');
    Route::get('relatorios/fluxo-caixa/pdf',            [RelatorioController::class, 'fluxoCaixaPdf'])->name('relatorios.fluxo-caixa.pdf');
    Route::get('relatorios/historico-cliente/csv',      [RelatorioController::class, 'historicoClienteCsv'])->name('relatorios.historico-cliente.csv');
    Route::get('relatorios/historico-cliente/pdf',      [RelatorioController::class, 'historicoClientePdf'])->name('relatorios.historico-cliente.pdf');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
