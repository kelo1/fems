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
        Schema::create('corporate_clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('branch_name')->default('No Branch Name');
            $table->string('company_email');
            $table->string('company_phone');
            $table->string('company_address');
            $table->string('gps_address')->nullable();
            $table->string('certificate_of_incorporation')->default('No Upload');
            $table->string('company_registration')->default('No Upload');
            $table->unsignedBigInteger('client_id')->unique();
            $table->unsignedBigInteger('corporate_type_id')->nullable();
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('corporate_type_id')->references('id')->on('corporate_types')->onDelete('set null');
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
        Schema::dropIfExists('corporate_clients');
    }
};
