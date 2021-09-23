<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$permissions)
    {
        $myPermissions = auth()->user()->permissions->pluck('slug')->toArray();

        foreach($permissions as $permission){
            if (in_array($permission, $myPermissions)){
                return $next($request);
            }
        } 

        return response()->json([
            'ready' => false,
            'message' => 'No está autorizado para acceder a este recurso',
        ], 403);
    }
    
}
