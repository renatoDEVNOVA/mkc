<?php

namespace App\Providers;

use App\Listeners\ConfigureApplicationUrl;
use App\Listeners\ConfigureTenantConnection;
use App\Listeners\ConfigureTenantDatabase;
use App\Listeners\ConfigureTenantMigrations;
use App\Listeners\ConfigureTenantRoutes;
use App\Listeners\ResolveTenantConnection;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Tenancy\Affects\Connections\Events\Drivers\Configuring as DriversConfiguring;
use Tenancy\Affects\Connections\Events\Resolving;
use Tenancy\Hooks\Database\Events\Drivers\Configuring;
use Tenancy\Hooks\Migration\Events\ConfigureMigrations;
use Tenancy\Affects\URLs\Events\ConfigureURL;
use Tenancy\Affects\Routes\Events\ConfigureRoutes;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Configuring::class => [
            ConfigureTenantDatabase::class,
        ],
        Resolving::class => [
            ResolveTenantConnection::class, 
        ],
        DriversConfiguring::class => [
            ConfigureTenantConnection::class,
        ],
        ConfigureMigrations::class => [
            ConfigureTenantMigrations::class,
        ],
        ConfigureURL::class => [
            ConfigureApplicationUrl::class,
        ],
        ConfigureRoutes::class => [
            ConfigureTenantRoutes::class,
        ]
        
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
