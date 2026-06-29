<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreHistorico extends Model
{
    protected $table = 'score_historico';

    protected $fillable = [
        'cliente_id',
        'parcela_id',
        'pontos_aplicados',
        'score_resultante',
    ];

    protected function casts(): array
    {
        return [
            'pontos_aplicados' => 'integer',
            'score_resultante' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }
}
