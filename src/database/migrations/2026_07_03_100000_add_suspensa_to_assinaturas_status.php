<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE assinaturas MODIFY COLUMN status ENUM('trial','pendente','ativa','inadimplente','suspensa','cancelada') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE assinaturas MODIFY COLUMN status ENUM('trial','pendente','ativa','inadimplente','cancelada') NOT NULL");
    }
};
