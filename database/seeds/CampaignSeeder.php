<?php

use Illuminate\Database\Seeder;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Cliente;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $campaigns = factory(App\Campaign::class,5)->create()->each(function ($campaign) {
            $campaign->alias = 'cmp' . $campaign->id . '_' . Str::random(8);
            $campaign->save();

            $cliente = Cliente::find($campaign->cliente_id);
            Storage::makeDirectory('clientes/'.$cliente->alias.'/'.$campaign->alias);
        });
    }
}
