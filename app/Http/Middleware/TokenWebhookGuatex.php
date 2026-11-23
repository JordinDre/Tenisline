<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhooks de Guatex (/api/guatex/*): cambian estados de ventas y registran pagos, así que piden un
 * token compartido. Se manda en la cabecera "X-Guatex-Token" o como ?token=.
 * Sin GUATEX_WEBHOOK_TOKEN en el .env quedan cerrados (en nov-2026 los logs no mostraban llamadas).
 */
class TokenWebhookGuatex
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('services.guatex.webhook_token');
        $recibido = (string) ($request->header('X-Guatex-Token') ?? $request->query('token', ''));

        abort_if($esperado === '' || ! hash_equals($esperado, $recibido), 403);

        return $next($request);
    }
}
