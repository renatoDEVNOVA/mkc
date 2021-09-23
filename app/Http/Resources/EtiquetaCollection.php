<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EtiquetaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = EtiquetaResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'etiquetas',
            'data' => $this->collection,
        ];
    }
}
