<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('datos_factura', function (Blueprint $table) {
            $table->string('nit_cliente', 20)->nullable()->after('razon_social_emisor');
            $table->string('razon_social_cliente')->nullable()->after('nit_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datos_factura', function (Blueprint $table) {
            $table->dropColumn(['nit_cliente', 'razon_social_cliente']);
        });
    }
};
