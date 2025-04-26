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
        Schema::table('billings', function (Blueprint $table) {
            $table->integer('VAT_RATE')->nullable()->after('VAT_APPLICABLE'); // VAT rate as a percentage
            $table->tinyInteger('WITH_HOLDING_APPLICABLE')->default(0)->after('VAT_RATE'); // 1 if applicable, 0 otherwise
            $table->integer('WITH_HOLDING_RATE')->nullable()->after('WITH_HOLDING_APPLICABLE'); // Withholding rate as a percentage
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn(['VAT_RATE', 'WITH_HOLDING_APPLICABLE', 'WITH_HOLDING_RATE']);
        });
    }
};
