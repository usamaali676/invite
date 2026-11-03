<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbp_client_id')->nullable()->constrained('gbp_clients')->cascadeOnDelete();
            $table->string('connection_type', 16);
            $table->string('google_email')->nullable();
            $table->string('google_account_id', 64)->nullable();
            $table->string('google_account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->index(['gbp_client_id', 'connection_type', 'status'], 'gbp_conn_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_google_connections');
    }
};
