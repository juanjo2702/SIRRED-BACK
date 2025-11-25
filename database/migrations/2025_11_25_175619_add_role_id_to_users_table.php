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
        // Add role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('ci')->constrained('roles')->onDelete('cascade');
        });

        // Migrate existing role data
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $roleName = $user->role ?? 'user';
            $role = DB::table('roles')->where('nombre', $roleName)->first();

            if ($role) {
                DB::table('users')->where('id', $user->id)->update(['role_id' => $role->id]);
            }
        }

        // Make role_id not nullable
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable(false)->change();
        });

        // Drop old role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the role column
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('ci');
        });

        // Migrate data back
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->role_id) {
                $role = DB::table('roles')->where('id', $user->role_id)->first();
                if ($role) {
                    DB::table('users')->where('id', $user->id)->update(['role' => $role->nombre]);
                }
            }
        }

        // Drop role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
