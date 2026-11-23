<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        // Alphanumeric Sender ID ya registrado y aprobado para Guatemala en esa cuenta.
        'alpha_sender' => env('TWILIO_ALPHA_SENDER', 'AUTHMSG'),
    ],

    'fel' => [
        'usuario_api' => env('FEL_USUARIO_API'),
        'llave_api' => env('FEL_LLAVE_API'),
        'usuario_firma' => env('FEL_USUARIO_FIRMA'),
        'identificador' => env('FEL_IDENTIFICADOR'),
        'llave_firma' => env('FEL_LLAVE_FIRMA'),

        'nit' => env('NIT'),
        'correo' => env('CORREO'),
        'nombre_comercial' => env('NOMBRE_COMERCIAL'),
        'razon_social' => env('RAZON_SOCIAL'),
        'direccion' => env('DIRECCION'),
        'codigo_postal' => env('CODIGO_POSTAL'),
        'municipio' => env('MUNICIPIO'),
        'departamento' => env('DEPARTAMENTO'),
        'pais' => env('PAIS'),
        'pbx' => env('PBX'),
        'whatsapp' => env('WHATSAPP'),
    ],

    'fel2' => [
        'usuario_api' => env('FEL_USUARIO_API'),
        'llave_api' => env('FEL_LLAVE_API'),
        'usuario_firma' => env('FEL_USUARIO_FIRMA'),
        'identificador' => env('FEL_IDENTIFICADOR'),
        'llave_firma' => env('FEL_LLAVE_FIRMA'),

        'nit' => env('NIT'),
        'correo' => env('CORREO'),
        'nombre_comercial' => env('NOMBRE_COMERCIAL2'),
        'razon_social' => env('RAZON_SOCIAL'),
        'direccion' => env('DIRECCION2'),
        'codigo_postal' => env('CODIGO_POSTAL2'),
        'municipio' => env('MUNICIPIO2'),
        'departamento' => env('DEPARTAMENTO2'),
        'pais' => env('PAIS'),
        'pbx' => env('PBX'),
        'whatsapp' => env('WHATSAPP'),
    ],

    'fel3' => [
        'usuario_api' => env('FEL_USUARIO_API'),
        'llave_api' => env('FEL_LLAVE_API'),
        'usuario_firma' => env('FEL_USUARIO_FIRMA'),
        'identificador' => env('FEL_IDENTIFICADOR'),
        'llave_firma' => env('FEL_LLAVE_FIRMA'),

        'nit' => env('NIT'),
        'correo' => env('CORREO'),
        'nombre_comercial' => env('NOMBRE_COMERCIAL3'),
        'razon_social' => env('RAZON_SOCIAL'),
        'direccion' => env('DIRECCION3'),
        'codigo_postal' => env('CODIGO_POSTAL3'),
        'municipio' => env('MUNICIPIO3'),
        'departamento' => env('DEPARTAMENTO3'),
        'pais' => env('PAIS'),
        'pbx' => env('PBX'),
        'whatsapp' => env('WHATSAPP'),
    ],

    // Configuración de Guatex
    'guatex' => [
        // Token que deben mandar los webhooks /api/guatex/* (sin él quedan cerrados)
        'webhook_token' => env('GUATEX_WEBHOOK_TOKEN'),
        'url_guias' => env('GUATEX_URL_GUIAS', 'https://guias.guatex.gt/tomarservicio/servicio'),
        'usuario' => env('GUATEX_USUARIO'),
        'password' => env('GUATEX_PASSWORD'),
        'password_municipios' => env('GUATEX_PASSWORD_MUNICIPIOS'),
        // ZACAPA
        'codigo_cobro_zacapa' => env('GUATEX_CODIGO_COBRO_ZACAPA'),
        'codigo_cobro_cod_zacapa' => env('GUATEX_CODIGO_COBRO_COD_ZACAPA'),
        // CHIQUIMULA
        'codigo_cobro_chiquimula' => env('GUATEX_CODIGO_COBRO_CHIQUIMULA'),
        'codigo_cobro_cod_chiquimula' => env('GUATEX_CODIGO_COBRO_COD_CHIQUIMULA'),
        // ESQUIPULAS
        'codigo_cobro_esquipulas' => env('GUATEX_CODIGO_COBRO_ESQUIPULAS'),
        'codigo_cobro_cod_esquipulas' => env('GUATEX_CODIGO_COBRO_COD_ESQUIPULAS'),
    ],

];
