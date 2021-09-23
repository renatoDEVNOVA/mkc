<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMedioEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('medio_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medio_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->foreignId('idTipoEmail')->constrained('tipo_atributos')->onDelete('cascade');
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
        Schema::dropIfExists('medio_emails');
    }
}
