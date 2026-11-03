<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_clients', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('google_maps_url', 2048)->nullable();
            $table->string('google_account_id', 64)->nullable();
            $table->string('google_location_id', 64)->nullable();
            $table->string('location_resource_name', 128)->nullable();
            $table->string('google_location_title')->nullable();
            $table->string('google_location_address', 512)->nullable();
            $table->string('access_status', 32)->default('NEW');
            $table->string('access_method', 32)->default('API');
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('access_status');
            $table->index('location_resource_name');
            $table->index('client_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_clients');
    }
};
