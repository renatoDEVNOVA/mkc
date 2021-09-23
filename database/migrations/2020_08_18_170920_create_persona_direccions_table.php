<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersonaDireccionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('persona_direccions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->onDelete('cascade');
            $table->string('direccion');
            $table->foreignId('idTipoDireccion')->constrained('tipo_atributos')->onDelete('cascade');
            $table->timestamps();
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
        Schema::dropIfExists('persona_direccions');
    }
}
