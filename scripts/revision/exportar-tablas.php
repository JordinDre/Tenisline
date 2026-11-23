<?php
// Llama la acción "Exportar" (pxlrbt/filament-excel) de las 4 tablas, como super_admin, y guarda el Excel en TSV.
use App\Models\User;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = storage_path('app/revision/'.($argv[1] ?? 'antes')); @mkdir($dir, 0777, true);
$user = User::role('super_admin')->orderBy('id')->firstOrFail();
$paginas = [
  'ventas' => \App\Filament\Ventas\Resources\VentaDetalleResource\Pages\ListVentaDetalles::class,
  'inventario' => \App\Filament\Inventario\Resources\KardexResource\Pages\ListKardexes::class,
  'inventario ' => \App\Filament\Inventario\Resources\ProductoResource\Pages\ListProductos::class,
];
foreach ($paginas as $panel => $clase) {
  try {
    \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel(trim($panel)));
    $t = Livewire::actingAs($user)->test($clase)->callTableAction('export');
    $dl = $t->effects['download'] ?? null;
    if (! $dl) { echo "FALLA $clase sin descarga\n"; continue; }
    $nombre = class_basename($clase);
    file_put_contents("$dir/tabla-$nombre.xlsx", base64_decode($dl['content']));
    $csv = '';
    foreach (IOFactory::load("$dir/tabla-$nombre.xlsx")->getActiveSheet()->toArray(null, true, false, false) as $f) $csv .= implode("\t", $f)."\n";
    file_put_contents("$dir/tabla-$nombre.tsv", $csv);
    echo "OK $nombre (".substr_count($csv, "\n")." filas)\n";
  } catch (Throwable $e) { echo "FALLA $clase ".get_class($e).': '.mb_substr($e->getMessage(),0,200)."\n"; }
}
