<?php

namespace App\Http\Resources\Campaign;

use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id"=> $this->id,
            "titulo" => $this->titulo,
            "alias"=> $this->alias,
            "fechaInicio"=> $this->fechaInicio,
            "fechaFin"=> $this->fechaFin,
            "idCampaignGroup"=> $this->idCampaignGroup,
            "cliente_id"=> $this->cliente_id,
            "idAgente"=> $this->idAgente,
            "observacion"=> $this->observacion,
            "tipoPublico"=> $this->tipoPublico,
            "tipoObjetivo"=> $this->tipoObjetivo,
            "tipoAudiencia"=> $this->tipoAudiencia,
            "interesPublico"=> $this->interesPublico,
            "novedad"=> $this->novedad,
            "actualidad"=> $this->actualidad,
            "autoridadCliente"=> $this->autoridadCliente,
            "mediaticoCliente"=> $this->mediaticoCliente,
            "autoridadVoceros"=> $this->autoridadVoceros,
            "mediaticoVoceros"=> $this->mediaticoVoceros,
            "pesoPublico"=> $this->pesoPublico,
            "pesoObjetivo"=> $this->pesoObjetivo,
            "pesoAudiencia"=> $this->pesoAudiencia,
            "pesoInteresPublico"=> $this->pesoInteresPublico,
            "pesoNovedad"=> $this->pesoNovedad,
            "pesoActualidad"=> $this->pesoActualidad,
            "pesoAutoridadCliente"=> $this->pesoAutoridadCliente,
            "pesoMediaticoCliente"=> $this->pesoMediaticoCliente,
            "pesoAutoridadVoceros"=> $this->pesoAutoridadVoceros,
            "pesoMediaticoVoceros"=> $this->pesoMediaticoVoceros,
            "created_at"=> $this->created_at,
            "updated_at"=> $this->updated_at,
            "deleted_at"=>$this->deleted_at,
            "cliente"=>$this->cliente,
            "campaign_group"=>$this->campaignGroup,
            "agente"=>$this->agente,
            "campaign_responsables"=>$this->campaignResponsables,
        ];
    }
}
