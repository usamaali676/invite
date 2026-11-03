<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gbp_client_id')->constrained('gbp_clients')->cascadeOnDelete();
            $table->string('access_method', 32);
            $table->foreignId('google_connection_id')->nullable()->constrained('gbp_google_connections')->nullOnDelete();
            $table->string('location_resource_name', 128)->nullable();
            $table->string('requested_account', 128)->nullable();
            $table->string('invitation_resource_name')->nullable();
            $table->string('requested_role', 32)->default('MANAGER');
            $table->string('status', 32);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('error_code', 48)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['gbp_client_id', 'status']);
            $table->index(['location_resource_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_access_requests');
    }
};
