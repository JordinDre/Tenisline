<?php
// Aplica filtros con formulario y muestra los chips que genera la tabla (super_admin, en la copia).
use Livewire\Livewire;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::role('super_admin')->orderBy('id')->first();
$casos = [
  ['inventario', App\Filament\Inventario\Resources\KardexResource\Pages\ListKardexes::class, 'created_at', ['fecha_inicial' => '2026-10-01', 'fecha_final' => '2026-10-31']],
  ['ventas', App\Filament\Ventas\Resources\VentaDetalleResource\Pages\ListVentaDetalles::class, 'asesor', ['asesor' => App\Models\User::role('vendedor')->value('id')]],
  ['ventas', App\Filament\Ventas\Resources\VentaResource\Pages\ListVentas::class, null, null],
  ['inventario', App\Filament\Inventario\Resources\ProductoResource\Pages\ListProductos::class, null, null],
];
foreach ($casos as [$panel, $clase, $filtro, $datos]) {
  try {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel($panel));
    $t = Livewire::actingAs($u)->test($clase);
    if ($filtro) $t->filterTable($filtro, $datos);
    $tabla = $t->instance()->getTable();
    $chips = collect($tabla->getFilters())->flatMap(fn ($f) => collect($f->getIndicators())->map(fn ($i) => $i->getLabel()));
    echo class_basename($clase), ' → chips: ', $chips->implode(' | ') ?: '(ninguno)', ' · registros: ', $t->instance()->getTableRecords()->total() ?? '?', "\n";
  } catch (Throwable $e) { echo class_basename($clase), ' ERROR ', class_basename($e), ': ', mb_substr($e->getMessage(), 0, 160), "\n"; }
}
