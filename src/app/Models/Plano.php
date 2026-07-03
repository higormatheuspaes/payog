<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    protected $fillable = [
        'nome',
        'limite_mensagens_mes',
        'valor_mensal',
        'abacatepay_product_id',
    ];

    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
        ];
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }
}
