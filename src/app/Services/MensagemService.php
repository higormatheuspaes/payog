<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\LogMensagem;
use App\Models\Parcela;
use Twilio\Rest\Client;

class MensagemService
{
    private Client $twilio;

    public function __construct()
    {
        $this->twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token'),
        );
    }

    public function enviarLembreteAntes(Parcela $parcela): void
    {
        $empresa = $parcela->cobranca->empresa;
        $cliente = $parcela->cobranca->cliente;

        if (! $this->notificacaoAtiva($empresa, 'lembrete_antes')) {
            return;
        }

        $dias  = $empresa->notif_lembrete_antes_dias ?? 3;
        $texto = "Olá, {$cliente->nome}! 👋\n\n"
            . "Este é um lembrete da *{$empresa->nome}*.\n\n"
            . "Você tem uma parcela de *R$ " . number_format($parcela->valor, 2, ',', '.') . "* "
            . "com vencimento em *" . $parcela->vencimento->format('d/m/Y') . "*.\n\n"
            . ($parcela->codigo_boleto ? "*Código:* {$parcela->codigo_boleto}\n\n" : '')
            . "Qualquer dúvida, entre em contato conosco.";

        $this->enviar($empresa, $cliente, $parcela, 'lembrete_antes', $texto);
    }

    public function enviarLembreteDia(Parcela $parcela): void
    {
        $empresa = $parcela->cobranca->empresa;
        $cliente = $parcela->cobranca->cliente;

        if (! $this->notificacaoAtiva($empresa, 'lembrete_dia')) {
            return;
        }

        $texto = "Olá, {$cliente->nome}! 👋\n\n"
            . "Sua parcela de *R$ " . number_format($parcela->valor, 2, ',', '.') . "* "
            . "com a *{$empresa->nome}* vence *hoje*.\n\n"
            . "Não esqueça de realizar o pagamento!"
            . ($parcela->codigo_boleto ? "\n\n*Código:* {$parcela->codigo_boleto}" : '');

        $this->enviar($empresa, $cliente, $parcela, 'lembrete_dia', $texto);
    }

    public function enviarAvisoAtraso(Parcela $parcela): void
    {
        $empresa = $parcela->cobranca->empresa;
        $cliente = $parcela->cobranca->cliente;

        if (! $this->notificacaoAtiva($empresa, 'aviso_atraso')) {
            return;
        }

        $diasAtraso = (int) $parcela->vencimento->diffInDays(now());
        $texto = "Olá, {$cliente->nome}.\n\n"
            . "Identificamos que sua parcela de *R$ " . number_format($parcela->valor, 2, ',', '.') . "* "
            . "com a *{$empresa->nome}* está em atraso há *{$diasAtraso} dia(s)*.\n\n"
            . ($parcela->codigo_boleto ? "*Código:* {$parcela->codigo_boleto}\n\n" : '')
            . "Por favor, entre em contato para regularizar sua situação.";

        $this->enviar($empresa, $cliente, $parcela, 'aviso_atraso', $texto);
    }

    public function enviarConfirmacaoPagamento(Parcela $parcela): void
    {
        $empresa = $parcela->cobranca->empresa;
        $cliente = $parcela->cobranca->cliente;

        if (! $this->notificacaoAtiva($empresa, 'confirmacao_pagamento')) {
            return;
        }

        $texto = "Olá, {$cliente->nome}! ✅\n\n"
            . "Seu pagamento de *R$ " . number_format($parcela->valor, 2, ',', '.') . "* "
            . "para *{$empresa->nome}* foi confirmado.\n\n"
            . "Obrigado!";

        $this->enviar($empresa, $cliente, $parcela, 'confirmacao_pagamento', $texto);
    }

    private function enviar(Empresa $empresa, $cliente, ?Parcela $parcela, string $tipo, string $texto): void
    {
        $telefone = $this->formatarTelefone($cliente->telefone);

        if (! (new ConsumoService)->podEnviar($empresa)) {
            LogMensagem::create([
                'empresa_id'    => $empresa->id,
                'cliente_id'    => $cliente->id,
                'parcela_id'    => $parcela?->id,
                'tipo'          => $tipo,
                'telefone'      => $telefone,
                'mensagem'      => $texto,
                'status'        => 'erro',
                'erro_detalhes' => 'Envios pausados: teto de gasto com excedente atingido.',
                'enviado_em'    => now(),
            ]);
            return;
        }

        try {
            $this->twilio->messages->create(
                "whatsapp:{$telefone}",
                [
                    'from' => config('services.twilio.whatsapp_from'),
                    'body' => $texto,
                ]
            );

            LogMensagem::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'parcela_id' => $parcela?->id,
                'tipo'       => $tipo,
                'telefone'   => $telefone,
                'mensagem'   => $texto,
                'status'     => 'enviado',
                'enviado_em' => now(),
            ]);

            (new ConsumoService)->registrarEnvio($empresa);
        } catch (\Exception $e) {
            LogMensagem::create([
                'empresa_id'     => $empresa->id,
                'cliente_id'     => $cliente->id,
                'parcela_id'     => $parcela?->id,
                'tipo'           => $tipo,
                'telefone'       => $telefone,
                'mensagem'       => $texto,
                'status'         => 'erro',
                'erro_detalhes'  => $e->getMessage(),
                'enviado_em'     => now(),
            ]);
        }
    }

    private function notificacaoAtiva(Empresa $empresa, string $tipo): bool
    {
        return (bool) $empresa->{"notif_{$tipo}_ativo"};
    }

    private function formatarTelefone(string $telefone): string
    {
        $numero = preg_replace('/\D/', '', $telefone);

        if (strlen($numero) === 10 || strlen($numero) === 11) {
            $numero = '55' . $numero;
        }

        return '+' . $numero;
    }
}
