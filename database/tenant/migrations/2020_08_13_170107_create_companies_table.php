<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('nombreComercial');
            $table->string('razonSocial')->nullable();
            $table->string('propietario')->nullable();
            $table->string('representanteLegal')->nullable();
            $table->foreignId('idTipoDocumento')->constrained('tipo_atributos')->onDelete('cascade');
            $table->string('nroDocumento');
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
        Schema::dropIfExists('companies');
    }
}
