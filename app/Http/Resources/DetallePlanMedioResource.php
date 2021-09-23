<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DetallePlanMedioResource extends JsonResource
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
            "idPlanMedio"=> $this->idPlanMedio,
            "idProgramaContacto"=> $this->idProgramaContacto,
            "idsMedioPlataforma"=>  $this->idsMedioPlataforma,
            "tipoTier"=> $this->tipoTier,
            "tipoNota"=> $this->tipoNota,
            "tipoEtapa"=> $this->tipoEtapa,
            "muestrasRegistradas"=> $this->muestrasRegistrada,
            "muestrasEnviadas"=> $this->muestrasEnviadas,
            "muestrasVerificadas"=> $this->muestrasVerificadas,
            "statusPublicado"=> $this->statusPublicado,
            "statusExperto"=> $this->statusExperto,
            "vinculado"=> $this->vinculado,
            "idDetallePlanMedioPadre"=> $this->idDetallePlanMedioPadre,
            "user_id"=> $this->user_id,
            "created_at"=> $this->created_at,
            "updated_at"=> $this->updated_at,
            "deleted_at"=> $this->deleted_at,
        ];
    }
}
