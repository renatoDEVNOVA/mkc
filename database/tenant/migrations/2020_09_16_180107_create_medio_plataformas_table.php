<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedioPlataformasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('medio_plataformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medio_id')->constrained()->onDelete('cascade');
            $table->foreignId('idPlataformaClasificacion')->constrained('plataforma_clasificacions')->onDelete('cascade');
            $table->string('valor');
            $table->integer('alcance')->nullable();
            $table->string('observacion')->nullable();
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
        Schema::dropIfExists('medio_plataformas');
    }
}
