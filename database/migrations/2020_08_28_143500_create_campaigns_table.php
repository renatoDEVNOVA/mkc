<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('alias')->nullable();
            $table->date('fechaInicio');
            $table->date('fechaFin');
            $table->foreignId('idCampaignGroup')->nullable()->constrained('campaign_groups')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained()->onDelete('cascade');
            $table->foreignId('idAgente')->constrained('users')->onDelete('cascade');
            $table->text('observacion')->nullable();
            //$table->string('hashtags')->nullable();
            //$table->boolean('materialPrensa');
            //$table->string('idsTipoArticulo')->nullable();
            $table->tinyInteger('tipoPublico')->nullable();
            $table->tinyInteger('tipoObjetivo')->nullable();
            $table->tinyInteger('tipoAudiencia')->nullable();
            $table->tinyInteger('interesPublico')->nullable();
            $table->tinyInteger('novedad')->nullable();
            $table->tinyInteger('actualidad')->nullable();
            $table->tinyInteger('autoridadCliente')->nullable();
            $table->tinyInteger('mediaticoCliente')->nullable();
            $table->tinyInteger('autoridadVoceros')->nullable();
            $table->tinyInteger('mediaticoVoceros')->nullable();
            $table->tinyInteger('pesoPublico')->nullable();
            $table->tinyInteger('pesoObjetivo')->nullable();
            $table->tinyInteger('pesoAudiencia')->nullable();
            $table->tinyInteger('pesoInteresPublico')->nullable();
            $table->tinyInteger('pesoNovedad')->nullable();
            $table->tinyInteger('pesoActualidad')->nullable();
            $table->tinyInteger('pesoAutoridadCliente')->nullable();
            $table->tinyInteger('pesoMediaticoCliente')->nullable();
            $table->tinyInteger('pesoAutoridadVoceros')->nullable();
            $table->tinyInteger('pesoMediaticoVoceros')->nullable();
            //$table->boolean('nivelSocioeconomicoA');
            //$table->boolean('nivelSocioeconomicoB');
            //$table->boolean('nivelSocioeconomicoC');
            //$table->boolean('nivelSocioeconomicoD');
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
        Schema::dropIfExists('campaigns');
    }
}
