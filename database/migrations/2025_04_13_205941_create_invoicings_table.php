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
        Schema::create('invoicings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_provider_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->string('equipment_serial_number')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->text('invoice_details')->nullable(); // description of the invoice
            $table->string('invoice')->nullable();
            $table->double('payment_amount', 15, 2); // total amount of the invoice
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type')->nullable(); // Store the type of user who created the invoice
            $table->boolean('payment_status')->nullable(); // status of the invoice (e.g., pending, paid, overdue)
            
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
            $table->foreign('service_provider_id')->references('id')->on('service_providers')->onDelete('set null');

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
        Schema::dropIfExists('invoicings');
    }
};
