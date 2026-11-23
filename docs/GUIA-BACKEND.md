# Guía de backend — Tenisline

Laravel 12 · PHP 8.4 · Filament 3 · Inertia 2 · MariaDB 11 · spatie/laravel-permission (Shield).
Servidor: AWS Lightsail (`deploy/lightsail/`). Diseño de pantallas: `docs/GUIA-DISENO.md`.

Esta guía es obligatoria para rutas, recursos, acciones y cambios de lógica.

---

## 1. Seguridad (lo primero)

1. **Policy en todo recurso de Filament** (`app/Policies/<Modelo>Policy.php`) con permisos de
   Shield. Sin policy, Filament permite todo a cualquiera que entre al panel.
   Excepción documentada: `CierrePolicy` (todo permitido salvo borrar, solo super_admin/administrador).
2. **Rutas web propias** (`routes/web.php`) dentro de `auth` y con el middleware **`personal`**
   (bloquea clientes y proveedores, que también inician sesión en la tienda). Con roles:
   `->middleware('personal:super_admin,administrador')`.
3. **Documentos (PDF/Excel) de un registro:** revisar lo mismo que el botón del admin que los abre:
   - policy: `Gate::authorize('factura', $venta)`;
   - o "el registro aparece en la tabla de ese usuario":
     `$this->autorizarComoEnTabla(VentaResource::class, $id)` (en `Controller`).
   Ocultar el botón (`->visible()`) **no** protege la ruta.
4. **Ningún GET con efectos** (generar/eliminar guías, cambiar estados, enviar SMS). Usar `POST`
   con rol. Webhooks externos: `routes/api.php` con un token compartido (ver
   `TokenWebhookGuatex`, variable `GUATEX_WEBHOOK_TOKEN`; sin ella quedan cerrados).
5. **Nunca** excepciones por ID de usuario (`if ($user->id == 10)`): usar un rol o permiso.
6. **Secretos solo en `.env`**: nada de credenciales como valor por defecto en `config/*.php`.
   Documentar la variable (vacía) en `.env.example`.
7. **SQL:** nada de texto del request dentro de `DB::raw`/`orderByRaw`/`whereRaw`; usar bindings
   (`whereRaw('x LIKE ?', [...])`) o una lista blanca de columnas.
8. Cabeceras de seguridad: middleware `SecurityHeaders` (global). CORS por `CORS_ALLOWED_ORIGINS`
   y expiración de tokens por `SANCTUM_EXPIRATION` (sin las variables, igual que antes).

## 2. Validación y archivos

- Validar en el servidor: FormRequest (`app/Http/Requests/...`) o `->rules()` en el formulario.
- Subidas: `FileUpload` ya va a S3 por defecto (`AppServiceProvider`); para imágenes usar
  `->image()`. La regla `image` de Laravel 12 no acepta SVG.
- Fechas con Carbon 3: `diffIn*` devuelve float con signo → `(int) abs(...)` si se espera entero.

## 3. Rendimiento

- **Eager loading** de lo que muestran las tablas: `->with([...])` en `getEloquentQuery()`.
- **Nada de consultas por fila**: ni en columnas ni en `->visible()`/`->badge()` de acciones.
  Usar relaciones cargadas (`relationLoaded`) o memorizar:
  - una vez **por petición**: `request()->attributes` (ver `VentaResource::hayBloqueo()`);
  - una vez **por registro**: memoria en el modelo (ver `Cierre::memo()`). Solo para valores que
    las acciones no cambian en la misma petición.
- Totales con `sum()`/`count()` en SQL, no sumando colecciones en PHP.
- Paginación en todos los listados (Filament ya pagina).
- Índices: migración **aditiva** que revise si ya existe:
  ```php
  if (! Schema::hasIndex('ventas', ['estado', 'created_at'])) {
      Schema::table('ventas', fn (Blueprint $t) => $t->index(['estado', 'created_at']));
  }
  ```
- **Servicios externos siempre con timeout y `try/catch`** (Guatex, FEL, Twilio, Labelary):
  `Http::connectTimeout(10)->timeout(30)->...`, `CURLOPT_CONNECTTIMEOUT`/`CURLOPT_TIMEOUT`,
  `connection_timeout` en `SoapClient`.
