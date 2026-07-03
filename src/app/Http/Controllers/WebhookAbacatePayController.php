<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Plano;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookAbacatePayController extends Controller
{
    public function handle(Request $request): Response
    {
        if (! $this->verificarAssinatura($request)) {
            Log::warning('AbacatePay webhook: assinatura inválida', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $evento  = $payload['event'] ?? null;
        $data    = $payload['data']  ?? [];

        Log::info('AbacatePay webhook recebido', ['event' => $evento]);

        match ($evento) {
            'subscription.completed'       => $this->subscriptionCompleted($data),
            'subscription.renewed'         => $this->subscriptionRenewed($data),
            'subscription.cancelled'       => $this->subscriptionCancelled($data),
            'subscription.overdue',
            'subscription.payment_failed'  => $this->subscriptionSuspended($data),
            default                        => null,
        };

        return response('OK', 200);
    }

    private function subscriptionCompleted(array $data): void
    {
        $empresa = $this->resolverEmpresa($data);
        if (! $empresa) return;

        $productId = $data['checkout']['items'][0]['id'] ?? null;
        $plano     = $productId ? Plano::where('abacatepay_product_id', $productId)->first() : null;
        $subId     = $data['subscription']['id'] ?? null;

        $empresa->update([
            'status_assinatura'          => 'ativo',
            'plano_id'                   => $plano?->id ?? $empresa->plano_id,
            'abacatepay_subscription_id' => $subId ?? $empresa->abacatepay_subscription_id,
        ]);

        $empresa->assinatura?->update([
            'status'                        => 'ativa',
            'gateway_assinatura_id_externo' => $subId,
        ]);
    }

    private function subscriptionRenewed(array $data): void
    {
        $empresa = $this->resolverEmpresa($data);
        if (! $empresa) return;

        // Sincroniza o plano caso tenha sido alterado direto no painel AbacatePay
        $productId = $data['checkout']['items'][0]['id'] ?? $data['subscription']['productId'] ?? null;
        $plano     = $productId ? Plano::where('abacatepay_product_id', $productId)->first() : null;

        $empresa->update([
            'status_assinatura' => 'ativo',
            'plano_id'          => $plano?->id ?? $empresa->plano_id,
        ]);
        $empresa->assinatura?->update(['status' => 'ativa']);
    }

    private function subscriptionCancelled(array $data): void
    {
        $empresa = $this->resolverEmpresa($data);
        if (! $empresa) return;

        $empresa->update(['status_assinatura' => 'cancelado']);
        $empresa->assinatura?->update(['status' => 'cancelada']);
    }

    private function subscriptionSuspended(array $data): void
    {
        $empresa = $this->resolverEmpresa($data);
        if (! $empresa) return;

        $empresa->update(['status_assinatura' => 'suspenso']);
        $empresa->assinatura?->update(['status' => 'suspensa']);
    }

    private function resolverEmpresa(array $data): ?Empresa
    {
        // Tenta pelo subscription ID
        $subId = $data['subscription']['id'] ?? null;
        if ($subId) {
            $empresa = Empresa::where('abacatepay_subscription_id', $subId)->first();
            if ($empresa) return $empresa;
        }

        // Fallback: externalId em checkout ou payment, formato "payog-empresa-{id}"
        $externalId = $data['checkout']['externalId']
            ?? $data['payment']['externalId']
            ?? null;

        if ($externalId && str_starts_with($externalId, 'payog-empresa-')) {
            // Formato: "payog-empresa-{id}" ou "payog-empresa-{id}-{timestamp}"
            $parte     = str_replace('payog-empresa-', '', $externalId);
            $empresaId = (int) explode('-', $parte)[0];
            return Empresa::find($empresaId);
        }

        Log::warning('AbacatePay webhook: empresa não encontrada', ['data' => $data]);
        return null;
    }

    private function verificarAssinatura(Request $request): bool
    {
        $secret = config('services.abacatepay.webhook_secret');

        if (empty($secret)) {
            if (app()->isProduction()) {
                Log::critical('AbacatePay webhook bloqueado: ABACATEPAY_WEBHOOK_SECRET não configurado em produção');
                return false;
            }
            Log::warning('AbacatePay webhook: ABACATEPAY_WEBHOOK_SECRET não configurado (ok em dev)');
            return true;
        }

        $signature = $request->header('X-Webhook-Signature');
        if (! $signature) return false;

        $expected = base64_encode(
            hash_hmac('sha256', $request->getContent(), $secret, true)
        );

        return hash_equals($expected, $signature);
    }
}
