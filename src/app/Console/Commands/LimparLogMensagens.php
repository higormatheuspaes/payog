<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimparLogMensagens extends Command
{
    protected $signature   = 'mensagens:limpar-log {--dias=90 : Dias de retenção}';
    protected $description = 'Remove log_mensagens mais antigos que N dias';

    public function handle(): int
    {
        $dias    = (int) $this->option('dias');
        $corte   = now()->subDays($dias)->toDateTimeString();

        $deletados = DB::table('log_mensagens')
            ->where('enviado_em', '<', $corte)
            ->delete();

        $this->info("Log de mensagens: {$deletados} registros removidos (>{$dias} dias).");

        return Command::SUCCESS;
    }
}
