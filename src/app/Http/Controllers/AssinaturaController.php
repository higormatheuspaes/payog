<?php

namespace App\Http\Controllers;

use App\Services\AbacatePayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AssinaturaController extends Controller
{
    public function gerarCheckout(): RedirectResponse
    {
        $empresa = auth()->user()->empresa;
        $plano   = $empresa->plano;

        if (! $empresa->abacatepay_customer_id || ! $plano?->abacatepay_product_id) {
            return back()->withErrors(['msg' => 'Dados de assinatura incompletos. Entre em contato com o suporte.']);
        }

        try {
            $abacate = new AbacatePayService;

            // Cancela a assinatura anterior para evitar cobranças duplicadas
            if ($empresa->abacatepay_subscription_id) {
                try {
                    $abacate->cancelarAssinatura($empresa->abacatepay_subscription_id);
                } catch (\Exception) {
                    // ignora se já estava cancelada no gateway
                }
            }

            // Sufixo único para evitar conflito de externalId no AbacatePay
            $externalId = 'payog-empresa-' . $empresa->id . '-' . time();

            $subscription = $abacate->criarAssinatura(
                customerId:    $empresa->abacatepay_customer_id,
                productId:     $plano->abacatepay_product_id,
                externalId:    $externalId,
                completionUrl: route('assinatura.aguardando', absolute: true),
            );

            Log::info('AbacatePay checkout gerado', [
                'empresa_id'  => $empresa->id,
                'external_id' => $externalId,
                'checkout_id' => $subscription['id'],
                'url'         => $subscription['url'],
            ]);

            $empresa->update(['abacatepay_subscription_id' => $subscription['id']]);
            $empresa->assinatura?->update(['gateway_assinatura_id_externo' => $subscription['id']]);

            return redirect($subscription['url']);
        } catch (\Exception $e) {
            Log::error('AbacatePay checkout falhou', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);

            return back()->withErrors(['msg' => 'Não foi possível gerar o link de pagamento. Tente novamente em instantes.']);
        }
    }
}
