<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas. No pisa las que ya puso la respuesta
 * (los PDF ya mandan X-Frame-Options: SAMEORIGIN para abrirse en el iframe del admin).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $cabeceras = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // La tienda y la venta usan la ubicación y las fotos usan la cámara del propio sitio
            'Permissions-Policy' => 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()',
        ];

        foreach ($cabeceras as $nombre => $valor) {
            if (! $response->headers->has($nombre)) {
                $response->headers->set($nombre, $valor);
            }
        }

        return $response;
    }
}
