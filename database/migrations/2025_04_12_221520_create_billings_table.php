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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('DESCRIPTION')->nullable();
            $table->boolean('VAT_APPLICABLE')->nullable();
            $table->boolean('isACTIVE')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type')->nullable(); // Store the type of user who created the billing
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('billings');
    }
};
