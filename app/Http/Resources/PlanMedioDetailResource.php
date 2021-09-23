<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanMedioDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $idsCampaign = auth()->user()->myCampaigns();
        $this->campaign->cliente;
        return [
            "id"=> $this->id,
            "campaign_id"=> $this->campaign_id,
            "nombre"=> $this->nombre,
            "idNotaPrensa"=> $this->idNotaPrensa,
            "descripcion"=> $this->descripcion,
            "status"=> $this->status,
            "user_id"=> $this->user_id,
            "created_at"=> $this->created_at,
            "updated_at"=> $this->updated_at,
            "deleted_at"=> $this->deleted_at,
            "isEditable" => in_array($this->campaign_id, $idsCampaign),
            "campaign" => $this->campaign,
            "detalle_plan_medios" =>  "planMedios/detalle/$this->id",
        ];
    }
}
