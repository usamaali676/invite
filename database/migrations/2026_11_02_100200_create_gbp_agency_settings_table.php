<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_agency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_id', 64)->nullable();
            $table->string('organization_name')->nullable();
            $table->string('location_group_account_id', 64)->nullable();
            $table->string('manager_email')->nullable();
            $table->string('default_role', 32)->default('MANAGER');
            $table->string('api_project_number', 32)->nullable();
            $table->string('api_approval_status', 32)->default('NOT_REQUESTED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_agency_settings');
    }
};
