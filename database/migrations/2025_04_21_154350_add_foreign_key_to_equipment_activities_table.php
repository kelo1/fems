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
        Schema::table('equipment_activities', function (Blueprint $table) {
            $table->foreign('device_serial_number')
                ->references('device_serial_number')
                ->on('service_provider_devices')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('equipment_activities', function (Blueprint $table) {
            $table->dropForeign(['device_serial_number']);
        });
    }
};
