<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On MySQL 5.7 and MariaDB (explicit_defaults_for_timestamp = OFF) the first NOT NULL
 * TIMESTAMP column of a table silently gets "ON UPDATE CURRENT_TIMESTAMP". Every update of a
 * link row, such as recording the first visit, then reset expires_at to the current time and
 * the link expired immediately. A DATETIME column has no such implicit behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gbp_onboarding_links', function (Blueprint $table) {
            $table->dateTime('expires_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('gbp_onboarding_links', function (Blueprint $table) {
            $table->timestamp('expires_at')->change();
        });
    }
};
