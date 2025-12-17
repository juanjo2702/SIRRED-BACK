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
            $table->text('qr_url')->nullable()->after('texto_completo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datos_factura', function (Blueprint $table) {
            $table->dropColumn('qr_url');
        });
    }
};
