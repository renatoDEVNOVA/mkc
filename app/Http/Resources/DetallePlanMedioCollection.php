<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DetallePlanMedioCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = DetallePlanMedioResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'detallePlanMedio',
            'data' => $this->collection,
        ];
    }
}
