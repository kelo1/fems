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
        Schema::table('service_provider_devices', function (Blueprint $table) {
            $table->text('description')->nullable()->after('service_provider_id'); // Add the description column
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('service_provider_devices', function (Blueprint $table) {
            $table->dropColumn('description'); // Remove the description column
        });
    }
};
