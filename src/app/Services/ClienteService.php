<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;


class ClienteService
{
	public function criar(array $dados): Cliente
	{
		return Cliente::create([
			'empresa_id'		=>Auth::user()->empresa_id,
			'nome'				=>$dados['nome'],
			'telefone'			=>$dados['telefone'],
			'cpf_cnpj'			=>$dados['cpf_cnpj'] ?? null,
			'email'				=>$dados['email'] ?? null,
			'score_atual'		=>100,
			'score_categoria'	=>'bom_pagador',
		]);
	}

	public function atualizar(Cliente $cliente, array $dados): Cliente
	{
		$cliente->update([
			'nome'				=>$dados['nome'],
			'telefone'			=>$dados['telefone'],
			'cpf_cnpj'			=>$dados['cpf_cnpj'],
			'email'				=>$dados['email'],
		]);

		return $cliente->fresh();
	}

	public function excluir(Cliente $cliente): void
	{
        if ($cliente->cobrancas()->exists()) {
            throw new \RuntimeException('Não é possível excluir um cliente com cobranças vinculadas.');
        }

		$cliente->delete();
	}

	public function listar(string $busca = '', string $categoria = ''): LengthAwarePaginator
	{
		return Cliente::where('empresa_id', Auth::user()->empresa_id)
			->when($busca, fn($q) => $q->where(function ($q) use ($busca) {
				$q->where('nome', 'like', "%{$busca}%")
				->orWhere('telefone', 'like', "%{$busca}%")
				->orWhere('cpf_cnpj', 'like', "%{$busca}%");
			}))
			->when($categoria, fn($q) => $q->where('score_categoria', $categoria))
			->orderBy('nome')
			->paginate(15);
	}
}
