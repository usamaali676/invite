<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbp_client_id')->constrained('gbp_clients')->cascadeOnDelete();
            $table->foreignId('gbp_access_request_id')->nullable()->constrained('gbp_access_requests')->nullOnDelete();
            $table->string('action', 64);
            $table->string('actor_type', 16);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['gbp_client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_access_logs');
    }
};
