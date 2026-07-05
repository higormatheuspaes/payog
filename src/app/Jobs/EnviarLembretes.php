<?php

namespace App\Jobs;

use App\Models\Parcela;
use App\Services\MensagemService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnviarLembretes implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 300;

    /** Segundos de espera entre tentativas: 1min, 5min, 15min */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MensagemService $service): void
    {
        // Lembrete no dia do vencimento
        Parcela::with('cobranca.empresa', 'cobranca.cliente')
            ->where('status', 'pendente')
            ->whereDate('vencimento', today())
            ->chunk(200, function ($parcelas) use ($service) {
                foreach ($parcelas as $parcela) {
                    $service->enviarLembreteDia($parcela);
                }
            });

        // Lembrete X dias antes (conforme configuração de cada empresa)
        Parcela::with('cobranca.empresa', 'cobranca.cliente')
            ->where('status', 'pendente')
            ->whereDate('vencimento', '>', today())
            ->whereHas('cobranca.empresa', fn($q) => $q->where('notif_lembrete_antes_ativo', true))
            ->chunk(200, function ($parcelas) use ($service) {
                foreach ($parcelas as $parcela) {
                    $dias = $parcela->cobranca->empresa->notif_lembrete_antes_dias ?? 3;
                    if ($parcela->vencimento->diffInDays(today()) === $dias) {
                        $service->enviarLembreteAntes($parcela);
                    }
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job EnviarLembretes falhou definitivamente', ['error' => $e->getMessage()]);
    }
}
