<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Parcela;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CobrancaService
{
    public function listar(string $busca = '', string $tipo = ''): LengthAwarePaginator
    {
        return Cobranca::with(['cliente', 'parcelas'])
            ->where('empresa_id', Auth::user()->empresa_id)
            ->when($busca, fn($q) => $q->whereHas('cliente', fn($q) =>
                $q->where('nome', 'like', "%{$busca}%")
            ))
            ->when($tipo, fn($q) => $q->where('tipo', $tipo))
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function criar(array $dados): Cobranca
    {
        return DB::transaction(function () use ($dados) {
            $cobranca = Cobranca::create([
                'empresa_id'      => Auth::user()->empresa_id,
                'cliente_id'      => $dados['cliente_id'],
                'descricao'       => $dados['descricao'],
                'tipo'            => $dados['tipo'],
                'valor_total'     => $dados['valor_total'],
                'numero_parcelas' => $dados['numero_parcelas'],
                'periodicidade'   => $dados['tipo'] === 'recorrente' ? 'mensal' : null,
            ]);

            if (!empty($dados['parcelas'])) {
                $this->salvarParcelasCustomizadas($cobranca, $dados['parcelas']);
            } else {
                $this->gerarParcelas($cobranca, $dados['data_primeiro_vencimento']);
            }

            return $cobranca;
        });
    }

    private function salvarParcelasCustomizadas(Cobranca $cobranca, array $parcelas): void
    {
        foreach ($parcelas as $p) {
            Parcela::create([
                'cobranca_id' => $cobranca->id,
                'numero'      => $p['numero'],
                'valor'       => $p['valor'],
                'vencimento'  => $p['vencimento'],
                'origem'      => 'automatica',
                'status'      => 'pendente',
            ]);
        }
    }

	public function cancelar(Cobranca $cobranca): void
    {
        DB::transaction(function () use ($cobranca) {
            $cobranca->parcelas()
                ->where('status', 'pendente')
                ->update(['status' => 'cancelado']);
        });
    }

	private function gerarParcelas(Cobranca $cobranca, string $dataInicial): void
    {
        $valorParcela = round($cobranca->valor_total / $cobranca->numero_parcelas, 2);
        $totalGerado  = $valorParcela * ($cobranca->numero_parcelas - 1);
        $ultimoValor  = round($cobranca->valor_total - $totalGerado, 2);

        $vencimento = \Carbon\Carbon::parse($dataInicial);

        for ($i = 1; $i <= $cobranca->numero_parcelas; $i++) {
            $isUltima = $i === $cobranca->numero_parcelas;

            Parcela::create([
                'cobranca_id' => $cobranca->id,
                'numero'      => $i,
                'valor'       => $isUltima ? $ultimoValor : $valorParcela,
                'vencimento'  => $vencimento->toDateString(),
                'origem'      => 'automatica',
                'status'      => 'pendente',
            ]);

            $vencimento->addMonth();
        }
    }

}