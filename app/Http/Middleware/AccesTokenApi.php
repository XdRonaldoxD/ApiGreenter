<?php

namespace App\Http\Middleware;

use Closure;

class AccesTokenApi
{

    public function handle($request, Closure $next)
    {
        $Api_token = $request->header('Authorization');
        $Api_token = str_replace('Bearer ','', $Api_token);
        if ($Api_token !== config('configuracion.Api_token')) {
            return response()->json([
                "error" => "Unauthorized",
                "message" => "Usted no se encuentra autorizado."
            ], 401);
        }
        return $next($request);
    }
}
