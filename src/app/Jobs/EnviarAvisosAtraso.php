<?php

namespace App\Jobs;

use App\Models\Parcela;
use App\Services\MensagemService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnviarAvisosAtraso implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 300;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MensagemService $service): void
    {
        Parcela::with('cobranca.empresa', 'cobranca.cliente')
            ->where(fn($q) => $q
                ->where('status', 'atrasado')
                ->orWhere(fn($q) => $q
                    ->where('status', 'pendente')
                    ->whereDate('vencimento', '<', today())
                )
            )
            ->whereHas('cobranca.empresa', fn($q) => $q->where('notif_aviso_atraso_ativo', true))
            ->chunk(200, function ($parcelas) use ($service) {
                foreach ($parcelas as $parcela) {
                    $service->enviarAvisoAtraso($parcela);
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job EnviarAvisosAtraso falhou definitivamente', ['error' => $e->getMessage()]);
    }
}
