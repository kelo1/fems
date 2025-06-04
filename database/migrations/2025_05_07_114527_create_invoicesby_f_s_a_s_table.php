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
        Schema::create('invoices_by_fsa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fsa_id')->constrained('fire_service_agents')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->string('certificate_serial_number')->nullable(); // Serial number of the certificate, if applicable
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->text('invoice_details');
            $table->string('invoice');
            $table->decimal('payment_amount', 15, 2);
            $table->string('created_by');
            $table->string('created_by_type');
            $table->boolean('payment_status')->nullable();
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
        Schema::dropIfExists('invoices_by_fsa');
    }
};
