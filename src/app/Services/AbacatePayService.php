<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AbacatePayService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.abacatepay.url'), '/');
        $this->apiKey  = config('services.abacatepay.key');
    }

    public function criarProduto(string $externalId, string $nome, int $centavos, string $ciclo = 'MONTHLY'): array
    {
        return $this->post('/products/create', [
            'externalId' => $externalId,
            'name'       => $nome,
            'price'      => $centavos,
            'currency'   => 'BRL',
            'cycle'      => $ciclo,
        ]);
    }

    public function criarCustomer(string $nome, string $email, string $cpfCnpj, string $telefone): array
    {
        return $this->post('/customers/create', [
            'name'      => $nome,
            'email'     => $email,
            'taxId'     => preg_replace('/\D/', '', $cpfCnpj),
            'cellphone' => $this->formatarTelefone($telefone),
        ]);
    }

    public function criarAssinatura(string $customerId, string $productId, string $externalId, string $completionUrl): array
    {
        return $this->post('/subscriptions/create', [
            'items'         => [['id' => $productId, 'quantity' => 1]],
            'customerId'    => $customerId,
            'externalId'    => $externalId,
            'completionUrl' => $completionUrl,
            'methods'       => ['CARD'],
            'retryPolicy'   => ['maxRetry' => 3, 'retryEvery' => 2],
        ]);
    }

    public function cancelarAssinatura(string $subscriptionId): array
    {
        return $this->post('/subscriptions/cancel', ['id' => $subscriptionId]);
    }

    private function post(string $endpoint, array $data): array
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post($this->baseUrl . $endpoint, $data);

        $this->assertSuccess($response, $endpoint);

        return $response->json('data');
    }

    private function assertSuccess(Response $response, string $endpoint): void
    {
        if ($response->failed() || ! $response->json('success')) {
            $error = $response->json('error') ?? $response->body();
            throw new \RuntimeException("AbacatePay [{$endpoint}]: {$error}");
        }
    }

    private function formatarTelefone(string $telefone): string
    {
        $digits = preg_replace('/\D/', '', $telefone);
        return '+' . (str_starts_with($digits, '55') ? $digits : '55' . $digits);
    }
}
