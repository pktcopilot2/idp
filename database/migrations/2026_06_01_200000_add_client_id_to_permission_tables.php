<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames  = config('permission.table_names');
        $columnNames = config('permission.column_names');

        // --- permissions table --------------------------------------------------
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->string('client_id', 36)->nullable()->default(null)->after('id');
            $table->index('client_id', 'permissions_client_id_index');
        });
        // Replace the old unique(name, guard_name) with (client_id, name, guard_name).
        // We do this in a separate call so the column is committed first.
        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['client_id', 'name', 'guard_name'], 'permissions_client_id_name_guard_name_unique');
        });

        // --- roles table --------------------------------------------------------
        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->string('client_id', 36)->nullable()->default(null)->after('id');
            $table->index('client_id', 'roles_client_id_index');
        });
        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['client_id', 'name', 'guard_name'], 'roles_client_id_name_guard_name_unique');
        });

        // --- model_has_permissions pivot ----------------------------------------
        // Just add the column + index; the existing PK (permission_id, model_id,
        // model_type) stays valid because every permission_id is unique per client.
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
            $table->string('client_id', 36)->nullable()->default(null);
            $table->index('client_id', 'model_has_permissions_client_id_index');
        });

        // --- model_has_roles pivot -----------------------------------------------
        // Same reasoning: every role_id is unique per client, so the existing PK
        // (role_id, model_id, model_type) naturally remains unique.
        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
            $table->string('client_id', 36)->nullable()->default(null);
            $table->index('client_id', 'model_has_roles_client_id_index');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
            $table->dropIndex('model_has_roles_client_id_index');
            $table->dropColumn('client_id');
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
            $table->dropIndex('model_has_permissions_client_id_index');
            $table->dropColumn('client_id');
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropUnique('roles_client_id_name_guard_name_unique');
            $table->dropIndex('roles_client_id_index');
            $table->dropColumn('client_id');
            $table->unique(['name', 'guard_name']);
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropUnique('permissions_client_id_name_guard_name_unique');
            $table->dropIndex('permissions_client_id_index');
            $table->dropColumn('client_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};
