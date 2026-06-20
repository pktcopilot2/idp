<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_client_accesses', function (Blueprint $table) {
            $table->string('session_id', 255)->index();
            $table->uuid('client_id');
            $table->timestamp('last_accessed_at')->useCurrent();

            $table->primary(['session_id', 'client_id']);

            $table->foreign('client_id')
                ->references('id')
                ->on('oauth_clients')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_client_accesses');
    }
};
