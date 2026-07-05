<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // parcelas: queries de jobs (lembretes, avisos, score) e listagens
        Schema::table('parcelas', function (Blueprint $table) {
            $table->index(['status', 'vencimento'], 'parcelas_status_vencimento_idx');
            $table->index('cobranca_id', 'parcelas_cobranca_id_idx');
        });

        // cobrancas: listagens por empresa e tipo (recorrente)
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->index(['empresa_id', 'tipo'], 'cobrancas_empresa_tipo_idx');
            $table->index('cliente_id', 'cobrancas_cliente_id_idx');
        });

        // clientes: listagens, busca e filtro por score
        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['empresa_id', 'score_categoria'], 'clientes_empresa_score_idx');
        });

        // consumo_mensagens_mes: verificado em cada envio de mensagem
        Schema::table('consumo_mensagens_mes', function (Blueprint $table) {
            $table->index(['empresa_id', 'ciclo_referencia'], 'consumo_empresa_ciclo_idx');
        });

        // log_mensagens: listagem paginada com filtros
        Schema::table('log_mensagens', function (Blueprint $table) {
            $table->index(['empresa_id', 'enviado_em'], 'log_mensagens_empresa_enviado_idx');
            $table->index('cliente_id', 'log_mensagens_cliente_id_idx');
            $table->index('parcela_id', 'log_mensagens_parcela_id_idx');
        });

        // historico_status_parcela: exibido na ficha do cliente
        Schema::table('historico_status_parcela', function (Blueprint $table) {
            $table->index('parcela_id', 'historico_parcela_id_idx');
        });

        // score_historico: sidebar do cliente
        Schema::table('score_historico', function (Blueprint $table) {
            $table->index('cliente_id', 'score_historico_cliente_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropIndex('parcelas_status_vencimento_idx');
            $table->dropIndex('parcelas_cobranca_id_idx');
        });
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropIndex('cobrancas_empresa_tipo_idx');
            $table->dropIndex('cobrancas_cliente_id_idx');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_empresa_score_idx');
        });
        Schema::table('consumo_mensagens_mes', function (Blueprint $table) {
            $table->dropIndex('consumo_empresa_ciclo_idx');
        });
        Schema::table('log_mensagens', function (Blueprint $table) {
            $table->dropIndex('log_mensagens_empresa_enviado_idx');
            $table->dropIndex('log_mensagens_cliente_id_idx');
            $table->dropIndex('log_mensagens_parcela_id_idx');
        });
        Schema::table('historico_status_parcela', function (Blueprint $table) {
            $table->dropIndex('historico_parcela_id_idx');
        });
        Schema::table('score_historico', function (Blueprint $table) {
            $table->dropIndex('score_historico_cliente_id_idx');
        });
    }
};
