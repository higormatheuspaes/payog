<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE empresas MODIFY COLUMN status_assinatura ENUM('trial','pendente','ativo','suspenso','cancelado') NOT NULL DEFAULT 'trial'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE empresas MODIFY COLUMN status_assinatura ENUM('trial','ativa','inadimplente','suspensa','cancelada') NOT NULL DEFAULT 'trial'");
    }
};
