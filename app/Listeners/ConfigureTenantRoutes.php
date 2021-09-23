<?php

namespace App\Listeners;

use Tenancy\Affects\Routes\Events\ConfigureRoutes;

class ConfigureTenantRoutes 
{
    protected $namespace = 'App\Http\Controllers';

    public function handle(ConfigureRoutes $event) 
    {
        if($event->event->tenant)
        {
            $event
                ->flush()
                ->fromFile(
                    ['middleware' => ['api'],'prefix'=>'{tenant}','namespace'=>$this->namespace],
                    base_path('/routes/tenant.php')
                );
        }
    }
}