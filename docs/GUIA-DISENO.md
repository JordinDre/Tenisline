# Guía de diseño — Tenisline (admin y tienda)

Esta guía es **obligatoria** para cualquier pantalla, módulo, tabla o vista nueva (y para cambios
grandes en las existentes). Objetivo: que todo se vea y se comporte igual, en modo claro y oscuro,
en celular y en escritorio.

Hay dos interfaces en el mismo repo:

| Interfaz | Tecnología | Dónde |
|---|---|---|
| **Admin** (3 paneles: `/admin`, `/ventas`, `/inventario`) | Filament 3 + Livewire | `app/Filament/**`, `app/Providers/Filament/*PanelProvider.php` |
| **Tienda** (`/`, `/catalogo`, `/producto/...`, `/marcas`) | Inertia 2 + React 18 + Tailwind 3 | `resources/js/Pages`, `resources/js/Components/tienda` |

> Si algo de esta guía no alcanza para un caso nuevo, se resuelve **una vez** en el lugar común
> (tema, `app/Support/FiltrosTabla.php`, un componente de `Components/tienda`) y se documenta aquí.
> No se arregla con estilos sueltos dentro de una vista.

---

## 1. Principios

1. **Simple y minimalista**, con la identidad del logo: negro y blanco con **naranja `#ff4f17`**
   como acento (el mismo de la tienda).
2. **Sin sombras grandes**, poco relleno, bordes suaves (`rounded-lg`), espaciado uniforme.
3. **Botones en MAYÚSCULAS**, mismo tamaño y estilo en todo el sistema.
4. **El título lo pone el header** de la pantalla (Filament). No repetirlo dentro de la vista.
5. **Modo claro y oscuro siempre.** Responsive desde el inicio (probar a 375–390 px).
6. **Solo visual:** un rediseño no cambia flujos, campos ni permisos.

---

## 2. Colores y tema

### Admin (Filament)

- Color primario: `#ff4f17`, registrado en `AppServiceProvider` (`FilamentColor::register`) y como
  color por defecto del plugin de temas (`config/themes.php`). Un administrador puede elegir otro
  en la página **Temas**; no cambiarlo en código para un solo panel.
- Estilos propios de los 3 paneles: **`resources/css/filament/admin/theme.css`** (un solo archivo).
  Ahí están: botones en mayúsculas, sin sombras, bordes suaves, inputs de 16 px en celular,
  avisos a lo ancho en celular y "Limpiar"/contador de filtros solo cuando hay filtros.
  Después de editarlo: `npm run build`.
- Colores de botones/acciones: usar los nombres registrados (`primary`, `success`, `danger`,
  `warning`, `info`, `gray`, …). **Nada de hex** en vistas.
- Logos del panel: `public/images/logo-panel-negro.png` (claro) y `logo-panel-blanco.png` (oscuro).
  **PNG**, no WebP.

### Tienda (React)

- Colores en `tailwind.config.js`: `brand` (`#ff4f17`), `ink` (`#0a0a0a`) y los tokens de shadcn
  (`background`, `foreground`, `border`, …).
- `tailwind.paleta.js` conserva la paleta que antes ponía flowbite (`blue-600`, `gray-500`, …).
  **No borrarla**: la tienda usa esas tonalidades.
- No usar daisyUI ni flowbite (se quitaron). Componentes: `resources/js/Components/ui/*` (shadcn)
  y `resources/js/Components/tienda/*`.

---

## 3. Pantalla nueva en el admin (Filament)

1. Crear el recurso en el panel que corresponde (`app/Filament/{Ventas,Inventario}/Resources` o
   `app/Filament/Resources` para el admin).
2. **Policy obligatoria** (`app/Policies/<Modelo>Policy.php`) con los permisos de Shield
   (`view_any_<modelo>`, `create_<modelo>`, …). Sin policy, Filament deja hacer **todo** a todos.
   Ver `docs/GUIA-BACKEND.md`.
3. Si el listado debe mostrar solo ciertos registros por usuario (bodegas, dueño), filtrarlo en
   `getEloquentQuery()` — y **sin** `orderBy` ahí (ver §4.2).
4. Título: lo pone Filament con `$label`/`$pluralLabel`/`$title`. En vistas Blade propias
   (`resources/views/filament/**`) no poner otro `<h1>`/`<h2>` con el mismo texto.
5. Vistas Blade propias (modales, widgets): componentes de Filament
   (`<x-filament::button>`, `<x-filament::section>`, `<x-filament::badge>`, `<x-filament::icon>`)
   en lugar de HTML con estilos propios.

### Botones

- Acciones de Filament (`Action::make`, `CreateAction`, …) ya salen en MAYÚSCULAS y con el mismo
  tamaño (tema). No agregar `->extraAttributes(['class' => ...])` para cambiar su estilo.
- En Blade: `<x-filament::button>` (con `tag="a"` y `:href` para enlaces). Nunca un `<button>` con
  colores propios.

---

## 4. Tablas

### 4.1 Filtros (todas las tablas con datos deben tener filtros útiles)

Ya está configurado para **todas** las tablas en `app/Support/FiltrosTabla.php`:

- Botón **FILTROS** con texto.
- Filtros **recordados por módulo** (`persistFiltersInSession`).
- **Chips** de filtros aplicados con **×** para quitar uno solo, también en filtros con formulario
  (fechas, selects). Si un filtro define su propio `->indicateUsing()`, se usa ese.
- **LIMPIAR** (y el contador del botón) solo aparece cuando hay filtros aplicados.

