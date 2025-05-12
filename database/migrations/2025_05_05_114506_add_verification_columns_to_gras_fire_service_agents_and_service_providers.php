<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add columns to the 'gras' table
        Schema::table('gras', function (Blueprint $table) {
            $table->integer('OTP')->nullable()->length(11);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('sms_verified')->nullable();
            $table->uuid('email_token')->nullable();
        });

        // Add columns to the 'fire_service_agents' table
        Schema::table('fire_service_agents', function (Blueprint $table) {
            $table->integer('OTP')->nullable()->length(11);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('sms_verified')->nullable();
            $table->uuid('email_token')->nullable();
        });

        // Add columns to the 'service_providers' table
        Schema::table('service_providers', function (Blueprint $table) {
            $table->integer('OTP')->nullable()->length(11);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('sms_verified')->nullable();
            $table->uuid('email_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove columns from the 'gras' table
        Schema::table('gras', function (Blueprint $table) {
            $table->dropColumn(['OTP', 'email_verified_at', 'sms_verified']);
        });

        // Remove columns from the 'fire_service_agents' table
        Schema::table('fire_service_agents', function (Blueprint $table) {
            $table->dropColumn(['OTP', 'email_verified_at', 'sms_verified']);
        });

        // Remove columns from the 'service_providers' table
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['OTP', 'email_verified_at', 'sms_verified']);
        });
    }
};
