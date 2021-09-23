<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResultadoPlataformasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resultado_plataformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idProgramaContacto')->constrained('programa_contactos')->onDelete('cascade');
            $table->foreignId('idMedioPlataforma')->constrained('medio_plataformas')->onDelete('cascade');
            $table->string('foto')->nullable();
            $table->date('fechaPublicacion')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('idTipoCambio')->nullable()->constrained('tipo_cambios')->onDelete('cascade');
            $table->integer('segundos')->nullable();
            $table->decimal('ancho', 8, 2)->nullable();
            $table->decimal('alto', 8, 2)->nullable();
            $table->decimal('cm2', 8, 2)->nullable();
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
        Schema::dropIfExists('resultado_plataformas');
    }
}
