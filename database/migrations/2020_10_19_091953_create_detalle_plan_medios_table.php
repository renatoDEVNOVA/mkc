<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetallePlanMediosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detalle_plan_medios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idPlanMedio')->constrained('plan_medios')->onDelete('cascade');
            $table->foreignId('idProgramaContacto')->constrained('programa_contactos')->onDelete('cascade');
            $table->string('idsMedioPlataforma')->nullable();
            $table->tinyInteger('tipoTier')->nullable();
            $table->tinyInteger('tipoNota');
            $table->tinyInteger('tipoEtapa');
            $table->tinyInteger('muestrasRegistradas')->nullable();
            $table->tinyInteger('muestrasEnviadas')->nullable();
            $table->tinyInteger('muestrasVerificadas')->nullable();
            $table->tinyInteger('statusPublicado')->nullable();
            $table->boolean('statusExperto')->nullable();
            $table->boolean('vinculado')->nullable();
            $table->foreignId('idDetallePlanMedioPadre')->nullable()->constrained('detalle_plan_medios')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
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
        Schema::dropIfExists('detalle_plan_medios');
    }
}
