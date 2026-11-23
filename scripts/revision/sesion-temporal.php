<?php

/**
 * Sesión temporal para revisar el admin en el navegador SIN escribir contraseñas.
 * Solo en el entorno aislado (copia de la BD). Crea el usuario revision-temporal@tenisline.local
 * con el rol indicado y una sesión ya iniciada; imprime la cookie para ponerla en el navegador.
 *
 *   php scripts/revision/sesion-temporal.php [rol]     -> crea/renueva (rol por defecto super_admin)
 *   php scripts/revision/sesion-temporal.php --borrar  -> borra el usuario temporal
 *
 * En el navegador (la cookie normal es HttpOnly, por eso se ponen rutas más específicas):
 *   ['/admin','/ventas','/inventario','/livewire','/pdf','/guatex','/reporte','/filament']
 *     .forEach(p => document.cookie = 'NOMBRE=' + encodeURIComponent('VALOR') + '; path=' + p)
 */

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

abort_if(app()->environment('production') || config('database.connections.'.config('database.default').'.host') !== 'tl-rev-db', 1, 'Solo en el entorno aislado (DB_HOST=tl-rev-db).');

$email = 'revision-temporal@tenisline.local';

if (($argv[1] ?? null) === '--borrar') {
    User::where('email', $email)->get()->each(fn ($u) => $u->syncRoles([]) && $u->forceDelete());
    echo "Usuario temporal borrado.\n";
    exit;
}

$user = User::firstOrCreate(['email' => $email], ['name' => 'Revisión temporal', 'password' => Hash::make(Str::random(48))]);
$user->syncRoles([$argv[1] ?? 'super_admin']);

$sesion = app('session')->driver();
$sesion->start();
$sesion->put(auth()->guard('web')->getName(), $user->id);
$sesion->save();

$nombre = config('session.cookie');
$valor = Crypt::encrypt(CookieValuePrefix::create($nombre, app('encrypter')->getKey()).$sesion->getId(), false);

echo "Usuario temporal {$user->id} con rol ".($argv[1] ?? 'super_admin')."\n";
echo "Cookie: {$nombre}\n{$valor}\n";
