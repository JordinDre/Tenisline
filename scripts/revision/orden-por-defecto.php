<?php
// Primeros 25 IDs de cada tabla sin orden ni filtros (para comparar main contra la rama).
use Livewire\Livewire; use Filament\Facades\Filament;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = App\Models\User::find($argv[1] ?? 8);
foreach (Filament::getPanels() as $panel) { Filament::setCurrentPanel($panel);
  foreach ($panel->getResources() as $r) { $i = $r::getPages()['index'] ?? null; if (! $i) continue;
    try { $t = Livewire::actingAs($u)->test($i->getPage());
      $rec = $t->instance()->getTableRecords();
      echo $panel->getId(), ' ', class_basename($r), ' ', ($rec instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rec->total() : $rec->count()), ' ', collect($rec->items())->map->getKey()->implode(','), "\n";
    } catch (Throwable $e) { echo $panel->getId(), ' ', class_basename($r), ' ERROR ', class_basename($e), "\n"; } } }
