<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetallePlanResultadoPlataformasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detalle_plan_resultado_plataformas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idDetallePlanMedio')->constrained('detalle_plan_medios')->onDelete('cascade');
            $table->foreignId('idResultadoPlataforma')->constrained('resultado_plataformas')->onDelete('cascade');
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
        Schema::dropIfExists('detalle_plan_resultado_plataformas');
    }
}
