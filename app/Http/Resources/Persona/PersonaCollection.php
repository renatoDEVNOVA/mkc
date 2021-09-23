<?php

namespace App\Http\Resources\Persona;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PersonaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = PersonaResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'persona',
            'data' => $this->collection,
        ];
    }
}
