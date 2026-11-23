# Tenisline — instrucciones para agentes de IA (mismo contenido que CLAUDE.md)

Laravel 12 · PHP 8.4 · Filament 3 (paneles `/admin`, `/ventas`, `/inventario`) · Inertia 2 + React 18
(tienda) · MariaDB 11 · AWS Lightsail. Repo: `JordinDre/Tenisline`.

## Reglas obligatorias

- **Antes de crear o cambiar una pantalla, tabla o vista: leer [`docs/GUIA-DISENO.md`](docs/GUIA-DISENO.md).**
- **Antes de tocar rutas, recursos, policies o lógica: leer [`docs/GUIA-BACKEND.md`](docs/GUIA-BACKEND.md).**
- Responder en español, concreto y breve. **No cambiar funcionalidad que ya funciona**; si un
  arreglo cambia el comportamiento, preguntar primero. Los rediseños son solo visuales.
- **El `.env` local tiene llaves reales** (Twilio, Guatex, FEL, S3, correo): nunca barrer rutas a
  ciegas; leer el código antes de llamar una ruta. Probar en el entorno aislado
  (`scripts/revision/revision-local.sh`, sin internet, copia de la BD) con `scripts/revision/*`.
- No correr `php artisan test` contra la BD local. No escribir contraseñas: usar
  `scripts/revision/sesion-temporal.php` en el entorno aislado y borrarlo al terminar.
- Todo recurso con **policy**; rutas propias con `auth` + `personal`; ningún GET con efectos.
- Tablas: filtros útiles (chips y "Limpiar" ya son globales), orden por columna en SQL y orden
  por defecto con `->defaultSort()` (nunca `orderBy` en `getEloquentQuery()`).
- Sin colores hex ni sombras grandes; modo oscuro y 390 px sin scroll horizontal.
- Servicios externos con timeout y try/catch. Secretos solo en `.env`.
- Migraciones solo aditivas (el deploy corre `migrate --force`).
- Trabajo grande: rama desde `main` en un `git worktree` aparte (no tocar el trabajo sin commit),
  un solo PR, y revisar `git log origin/main..<rama>` antes del merge.

## Comandos

- Front: `npm run build` (tienda y tema de Filament).
- Revisión aislada (en un worktree aparte): `APP_DIR=$PWD scripts/revision/revision-local.sh up | status | down` → http://localhost:3002
- Deploy: merge a `main` (GitHub Actions → `deploy/lightsail/deploy.sh`).
