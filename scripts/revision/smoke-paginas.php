<?php

/**
 * Abre (GET) las páginas de listado, dashboards, vistas de registro de los paneles de Filament
 * y las páginas de la tienda, como super_admin, dentro del proceso (sin contraseña).
 * Solo páginas revisadas que no tienen efectos al abrirse; NO abre páginas de edición
 * (resource-lock crea bloqueos) ni rutas /pdf, /reporte o /guatex.
 *
 * Uso (dentro de tl-rev-app): php scripts/revision/smoke-paginas.php [user_id]
 */

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = isset($argv[1]) ? User::findOrFail($argv[1]) : User::role('super_admin')->orderBy('id')->firstOrFail();

$urls = ['/', '/catalogo', '/marcas', '/manifest.json', '/offline', '/sitemap.xml'];
$slug = \App\Models\Producto::query()->whereNotNull('slug')->latest('id')->value('slug');
if ($slug) {
    $urls[] = '/producto/'.$slug;
}

foreach (Route::getRoutes() as $route) {
    $name = $route->getName() ?? '';
    if (! in_array('GET', $route->methods()) || ! str_starts_with($name, 'filament.')) {
        continue;
    }
    if (preg_match('/\.(edit|create)$|auth\.|imports\.|exports\.|logout|password|email-verification|tenant/', $name)) {
        continue;
    }
    $uri = '/'.ltrim($route->uri(), '/');
    if (preg_match('/\{record\}/', $uri) && str_ends_with($name, '.view')) {
        $resource = collect(\Filament\Facades\Filament::getPanels())
            ->flatMap(fn ($p) => $p->getResources())
            ->first(fn ($r) => str_contains($name, '.resources.'.$r::getSlug().'.'));
        try {
            $id = $resource ? $resource::getModel()::query()->latest('id')->value('id') : null;
        } catch (Throwable $e) {
            $id = null;
        }
        if (! $id) {
            continue;
        }
        $uri = str_replace('{record}', $id, $uri);
    }
    if (preg_match('/\{[^}]+\}/', $uri)) {
        continue;
    }
    $urls[] = $uri;
}

$urls = array_values(array_unique($urls));
$medir = in_array('--medir', $argv);
$consultas = [];
\Illuminate\Support\Facades\DB::listen(function ($q) use (&$consultas) { $consultas[] = [$q->sql, $q->time]; });
$fallas = 0;
foreach ($urls as $url) {
    Auth::forgetGuards();
    Auth::guard('web')->setUser($user);
    $request = Request::create($url, 'GET');
    $consultas = [];
    $t0 = microtime(true);
    try {
        $response = $kernel->handle($request);
        $code = $response->getStatusCode();
        $extra = '';
        if (property_exists($response, 'exception') && $response->exception) {
            $extra = ' '.get_class($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 150);
        }
        if ($code >= 500 || $extra) {
            $fallas++;
        }
        if (in_array('--lentas', $argv)) {
            foreach ($consultas as [$sql, $t]) {
                if ($t >= 8) {
                    echo str_pad((int) $t, 5, ' ', STR_PAD_LEFT).'ms '.$url.' | '.mb_substr(preg_replace('/\\s+/', ' ', $sql), 0, 260)."\n";
                }
            }
        } elseif ($medir) {
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $sqlMs = (int) array_sum(array_column($consultas, 1));
            $rep = collect($consultas)->map(fn ($c) => preg_replace('/\\b\\d+\\b|\'[^\']*\'/', '?', $c[0]))->countBy()->sortDesc()->take(1);
            $top = $rep->keys()->first();
            echo str_pad($code, 4).str_pad(count($consultas), 5, ' ', STR_PAD_LEFT).'q '.str_pad($sqlMs, 5, ' ', STR_PAD_LEFT).'ms-sql '.str_pad($ms, 6, ' ', STR_PAD_LEFT).'ms '.$url.' | x'.$rep->first().' '.mb_substr($top ?? '', 0, 110)."\n";
        } else {
            echo str_pad($code, 4).$url.$extra."\n";
        }
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        $fallas++;
        echo "EXC {$url} ".get_class($e).': '.mb_substr($e->getMessage(), 0, 150)."\n";
    }
}
echo "\nPáginas: ".count($urls)." · con error: $fallas\n";
