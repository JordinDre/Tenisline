<?php

namespace App\Http\Controllers;

use App\Services\Pwa\ManifestService;

/**
 * Manifest y página sin conexión de la PWA del admin (antes venían de tomatophp/filament-pwa,
 * que no es compatible con Laravel 12). Mismas rutas y mismos ajustes del grupo "pwa".
 */
class PwaController extends Controller
{
    public function manifest()
    {
        return response()->json(ManifestService::generate());
    }

    public function offline()
    {
        return view('pwa.offline');
    }
}
