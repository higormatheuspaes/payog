<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('abacatepay_customer_id')->nullable()->after('teto_gasto_excedente');
            $table->string('abacatepay_subscription_id')->nullable()->after('abacatepay_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['abacatepay_customer_id', 'abacatepay_subscription_id']);
        });
    }
};
