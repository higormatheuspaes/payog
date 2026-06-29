<?php

namespace App\Console\Commands;

use App\Models\Parcela;
use App\Services\ScoreService;
use Illuminate\Console\Command;

class AtualizarScoresAtrasados extends Command
{
    protected $signature   = 'score:atualizar-atrasados';
    protected $description = 'Aplica penalidade de score em parcelas vencidas e não pagas';

    public function handle(ScoreService $score): void
    {
        $parcelas = Parcela::with(['cobranca.cliente'])
            ->where('status', 'pendente')
            ->whereDate('vencimento', '<', now())
            ->get();

        $total = 0;
        foreach ($parcelas as $parcela) {
            $score->aplicarAtraso($parcela);
            $total++;
        }

        $this->info("Score atualizado para {$total} parcela(s) vencida(s).");
    }
}
