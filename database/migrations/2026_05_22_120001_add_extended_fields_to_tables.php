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
        Schema::table('docentes', function (Blueprint $table) {
            $table->string('complemento', 10)->nullable()->after('ci');
            $table->string('correo')->nullable()->after('complemento');
            $table->string('telefono')->nullable()->after('correo');
        });

        Schema::table('facturacions', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('hospital_practica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->dropColumn(['complemento', 'correo', 'telefono']);
        });

        Schema::table('facturacions', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};
