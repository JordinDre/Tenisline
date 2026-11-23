<?php
// Muestra las consultas agrupadas de una URL GET (como super_admin). Uso: php ... /ventas/cierres [user_id]
use App\Models\User; use Illuminate\Http\Request; use Illuminate\Support\Facades\{Auth, DB};
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$u = User::find($argv[2] ?? 8); $q = [];
DB::listen(function ($e) use (&$q) { $q[] = $e; });
Auth::guard('web')->setUser($u);
$res = $kernel->handle(Request::create($argv[1], 'GET'));
echo $res->getStatusCode().' · '.count($q)." consultas\n";
collect($q)->groupBy(fn ($e) => preg_replace('/\b\d+\b|\'[^\']*\'/', '?', $e->sql))
  ->map(fn ($g, $sql) => [count($g), round($g->sum('time')), mb_substr($sql, 0, 230)])
  ->sortByDesc(0)->take((int) ($argv[3] ?? 12))->each(fn ($r) => print(str_pad($r[0], 4).str_pad($r[1].'ms', 7).$r[2]."\n"));
