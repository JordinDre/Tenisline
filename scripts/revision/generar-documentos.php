<?php

/**
 * Genera todos los PDF y Excel con datos de la COPIA de la BD (entorno aislado) para comparar
 * antes y después de actualizar. Solo lectura: no llama rutas con efectos (PDFController::guias
 * llama a Guatex/Labelary y cambia estados: se omite; la guía se prueba con generarGuiasPdf).
 *
 * Uso (dentro de tl-rev-app):  php scripts/revision/generar-documentos.php <carpeta>
 */

use App\Http\Controllers\GUATEXController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TiendaController;
use App\Models\Cierre;
use App\Models\Compra;
use App\Models\Orden;
use App\Models\Traslado;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dir = storage_path('app/revision/'.($argv[1] ?? 'antes'));
@mkdir($dir, 0777, true);
$idsFile = storage_path('app/revision/ids.json');
$ids = file_exists($idsFile) ? json_decode(file_get_contents($idsFile), true) : [];

$admin = User::role('super_admin')->orderBy('id')->firstOrFail();
Auth::setUser($admin);
app('request')->setUserResolver(fn () => $admin);

$pdf = app(PDFController::class);
$rep = app(ReporteController::class);
$tienda = app(TiendaController::class);
$guatex = app(GUATEXController::class);

$fechas = ['fecha_inicial' => '2026-10-01', 'fecha_final' => '2026-10-31', 'fecha_incial' => '2026-10-01'];

// [nombre, candidatos (ids), fn(id) => Response]
$docs = [
    ['orden', fn () => Orden::whereHas('detalles')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->orden($id)],
    ['cotizacion', fn () => Orden::whereHas('detalles')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->cotizacion($id)],
    ['recibo-orden', fn () => Orden::whereHas('pagos')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->reciboOrden($id)],
    ['factura-orden', fn () => Orden::whereHas('factura')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->facturaOrden($id)],
    ['nota-credito-orden', fn () => Orden::whereHas('devolucion')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->notaCreditoOrden($id)],
    ['venta', fn () => Venta::whereHas('pagos')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->venta($id)],
    ['factura-venta', fn () => Venta::whereHas('factura')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->facturaVenta($id)],
    ['nota-credito-venta', fn () => Venta::whereHas('devolucion')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->notaCreditoVenta($id)],
    ['guias-venta', fn () => Venta::whereHas('guias')->latest('id')->limit(5)->pluck('id'), fn ($id) => $guatex->generarGuiasPdf($id)],
    // No hay guías en la BD: se arma una en memoria (sin guardar) para probar códigos de barra y QR.
    ['guias-sintetica', fn () => Venta::whereHas('cliente.direcciones')->latest('id')->limit(1)->pluck('id'), function ($id) {
        $venta = Venta::with('cliente.direcciones', 'tipo_pago', 'asesor')->findOrFail($id);
        $guia = new \App\Models\Guia(['tracking' => 'ZAC0012345', 'cantidad' => 2]);
        $guia->hijas = ['ZAC0012346'];
        $venta->setRelation('guias', collect([$guia]));
        $html = view('pdf.guias', compact('venta'))->render();

        return response(\Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper([0, 0, 250, 500], 'portrait')->output());
    }],
    ['cierre',fn () => Cierre::whereNotNull('cierre')->latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->cierre($id)],
    ['compra', fn () => Compra::latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->compra($id)],
    ['traslado', fn () => Traslado::latest('id')->limit(5)->pluck('id'), fn ($id) => $pdf->traslado($id)],
    ['catalogo', fn () => [0], fn ($id) => $pdf->catalogo()],
    ['catalogo-filtro', fn () => [0], fn ($id) => $tienda->exportarPdf(Request::create('/', 'GET', ['marca' => null]))],
    ['historial-vendidos', fn () => [0], fn ($id) => $tienda->HistorialVendidosPdf(Request::create('/', 'GET', []))],
    ['xlsx-ventas-general', fn () => [0], fn ($id) => $rep->VentasGeneral(Request::create('/', 'GET', $fechas))],
    ['xlsx-ventas-detalle', fn () => [0], fn ($id) => $rep->VentasDetalle(Request::create('/', 'GET', $fechas))],
    ['xlsx-pagos', fn () => [0], fn ($id) => $rep->Pagos(Request::create('/', 'GET', $fechas))],
    ['xlsx-resultados', fn () => [0], fn ($id) => $rep->Resultados(Request::create('/', 'GET', $fechas))],
    ['xlsx-historial-cliente', fn () => Venta::latest('id')->limit(1)->pluck('cliente_id'), fn ($id) => $rep->HistorialCliente(Request::create('/', 'GET', $fechas + ['cliente_id' => $id]))],
    ['xlsx-inventario', fn () => [0], fn ($id) => $rep->ReporteInventario()],
];

$resumen = [];
foreach ($docs as [$nombre, $candidatos, $gen]) {
    try {
        $lista = isset($ids[$nombre]) ? [$ids[$nombre]] : collect($candidatos())->all();
    } catch (Throwable $e) {
        $resumen[] = "OMITE $nombre -> ".mb_substr($e->getMessage(), 0, 120);
        continue;
    }
    $ok = false;
    $error = 'sin registros';
    foreach ($lista as $id) {
        try {
            $resp = $gen($id);
            if ($resp instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
                $file = $resp->getFile()->getPathname();
                $ext = 'xlsx';
                copy($file, "$dir/$nombre.$ext");
                $hoja = IOFactory::load("$dir/$nombre.$ext");
                $csv = '';
                foreach ($hoja->getAllSheets() as $s) {
                    foreach ($s->toArray(null, true, false, false) as $fila) {
                        $csv .= implode("\t", array_map(fn ($v) => (string) $v, $fila))."\n";
                    }
                }
                file_put_contents("$dir/$nombre.tsv", $csv);
            } else {
                if (! $resp || $resp->getStatusCode() !== 200 || ! str_starts_with($resp->getContent(), '%PDF')) {
                    throw new Exception('respuesta no es PDF ('.($resp?->getStatusCode() ?? 'null').')');
                }
                file_put_contents("$dir/$nombre.pdf", $resp->getContent());
            }
            $ids[$nombre] = $id;
            $resumen[] = "OK    $nombre (id $id)";
            $ok = true;
            break;
        } catch (Throwable $e) {
            $error = get_class($e).': '.mb_substr($e->getMessage(), 0, 160);
        }
    }
    if (! $ok) {
        $resumen[] = "FALLA $nombre -> $error";
    }
}

file_put_contents($idsFile, json_encode($ids, JSON_PRETTY_PRINT));
file_put_contents("$dir/resumen.txt", implode("\n", $resumen)."\n");
echo implode("\n", $resumen)."\n";
