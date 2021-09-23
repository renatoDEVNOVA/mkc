<?php

namespace App\Http\Resources\DetallePlanMedio;

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
        //$bitacora = $this->bitacoras->where('estado',5);
        return [
            "id"=> $this->id,
            "idPlanMedio"=> $this->idPlanMedio,
            "idProgramaContacto"=> $this->idProgramaContacto,
            "idsMedioPlataforma"=> $this->idsMedioPlataforma,
            "tipoTier"=> $this->tipoTier,
            "tipoNota"=> $this->tipoNota,
            "tipoEtapa"=> $this->tipoEtapa,
            "muestrasRegistradas"=> $this->muestrasRegistradas,
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
            //"medioPlataformas"=> $this->medioPlataformas,
            "hasAssociated"=> $this->hasAssociated,
            "isEditable"=> $this->isEditable,
            "plan_medio"=> $this->planMedio,
            "programa_contacto"=> $this->programaContacto,
            "voceros"=> $this->voceros,
            "bitacoras"=> $this->bitacoras
        ];
    }
}
