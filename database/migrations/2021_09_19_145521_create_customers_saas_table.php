<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersSaasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers_saas', function (Blueprint $table) {
            $table->id();
            $table->string('nroDocumento',11)->nullable();
            $table->string('razonSocial')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->int('teamSize');
            $table->int('industry');
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
        Schema::dropIfExists('customers_saas');
    }
}
