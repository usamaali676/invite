<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_onboarding_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbp_client_id')->constrained('gbp_clients')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted')->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gbp_client_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_onboarding_links');
    }
};
