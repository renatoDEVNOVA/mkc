<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Tenancy\Hooks\Migration\Events\ConfigureMigrations as Migrations;
use Tenancy\Tenant\Events\Deleted;

class ConfigureTenantMigrations
{
    public function handle(Migrations $event)
    {

        if ($event->event->tenant) {
            if ($event->event instanceof Deleted) {
                $event->disable();
            } else {
                $event->path(database_path('tenant/migrations'));
            }
        }
    }
}
