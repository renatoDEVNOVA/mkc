<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PlanMedioEstadoCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = PlanMedioResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'planMediosEstado',
            'data' => $this->collection,
        ];
    }
}
