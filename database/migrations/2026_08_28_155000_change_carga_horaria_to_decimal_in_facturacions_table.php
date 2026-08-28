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
        Schema::table('facturacions', function (Blueprint $table) {
            $table->decimal('carga_horaria', 10, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturacions', function (Blueprint $table) {
            $table->integer('carga_horaria')->default(0)->change();
        });
    }
};
