<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioSmsService
{
    /**
     * Envía el mensaje completo por SMS usando el Alphanumeric Sender ID
     * ya aprobado para Guatemala (evita el bloqueo de Claro, error 30018).
     * En el entorno de testing no llama a la API real.
     */
    public function enviar(string $numeroDestino, string $mensaje): void
    {
        if (app()->environment('testing')) {
            Log::info('TwilioSmsService: envío simulado (entorno testing)', ['to' => $numeroDestino]);

            return;
        }

        // Límite de tiempo: si Twilio no responde, la acción muestra el aviso de "no se pudo enviar" en vez de colgarse
        $http = new \Twilio\Http\CurlClient([CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20]);
        $client = new Client(config('services.twilio.account_sid'), config('services.twilio.auth_token'), null, null, $http);

        $client->messages->create($numeroDestino, [
            'from' => config('services.twilio.alpha_sender'),
            'body' => $mensaje,
        ]);
    }
}