Al crear filtros:

```php
->filters([
    SelectFilter::make('bodega_id')->label('Bodega')->relationship('bodega', 'bodega'),
    Filter::make('created_at')
        ->form([
            DatePicker::make('desde')->label('Desde'),
            DatePicker::make('hasta')->label('Hasta'),
        ])
        ->query(fn (Builder $q, array $data) => $q
            ->when($data['desde'], fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($data['hasta'], fn ($q, $v) => $q->whereDate('created_at', '<=', $v))),
    // los chips "Desde: 01/10/2026 ×" salen solos
])
```

- Diseño: el de por defecto (desplegable) o `layout: FiltersLayout::AboveContentCollapsible`.
  **No** usar `FiltersLayout::AboveContent` (en celular ocupa toda la pantalla antes de los datos).
- Los filtros solo agregan condiciones: **sin filtros, la tabla debe mostrar lo mismo que antes**.

### 4.2 Orden por columna (siempre en el servidor, sobre todos los registros)

- Columnas de la tabla: `->sortable()`. Relaciones `belongsTo` con punto (`'cliente.name'`)
  funcionan solas.
- Columnas **calculadas** (accessor, `getStateUsing`, suma de una relación `hasMany`): ordenar con
  la **misma** cuenta en SQL. Ejemplos reales: `CierreResource` (Total), `TrasladoResource`
  (Total pares), `CajaChicaResource` (Monto):

  ```php
  TextColumn::make('total_pares')
      ->getStateUsing(fn (Traslado $r) => $r->detalles->sum('cantidad_enviada'))
      ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
          TrasladoDetalle::selectRaw('COALESCE(SUM(cantidad_enviada), 0)')
              ->whereColumn('traslado_detalles.traslado_id', 'traslados.id'),
          $direction
      )),
  ```
- Si la columna no existe en la tabla (p. ej. `deleted_at` sin SoftDeletes), **no** la marques
  `->sortable()`.
- **Orden por defecto con `->defaultSort()`** en la tabla, nunca con `orderBy` en
  `getEloquentQuery()` (eso anula el orden por columna). Para órdenes especiales:
  `->defaultSort(fn (Builder $q) => $q->orderByRaw('...'))`.
- Probar: `php scripts/revision/probar-orden.php` (ver `docs/GUIA-BACKEND.md` §4).

### 4.3 Rendimiento en tablas

- Relaciones que muestran las columnas: `->with([...])` en `getEloquentQuery()`.
- Nada de consultas por fila en columnas o en `->visible(fn ($record) => ...)`: usar la relación
  ya cargada (`$record->relationLoaded('factura') ? $record->factura !== null : ...`).

---

## 5. Modo oscuro

- Filament lo maneja solo con sus componentes.
- En Blade propio, **cada** clase de color lleva su variante oscura:

| Claro | Oscuro |
|---|---|
| `bg-white` | `dark:bg-gray-900` |
| `bg-gray-50` / `bg-zinc-50` | `dark:bg-white/5` |
| `bg-gray-100` | `dark:bg-white/10` |
| `text-gray-900` / `text-gray-800` | `dark:text-gray-100` |
| `text-gray-700` | `dark:text-gray-200` |
| `text-gray-500` | `dark:text-gray-400` |
| `border-gray-200`, `divide-gray-200` | `dark:border-white/10`, `dark:divide-white/10` |

- Después de agregar clases nuevas en Blade: `npm run build` (el tema de Filament las compila).

---

## 6. Responsive (375–390 px)

- **Sin scroll horizontal de la página**; las tablas se desplazan dentro de su caja.
- Inputs de **16 px** en celular (ya global en `theme.css` y `resources/css/app.css`) para que el
  iPhone no haga zoom. No bajarles el tamaño con clases.
- Avisos (notificaciones) arriba y a lo ancho en celular (tema).
- Filas de botones con `flex flex-wrap gap-2`; grids `grid-cols-1 sm:grid-cols-2 …`; nada de
  anchos fijos en px.
- Tienda: imágenes con `ImagenProducto` (ya trae `srcset`, `loading="lazy"`, `decoding="async"`);
  animaciones ligeras en celular.

---

## 7. PWA

- Manifest y service worker: `app/Services/Pwa/ManifestService.php`, `resources/views/pwa/meta.blade.php`,
  `public/serviceworker.js` (no guarda caché a propósito). Ajustes en BD, grupo `pwa`.
- Sugerencia de instalar en celular: `resources/views/pwa/instalar.blade.php` (Android: aviso
  nativo; iPhone: "Compartir → Agregar a inicio"; se oculta 14 días al cerrarla).
- Los dos se registran en los 3 paneles con `renderHook` (`HEAD_END` y `BODY_END`).
- Íconos y logos en **PNG**.

---

## 8. Checklist antes del PR

- [ ] Policy con permisos de Shield; la ruta/acción valida el rol en el servidor (no solo `->visible()`).
- [ ] La tabla tiene filtros útiles; sin filtros muestra lo mismo que antes.
- [ ] Cada columna `->sortable()` ordena en SQL en ambas direcciones (`probar-orden.php` sin errores).
- [ ] Orden por defecto con `->defaultSort()`, no en `getEloquentQuery()`.
- [ ] Sin colores hex ni sombras grandes; botones con componentes de Filament.
- [ ] Se ve bien en **modo oscuro** (sin recuadros blancos).
- [ ] Se ve bien a **390 px** sin scroll horizontal.
- [ ] Un solo título (el del header).
- [ ] `npm run build` sin errores.
