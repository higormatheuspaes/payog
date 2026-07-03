<?php

namespace App\Console\Commands;

use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Console\Command;

class AbacatePaySetupProducts extends Command
{
    protected $signature   = 'abacatepay:setup-products {--force : Recriar mesmo que já tenha ID}';
    protected $description = 'Cria os produtos de assinatura no AbacatePay e salva os IDs na tabela planos';

    public function handle(AbacatePayService $abacate): int
    {
        $planos = Plano::all();

        if ($planos->isEmpty()) {
            $this->error('Nenhum plano encontrado. Rode os seeders primeiro.');
            return self::FAILURE;
        }

        foreach ($planos as $plano) {
            if ($plano->abacatepay_product_id && ! $this->option('force')) {
                $this->line("  <fg=yellow>SKIP</> {$plano->nome} — já tem ID: {$plano->abacatepay_product_id}");
                continue;
            }

            $externalId = 'payog-' . str($plano->nome)->slug();
            $centavos   = (int) round($plano->valor_mensal * 100);

            $this->line("  Criando produto: <fg=cyan>{$plano->nome}</> (R$ {$plano->valor_mensal} / mês)...");

            try {
                $produto = $abacate->criarProduto($externalId, "Payog {$plano->nome}", $centavos);
                $plano->update(['abacatepay_product_id' => $produto['id']]);
                $this->line("  <fg=green>OK</> ID: {$produto['id']}");
            } catch (\Exception $e) {
                $this->error("  ERRO em {$plano->nome}: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Produtos criados e IDs salvos na tabela planos.');
        return self::SUCCESS;
    }
}
