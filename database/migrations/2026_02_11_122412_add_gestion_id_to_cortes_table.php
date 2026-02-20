<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create a default legacy gestion (e.g., 2-2025)
        // We use DB facade to ensure data exists before adding the constraint
        $defaultId = DB::table('gestions')->insertGetId([
            'nombre' => 'Gestión 2-2025',
            'fecha_inicio' => '2025-07-01',
            'fecha_fin' => '2025-12-31',
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('cortes', function (Blueprint $table) use ($defaultId) {
            // Add nullable first
            $table->foreignId('gestion_id')->nullable()->after('id')->constrained('gestions');
        });

        // 2. Assign all existing cortes to this default gestion
        DB::table('cortes')->update(['gestion_id' => $defaultId]);

        // Note: We leave it nullable in schema definition to avoid SQLite/driver issues with "adding not null to not empty table",
        // but functionally all records now have an ID.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cortes', function (Blueprint $table) {
            $table->dropForeign(['gestion_id']);
            $table->dropColumn('gestion_id');
        });
    }
};
