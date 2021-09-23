<?php

namespace App\Http\Resources\Campaign;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CampaignCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public $collects = CampaignResource::class;

    public function toArray($request)
    {
        return [
            'ready'=> true,
            'type' => 'campaign',
            'data' => $this->collection,
        ];
    }
}
