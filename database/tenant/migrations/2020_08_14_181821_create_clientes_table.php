<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombreComercial');
            $table->string('razonSocial')->nullable();
            $table->string('rubro')->nullable();
            $table->string('alias')->nullable();
            $table->foreignId('idTipoDocumento')->constrained('tipo_atributos')->onDelete('cascade');
            $table->string('nroDocumento');
            $table->text('observacion')->nullable();
            $table->string('logo')->nullable();
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
        Schema::dropIfExists('clientes');
    }
}