- Medir antes y después: `php scripts/revision/smoke-paginas.php <user_id> --medir`
  (consultas y ms por página) y `consultas-url.php <url>` (consultas agrupadas de una página).

## 4. Pruebas locales (seguras)

> ⚠️ El `.env` local tiene **llaves reales** (Twilio, Guatex, FEL, AWS S3, correo).

- **Nunca barrer rutas a ciegas** (ni GET): antes de llamar una ruta, leer su código.
- **No correr `php artisan test` contra la BD local.** `phpunit.xml` usa la BD `testing`; revisa
  que exista y esté aislada antes de correr Pest.
- Para revisar como en producción **sin riesgo**, en un worktree aparte:
  ```bash
  git worktree add ../tenisline-revision -b mi-rama origin/main
  cd ../tenisline-revision && composer install && npm ci && npm run build
  APP_DIR=$PWD scripts/revision/revision-local.sh up      # http://localhost:3002
  APP_DIR=$PWD scripts/revision/revision-local.sh down    # apaga y borra la copia
  ```
  Copia la BD local (volumen `tenisline-admin_sail-mariadb`) a un contenedor, levanta la app en una
  red Docker **sin internet** con las llaves externas falsas y genera un `.env` marcado que solo
  ese script borra. Se niega a usar el `.env` de tu carpeta principal.
- Scripts de solo lectura para el entorno aislado (`docker exec tl-rev-app php scripts/revision/...`):

| Script | Qué hace |
|---|---|
| `smoke-paginas.php [user] [--medir]` | Abre las páginas de los 3 paneles y la tienda como ese usuario (sin edición). |
| `probar-permisos.php` | PDF, reportes y Guatex con un usuario de cada rol: 200 / 403 / 302 según lo esperado. |
| `probar-orden.php [user]` | Ordena cada columna `sortable` de cada tabla en ambas direcciones. |
| `orden-por-defecto.php [user]` | Primeros IDs de cada tabla sin orden ni filtros (comparar contra `main`). |
| `generar-documentos.php <carpeta>` | Genera los PDF y Excel con datos reales (para comparar antes/después). |
| `exportar-tablas.php <carpeta>` | Exportación Excel de las tablas (Kardex, Productos). |
| `probar-filtros.php` | Aplica filtros y muestra los chips. |
| `sesion-temporal.php [rol] / --borrar` | Usuario y sesión temporal para el navegador (sin contraseña). Borrar al terminar. |

- Para comparar con `main`: otro contenedor en la misma red aislada con el código de `main`
  (`git worktree add --detach ../tenisline-base origin/main`) y los mismos scripts.
- Antes del PR: `php -l` en los archivos tocados, `php artisan route:list` y `npm run build`.

## 5. Deploy

- Merge a `main` → GitHub Actions `.github/workflows/deploy-lightsail.yml` → `deploy/lightsail/deploy.sh`
  en el servidor: `composer install`, `npm ci && npm run build`, `php artisan migrate --force`,
  `optimize`, `filament:optimize`, `queue:restart`.
- Servidor: PHP 8.4 y Node 20 (`deploy/lightsail/setup-server.sh`).
- **Migraciones solo aditivas** (agregar, nunca borrar o renombrar columnas) e idempotentes:
  el deploy las corre solo.
- La configuración de nginx (`deploy/lightsail/add-site.sh`) **no** se aplica con el deploy; si
  cambia, hay que aplicarla a mano en el servidor.
- Antes de hacer merge: `git log origin/main..<rama>` para confirmar que ningún commit quede fuera.

## 6. Checklist antes del PR

- [ ] Recurso con policy; ruta con `auth` + `personal` (y roles si aplica), igual que el botón del admin.
- [ ] Ningún GET con efectos; webhooks con token.
- [ ] Sin texto del request en SQL; secretos solo en `.env`.
- [ ] Sin N+1 (eager loading), sin consultas por fila, totales en SQL.
- [ ] Servicios externos con timeout y try/catch.
- [ ] Migraciones aditivas que revisan si ya existe el índice o la columna.
- [ ] Probado en el entorno aislado con los roles que usan la pantalla (y uno que no).
- [ ] Sin filtros ni orden, la respuesta es idéntica a antes.
