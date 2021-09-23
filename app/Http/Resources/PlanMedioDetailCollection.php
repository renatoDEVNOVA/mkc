<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PlanMedioDetailCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = PlanMedioDetailResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'planMediosDetail',
            'data' => $this->collection,
        ];
    }
}
