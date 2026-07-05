<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Converte qualquer registro trial para pendente antes de remover o valor do enum
        DB::table('empresas')->where('status_assinatura', 'trial')->update(['status_assinatura' => 'pendente']);
        DB::table('assinaturas')->where('status', 'trial')->update(['status' => 'pendente']);

        DB::statement("ALTER TABLE empresas MODIFY COLUMN status_assinatura ENUM('pendente','ativo','suspenso','cancelado') NOT NULL DEFAULT 'pendente'");
        DB::statement("ALTER TABLE assinaturas MODIFY COLUMN status ENUM('pendente','ativa','inadimplente','suspensa','cancelada') NOT NULL DEFAULT 'pendente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE empresas MODIFY COLUMN status_assinatura ENUM('trial','pendente','ativo','suspenso','cancelado') NOT NULL DEFAULT 'trial'");
        DB::statement("ALTER TABLE assinaturas MODIFY COLUMN status ENUM('trial','pendente','ativa','inadimplente','suspensa','cancelada') NOT NULL DEFAULT 'trial'");
    }
};
