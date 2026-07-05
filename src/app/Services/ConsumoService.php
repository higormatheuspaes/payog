<?php

namespace App\Services;

use App\Models\ConsumoMensagensMes;
use App\Models\Empresa;
use App\Notifications\AlertaConsumoMensagens;
use Illuminate\Support\Facades\DB;

class ConsumoService
{
    const VALOR_EXCEDENTE_POR_MSG = 0.20;

    public function consumoAtual(Empresa $empresa): ConsumoMensagensMes
    {
        $ciclo = now()->startOfMonth()->toDateString();

        return ConsumoMensagensMes::firstOrCreate(
            ['empresa_id' => $empresa->id, 'ciclo_referencia' => $ciclo],
            [
                'mensagens_enviadas'        => 0,
                'mensagens_excedentes'      => 0,
                'valor_excedente_acumulado' => 0,
                'teto_gasto_excedente'      => $empresa->teto_gasto_excedente,
                'envios_pausados'           => false,
            ]
        );
    }

    public function podEnviar(Empresa $empresa): bool
    {
        $consumo = $this->consumoAtual($empresa);

        if ($consumo->envios_pausados) {
            return false;
        }

        if ($consumo->teto_gasto_excedente !== null
            && (float) $consumo->valor_excedente_acumulado >= (float) $consumo->teto_gasto_excedente) {
            $consumo->update(['envios_pausados' => true]);
            return false;
        }

        return true;
    }

    public function registrarEnvio(Empresa $empresa): void
    {
        $empresa->loadMissing('plano');
        $limite = $empresa->plano?->limite_mensagens_mes ?? PHP_INT_MAX;
        $ciclo  = now()->startOfMonth()->toDateString();

        $alertas = [];

        DB::transaction(function () use ($empresa, $limite, $ciclo, &$alertas) {
            $consumo = ConsumoMensagensMes::firstOrCreate(
                ['empresa_id' => $empresa->id, 'ciclo_referencia' => $ciclo],
                [
                    'mensagens_enviadas'        => 0,
                    'mensagens_excedentes'      => 0,
                    'valor_excedente_acumulado' => 0,
                    'teto_gasto_excedente'      => $empresa->teto_gasto_excedente,
                    'envios_pausados'           => false,
                ]
            );

            $anterior = (int) $consumo->mensagens_enviadas;
            $enviadas = $anterior + 1;
            $excedentes = max(0, $enviadas - $limite);
            $valor      = round($excedentes * self::VALOR_EXCEDENTE_POR_MSG, 2);
            $pausar     = $consumo->teto_gasto_excedente !== null
                && $valor >= (float) $consumo->teto_gasto_excedente;

            $consumo->update([
                'mensagens_enviadas'        => $enviadas,
                'mensagens_excedentes'      => $excedentes,
                'valor_excedente_acumulado' => $valor,
                'envios_pausados'           => $pausar,
            ]);

            // Gatilho: atingiu 80% do limite (apenas uma vez, ao cruzar o threshold)
            $limite80 = (int) ($limite * 0.8);
            if ($limite !== PHP_INT_MAX && $anterior < $limite80 && $enviadas >= $limite80) {
                $alertas[] = [AlertaConsumoMensagens::TIPO_80_PORCENTO, $enviadas, $limite, 0.0];
            }

            // Gatilho: primeira mensagem em excedente
            if ($anterior <= $limite && $enviadas > $limite) {
                $alertas[] = [AlertaConsumoMensagens::TIPO_EXCEDENTE, $enviadas, $limite, $valor];
            }

            // Gatilho: teto de gasto atingido (primeira vez que pausa)
            if ($pausar && ! (bool) $consumo->getOriginal('envios_pausados')) {
                $alertas[] = [AlertaConsumoMensagens::TIPO_TETO, $enviadas, $limite, $valor];
            }
        });

        // Envia alertas fora da transação para não atrasar o commit
        if ($alertas) {
            $empresa->loadMissing('users');
            $user = $empresa->users()->first();
            if ($user) {
                foreach ($alertas as [$tipo, $enviadas, $limite, $valor]) {
                    $user->notify(new AlertaConsumoMensagens($tipo, $enviadas, $limite, $valor));
                }
            }
        }
    }
}
