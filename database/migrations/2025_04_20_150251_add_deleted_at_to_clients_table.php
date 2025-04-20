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
        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes(); // Adds a `deleted_at` column
        });

        Schema::table('individual_clients', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
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
        Schema::table('clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('individual_clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('corporate_clients', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
