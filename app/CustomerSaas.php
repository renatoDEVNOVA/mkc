<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tenancy\Identification\Concerns\AllowsTenantIdentification;
use Tenancy\Identification\Contracts\Tenant;
use Tenancy\Tenant\Events\Created;
use Tenancy\Tenant\Events\Deleted;
use Tenancy\Tenant\Events\Updated;
use Tenancy\Identification\Drivers\Http\Contracts\IdentifiesByHttp;
use Illuminate\Http\Request;
use Tenancy\Identification\Drivers\Console\Contracts\IdentifiesByConsole;
use Symfony\Component\Console\Input\InputInterface;

class CustomerSaas extends Model implements Tenant, IdentifiesByHttp, IdentifiesByConsole
{
    //use HasFactory;
    use AllowsTenantIdentification;

    protected $table='customers_saas';
    
    protected $fillable = ['nroDocumento','razonSocial','teamSize','industry','slug','customers_users_id'];

    protected $dispatchesEvents = [
        'created' => Created::class,
        'updated' => Updated::class,
        'deleted' => Deleted::class,
    ];

    public function tenantIdentificationByHttp(Request $request): ?Tenant
    {
        $tenant =  $this->query()
            ->where('slug', $request->segment(1))
            ->first();
        if ($tenant) {
            config(['auth.defaults.guard'=>'tenant']);
        }
        
        return $tenant;
    }

    public function tenantIdentificationByConsole(InputInterface $input): ?Tenant
    {
        if ($input->hasParameterOption('--tenant')) {
            return $this->query()
                ->where('slug', $input->getParameterOption('--tenant'))
                ->first();
        }
        return null;
    }
}
