<?php

/**
 * Prueba por rol las rutas protegidas en la Fase 4 (dentro del proceso, sin contraseñas).
 * Solo GET de lectura y POST que deben responder 403 antes de llegar al controlador.
 * Compara el status real con lo que muestra el admin (misma policy o rol que el botón).
 *
 * Uso (dentro de tl-rev-app): php scripts/revision/probar-permisos.php
 */

use App\Models\Cierre;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Un usuario real por rol (el primero que tenga ese rol y ninguno "más alto"); el vendedor con bodegas.
$superiores = ['super_admin', 'administrador', 'gerente', 'auxiliar'];
$elegir = function (string $rol, bool $conBodegas = false) use ($superiores) {
    return User::role($rol)
        ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', array_diff($superiores, [$rol])))
        ->when($conBodegas, fn ($q) => $q->whereHas('bodegas'))
        ->orderBy('id')->value('id');
};
$usuarios = [];
foreach (['super_admin', 'administrador', 'auxiliar', 'vendedor', 'telemarketing', 'cliente', 'proveedor'] as $rol) {
    $usuarios[$rol] = $elegir($rol, in_array($rol, ['vendedor', 'telemarketing']));
}
$usuarios = array_filter($usuarios) + ['sin sesión' => null];
$vendedorId = $usuarios['vendedor'] ?? User::whereHas('bodegas')->value('id');
echo 'Usuarios: '.json_encode($usuarios)."\n";

$bodegasVendedor = User::find($vendedorId)->bodegas->pluck('id');
$ventaFactura = Venta::whereHas('factura')->whereIn('estado', ['creada', 'liquidada', 'enviado'])->whereIn('bodega_id', $bodegasVendedor)->latest('id')->value('id')
    ?? Venta::whereHas('factura')->latest('id')->value('id');
$ventaOtraBodega = Venta::whereHas('factura')->whereIn('estado', ['creada', 'liquidada', 'enviado'])->whereNotIn('bodega_id', $bodegasVendedor)->latest('id')->value('id');
$ventaNota = Venta::whereIn('estado', ['devuelta', 'parcialmente_devuelta'])->latest('id')->value('id');
$cierrePropio = Cierre::where('user_id', $vendedorId)->whereNotNull('cierre')->latest('id')->value('id');
$cierreAjeno = Cierre::where('user_id', '<>', $vendedorId)->whereNotNull('cierre')->latest('id')->value('id');
$compra = \App\Models\Compra::latest('id')->value('id');
$traslado = \App\Models\Traslado::latest('id')->value('id');
$q = '?fecha_inicial=2026-10-01&fecha_final=2026-10-02';

$personal = fn (?User $u) => $u && $u->canAccessPanel(\Filament\Facades\Filament::getPanel('ventas'));
$admin3 = fn (?User $u) => $personal($u) && $u->hasAnyRole(['super_admin', 'administrador', 'gerente']);
$admin2 = fn (?User $u) => $personal($u) && $u->hasAnyRole(['super_admin', 'administrador']);
$enTabla = function (?User $u, string $resource, $id) use ($personal) {
    if (! $personal($u) || ! Gate::forUser($u)->allows('viewAny', $resource::getModel())) {
        return false;
    }
    Auth::forgetGuards();
    Auth::guard('web')->setUser($u);

    return $resource::getEloquentQuery()->whereKey($id)->exists();
};
$cierreVisible = fn ($u, $id) => $enTabla($u, \App\Filament\Ventas\Resources\CierreResource::class, $id);

// [método, url, fn(User|null) => esperado permitido]
$casos = [
    ['GET', "/reporte/pagos$q", $admin3],
    ['GET', "/reporte/reporte-iventario", $admin3],
    ['GET', "/pdf/venta/$ventaFactura", fn ($u) => $personal($u) && $u->hasAnyRole(['administrador', 'super_admin', 'auxiliar'])],
    ['GET', "/pdf/factura-venta/$ventaFactura", fn ($u) => $personal($u) && Gate::forUser($u)->allows('factura', Venta::find($ventaFactura))],
    ['GET', "/pdf/factura-venta/$ventaOtraBodega", fn ($u) => $personal($u) && Gate::forUser($u)->allows('factura', Venta::find($ventaOtraBodega))],
    ['GET', "/pdf/nota-credito-venta/$ventaNota", fn ($u) => $personal($u) && Gate::forUser($u)->allows('credit_note', Venta::find($ventaNota))],
    ['GET', "/pdf/cierre/$cierrePropio", fn ($u) => $cierreVisible($u, $cierrePropio)],
    ['GET', "/pdf/cierre/$cierreAjeno", fn ($u) => $cierreVisible($u, $cierreAjeno)],
    ['GET', "/pdf/compra/$compra", fn ($u) => $enTabla($u, \App\Filament\Inventario\Resources\CompraResource::class, $compra)],
    ['GET', "/pdf/traslado/$traslado", fn ($u) => $enTabla($u, \App\Filament\Inventario\Resources\TrasladoResource::class, $traslado)],
    ['GET', "/guatex/generar_guias_pdf/$ventaFactura", fn ($u) => $enTabla($u, \App\Filament\Ventas\Resources\VentaResource::class, $ventaFactura)],
    ['GET', '/guatex/obtenerContent/1', $admin2, 'no-admin'],
    ['POST', '/guatex/guia/1', $admin2, 'no-admin'],
    ['POST', '/guatex/eliminar_guia/X', $admin2, 'no-admin'],
    ['GET', '/guatex/guia/1', fn () => false],
    ['GET', '/api/guatex/recolectar/NOEXISTE', fn () => false],
    ['POST', '/api/guatex/liquidar', fn () => false],
];

$fallas = 0;
foreach ($usuarios as $rol => $id) {
    $u = $id ? User::find($id) : null;
    $linea = [];
    foreach ($casos as $caso) {
        [$metodo, $url, $esperado] = $caso;
        $soloNoAdmin = ($caso[3] ?? null) === 'no-admin';
        $permitido = (bool) $esperado($u);
        if ($soloNoAdmin && $permitido) {
            continue; // no se ejecuta como admin: llamaría a Guatex o devolvería credenciales
        }
        Auth::forgetGuards();
        if ($u) {
            Auth::guard('web')->setUser($u);
        }
        $req = Request::create($url, $metodo);
        $req->headers->set('Accept', 'text/html');
        try {
            $res = $kernel->handle($req);
            $code = $res->getStatusCode();
            $kernel->terminate($req, $res);
        } catch (Throwable $e) {
            $code = 'EXC '.class_basename($e);
        }
        $bloqueado = in_array($code, [302, 403, 404, 405, 419], true);
        $ok = $permitido ? $code === 200 : $bloqueado;
        if (! $ok) {
            $fallas++;
        }
        $linea[] = ($ok ? '' : '✗ ').$metodo.' '.$url.' → '.$code.($permitido ? ' (debe abrir)' : '');
    }
    echo "== $rol\n  ".implode("\n  ", $linea)."\n";
}
echo "\nCasos que no cumplen lo esperado: $fallas\n";
