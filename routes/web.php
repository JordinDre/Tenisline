<?php

use App\Http\Controllers\GUATEXController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TiendaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TiendaController::class, 'index'])->name('inicio');
Route::get('/catalogo', [TiendaController::class, 'catalogo'])->name('catalogo');
Route::get('/producto/{slug}', [TiendaController::class, 'producto'])->name('producto');
Route::get('/marcas', [TiendaController::class, 'marcas'])->name('marcas');
Route::get('/img/og/{ruta}', [\App\Http\Controllers\ImagenController::class, 'og'])->where('ruta', '.+')->name('imagen.og');
Route::get('/img/{ancho}/{ruta}', \App\Http\Controllers\ImagenController::class)->where(['ancho' => '[0-9]+', 'ruta' => '.+'])->name('imagen');
Route::get('/sitemap.xml', [TiendaController::class, 'sitemap'])->name('sitemap');
Route::get('/manifest.json', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/offline', [\App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');
/* Route::get('/login', function () {
    return redirect('/admin');
}); */

Route::middleware('auth')->group(function () {
    /* Route::get('/crear-orden', [TiendaController::class, 'orden'])->name('crear.orden');
    Route::post('/guardar-orden', [TiendaController::class, 'storeOrden'])->name('guardar.orden');
    Route::inertia('/carrito', 'Carrito')->name('carrito');
    Route::post('/agregar-carrito', [TiendaController::class, 'agregarCarrito'])->name('agregar.carrito');
    Route::post('/sumar-carrito/{id}', [TiendaController::class, 'sumarCarrito'])->name('sumar.carrito');
    Route::post('/restar-carrito/{id}', [TiendaController::class, 'restarCarrito'])->name('restar.carrito');
    Route::post('/eliminar-carrito/{id}', [TiendaController::class, 'eliminarCarrito'])->name('eliminar.carrito'); */

    // REPORTES (página Reportes del panel admin: solo super_admin, administrador y gerente)
    Route::middleware('personal:super_admin,administrador,gerente')->group(function () {
        Route::get('/reporte/ventas-general', [ReporteController::class, 'VentasGeneral'])->name('reporte.ventasgeneral');
        Route::get('/reporte/ventas-detalle', [ReporteController::class, 'VentasDetalle'])->name('reporte.ventasdetalle');
        Route::get('/reporte/pagos', [ReporteController::class, 'Pagos'])->name('reporte.pagos');
        Route::get('/reporte/resultados', [ReporteController::class, 'Resultados'])->name('reporte.resultados');
        Route::get('/reporte/historial-cliente', [ReporteController::class, 'HistorialCliente'])->name('reporte.historialcliente');
        Route::get('/reporte/reporte-iventario', [ReporteController::class, 'ReporteInventario'])->name('reporte.reporteinventario');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // PDF: solo personal; cada método revisa además la policy del registro, igual que el botón del admin
    Route::prefix('pdf')->middleware('personal')->group(function () {
        Route::get('/orden/{id}', [PDFController::class, 'orden'])->name('pdf.orden');
        Route::get('/cotizacion/{id}', [PDFController::class, 'cotizacion'])->name('pdf.cotizacion');
        Route::get('/recibo-orden/{id}', [PDFController::class, 'reciboOrden'])->name('pdf.recibo-orden');
        Route::get('/guias/{id}', [PDFController::class, 'guias'])->name('pdf.guias');
        Route::get('/factura-orden/{id}', [PDFController::class, 'facturaOrden'])->name('pdf.factura.orden');
        Route::get('/nota-credito-orden/{id}', [PDFController::class, 'notaCreditoOrden'])->name('pdf.nota-credito.orden');

        Route::get('/venta/{id}', [PDFController::class, 'venta'])->name('pdf.venta');
        Route::get('/cierre/{id}', [PDFController::class, 'cierre'])->name('pdf.cierre');
        Route::get('/compra/{id}', [PDFController::class, 'compra'])->name('pdf.compra');
        Route::get('/traslado/{id}', [PDFController::class, 'traslado'])->name('pdf.traslado');
        Route::get('/factura-venta/{id}', [PDFController::class, 'facturaVenta'])->name('pdf.factura.venta');
        Route::get('/nota-credito-venta/{id}', [PDFController::class, 'notaCreditoVenta'])->name('pdf.nota-credito.venta');
        Route::get('/catalogo', [PDFController::class, 'catalogo'])->name('pdf.catalogo');

        Route::get('/catalogo/pdf', [TiendaController::class, 'exportarPdf'])->name('catalogo.pdf');
        Route::get('/catalogo/pdf-historial', [TiendaController::class, 'HistorialVendidosPdf'])->name('historial.pdf');
    });
});

/* GUATEX: solo personal con sesión. Lo que genera, elimina o cambia estados va por POST y solo para
   administradores (el admin no llama estas rutas: usa GuatexService desde la acción de la venta). */
Route::prefix('guatex')->middleware(['auth', 'personal'])->group(function () {
    Route::get('/destinos/{departamento}/{municipio?}', [GUATEXController::class, 'obtenerDestinos']);
    Route::get('/destino/{codigo}', [GUATEXController::class, 'consultarCodigoDestino']);
    Route::get('/consulta_tracking/{tracking}', [GUATEXController::class, 'consultarTracking']);
    Route::get('/generar_guias_pdf/{id}', [GUATEXController::class, 'generarGuiasPdf'])->name('guatex.generar_guias_pdf');

    Route::middleware('personal:super_admin,administrador')->group(function () {
        Route::post('/guia/{id}', [GUATEXController::class, 'generarGuia']);
        Route::post('/eliminar_guia/{tracking}', [GUATEXController::class, 'eliminarGuia']);
        Route::post('/recolectar/{tracking}', [GUATEXController::class, 'recolectar']);
        Route::post('/entregar/{tracking}', [GUATEXController::class, 'entregar']);
        Route::post('/liquidar', [GUATEXController::class, 'liquidar']);
        // Arma el XML con las credenciales de Guatex
        Route::get('/obtenerContent/{id}', [GUATEXController::class, 'obtenerContent']);
    });
});

require __DIR__.'/auth.php';
