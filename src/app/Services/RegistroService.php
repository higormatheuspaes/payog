<?php

namespace App\Services;

use App\Models\Assinatura;
use App\Models\Empresa;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegistroService
{
    public function registrar(array $dados): User
    {
        return DB::transaction(function () use ($dados) {
            $plano = Plano::where('nome', 'Básico')->firstOrFail();

            $empresa = Empresa::create([
                'nome'               => $dados['nome_empresa'],
                'cnpj_cpf'           => $dados['cnpj_cpf'],
                'telefone'           => $dados['telefone'],
                'email'              => $dados['email'],
                'plano_id'           => $plano->id,
                'status_assinatura'  => 'trial',
            ]);

            $user = User::create([
                'name'       => $dados['name'],
                'email'      => $dados['email'],
                'password'   => Hash::make($dados['password']),
                'empresa_id' => $empresa->id,
            ]);

            Assinatura::create([
                'empresa_id' => $empresa->id,
                'status'     => 'trial',
            ]);

            return $user;
        });
    }
}
