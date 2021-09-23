<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->string('nameReporte');
            $table->date('createdDate');
            $table->foreignId('cliente_id')->nullable()->constrained()->onDelete('cascade');
            $table->tinyInteger('tipoPeriodo')->nullable();
            $table->integer('numPeriodo')->nullable();
            $table->integer('year')->nullable();
            $table->tinyInteger('tipoReporte')->nullable();
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
        Schema::dropIfExists('reportes');
    }
}
