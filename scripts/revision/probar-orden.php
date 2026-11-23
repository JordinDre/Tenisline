<?php
// Ordena cada columna ordenable de cada tabla de listado (ascendente y descendente) y reporta errores.
// Uso: php ... [user_id]   (salida: una línea por columna: OK/ERROR)
use Livewire\Livewire;
use Filament\Facades\Filament;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::find($argv[1] ?? 8);
foreach (Filament::getPanels() as $panel) {
  Filament::setCurrentPanel($panel);
  foreach ($panel->getResources() as $resource) {
    $index = $resource::getPages()['index'] ?? null;
    if (! $index) continue;
    $pagina = $index->getPage();
    try {
      $t = Livewire::actingAs($u)->test($pagina);
      $cols = collect($t->instance()->getTable()->getColumns())->filter(fn ($c) => $c->isSortable())->keys();
    } catch (Throwable $e) { echo "PAGINA ", class_basename($pagina), ' ', class_basename($e), "\n"; continue; }
    foreach ($cols as $col) {
      foreach (['asc', 'desc'] as $dir) {
        try {
          $t2 = Livewire::actingAs($u)->test($pagina)->sortTable($col, $dir);
          $ids = $t2->instance()->getTableRecords()->take(5)->map(fn ($r) => $r->getKey())->implode(',');
          echo "OK    {$panel->getId()} ", class_basename($resource), " $col $dir [$ids]\n";
        } catch (Throwable $e) {
          echo "ERROR {$panel->getId()} ", class_basename($resource), " $col $dir ", class_basename($e), ': ', mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 140), "\n";
        }
      }
    }
  }
}
