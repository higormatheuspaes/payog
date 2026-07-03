<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empresa extends Model
{
    protected $fillable = [
        'nome',
        'cnpj_cpf',
        'email',
        'telefone',
        'logo_path',
        'plano_id',
        'status_assinatura',
        'teto_gasto_excedente',
        'notificacoes_ativas',
        'dias_antes_vencimento',
        'frequencia_aviso_atraso',
        'notif_lembrete_antes_ativo',
        'notif_lembrete_antes_dias',
        'notif_lembrete_dia_ativo',
        'notif_aviso_atraso_ativo',
        'notif_confirmacao_pagamento_ativo',
        'abacatepay_customer_id',
        'abacatepay_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'status_assinatura'     => 'string',
            'teto_gasto_excedente'  => 'decimal:2',
            'notificacoes_ativas'   => 'boolean',
            'dias_antes_vencimento' => 'integer',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function integracoesGateway(): HasMany
    {
        return $this->hasMany(IntegracaoGateway::class);
    }

    public function assinatura(): HasOne
    {
        return $this->hasOne(Assinatura::class);
    }

    public function consumoMensagensMes(): HasMany
    {
        return $this->hasMany(ConsumoMensagensMes::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
