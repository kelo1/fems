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
        Schema::table('service_providers', function (Blueprint $table) {
            $table->softDeletes(); // Adds a `deleted_at` column
        });
    
        Schema::table('fire_service_agents', function (Blueprint $table) {
            $table->softDeletes();
        });
    
        Schema::table('gras', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    
        Schema::table('fire_service_agents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    
        Schema::table('gras', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
