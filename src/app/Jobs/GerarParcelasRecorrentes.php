<?php

namespace App\Jobs;

use App\Models\Cobranca;
use App\Models\Parcela;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GerarParcelasRecorrentes implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 300;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $proximoMes = now()->addMonth()->startOfMonth();

        // Cobranças recorrentes que têm parcela vencendo no mês atual ou anterior,
        // mas ainda não têm parcela gerada para o próximo mês.
        Cobranca::where('tipo', 'recorrente')
            ->whereHas('parcelas', fn($q) => $q
                ->whereNotIn('status', ['cancelado'])
                ->whereDate('vencimento', '<', $proximoMes->toDateString())
            )
            ->whereDoesntHave('parcelas', fn($q) => $q
                ->whereDate('vencimento', '>=', $proximoMes->toDateString())
            )
            ->with(['parcelas' => fn($q) => $q
                ->whereNotIn('status', ['cancelado'])
                ->orderByDesc('vencimento')
                ->orderByDesc('numero')
            ])
            ->chunk(200, function ($cobrancas) {
                foreach ($cobrancas as $cobranca) {
                    $this->gerarProximaParcela($cobranca);
                }
            });
    }

    private function gerarProximaParcela(Cobranca $cobranca): void
    {
        $ultima = $cobranca->parcelas->first();

        if (! $ultima) {
            return;
        }

        // Só estende se a última parcela não foi cancelada
        if ($ultima->status === 'cancelado') {
            return;
        }

        DB::transaction(function () use ($cobranca, $ultima) {
            Parcela::create([
                'cobranca_id' => $cobranca->id,
                'numero'      => $ultima->numero + 1,
                'valor'       => $ultima->valor,
                'vencimento'  => $ultima->vencimento->addMonth()->toDateString(),
                'origem'      => 'automatica',
                'status'      => 'pendente',
            ]);
        });

        Log::info('Parcela recorrente gerada', [
            'cobranca_id' => $cobranca->id,
            'numero'      => $ultima->numero + 1,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job GerarParcelasRecorrentes falhou definitivamente', ['error' => $e->getMessage()]);
    }
}
