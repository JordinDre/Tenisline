<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo el personal (quien puede entrar a algún panel). Bloquea a clientes y proveedores,
 * que pueden iniciar sesión en la tienda pero no deben abrir PDF, reportes ni rutas de Guatex.
 * Opcionalmente pide uno de los roles indicados: ->middleware('personal:super_admin,administrador').
 */
class SoloPersonal
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->canAccessPanel(Filament::getPanel('ventas')), 403);
        abort_if($roles && ! $user->hasAnyRole($roles), 403);

        return $next($request);
    }
}
