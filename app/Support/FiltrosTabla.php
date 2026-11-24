<?php

namespace App\Support;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Comportamiento común de los filtros de todas las tablas (ver docs/GUIA-DISENO.md):
 * - botón "Filtros" con texto (no solo el ícono),
 * - los filtros se recuerdan por módulo (sesión),
 * - chips con × para los filtros con formulario (fechas, selects) que no definen indicateUsing().
 */
class FiltrosTabla
{
    public static function registrar(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->persistFiltersInSession()
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filtros')));

        // Si el filtro define su propio indicateUsing() después, ese reemplaza a este.
        // isImportant: se aplica después del setUp() de Filament (que pone su indicador por defecto)
        Filter::configureUsing(fn (Filter $filter) => $filter->indicateUsing(
            fn (array $state) => static::indicadores($filter, $state)
        ), isImportant: true);
    }

    /** @return array<string, Indicator>|array<Indicator> */
    public static function indicadores(Filter $filter, array $state): array
    {
        // Filtro simple (casilla): igual que Filament, el chip es el nombre del filtro
        if (! $filter->hasFormSchema()) {
            return ($state['isActive'] ?? false) ? [Indicator::make($filter->getIndicator())] : [];
        }

        $indicadores = [];
        foreach (static::campos($filter->getFormSchema()) as $campo) {
            $nombre = $campo->getName();
            $valor = $state[$nombre] ?? null;
            if (blank($valor) || $valor === false) {
                continue;
            }

            $etiqueta = static::texto(fn () => $campo->getLabel()) ?: $filter->getLabel();
            $texto = static::valor($campo, $valor);

            $indicadores[$nombre] = Indicator::make($texto === null ? $etiqueta : "{$etiqueta}: {$texto}")
                ->removeField($nombre);
        }

        return $indicadores;
    }

    /** @return array<Field> */
    private static function campos(array $componentes): array
    {
        $campos = [];
        foreach ($componentes as $componente) {
            if ($componente instanceof Field) {
                $campos[] = $componente;
            } elseif ($componente instanceof Component) {
                $campos = [...$campos, ...static::campos(static::texto(fn () => $componente->getChildComponents()) ?: [])];
            }
        }

        return $campos;
    }

    private static function valor(Field $campo, mixed $valor): ?string
    {
        if ($campo instanceof Toggle || $campo instanceof Checkbox) {
            return null;
        }

        if ($campo instanceof DatePicker || $campo instanceof DateTimePicker) {
            return static::texto(function () use ($campo, $valor) {
                $fecha = Carbon::parse($valor);

                return $fecha->format($campo instanceof DateTimePicker && $fecha->format('H:i') !== '00:00' ? 'd/m/Y H:i' : 'd/m/Y');
            }) ?: (string) $valor;
        }

        if ($campo instanceof Select) {
            $opciones = static::texto(fn () => $campo->getOptions()) ?: [];
            $etiquetas = collect((array) $valor)->map(fn ($v) => static::opcion($opciones, $v) ?? $v);

            return $etiquetas->implode(', ');
        }

        return is_array($valor) ? implode(', ', $valor) : (string) $valor;
    }

    private static function opcion(array $opciones, mixed $valor): ?string
    {
        foreach ($opciones as $clave => $etiqueta) {
            if (is_array($etiqueta)) { // opciones agrupadas
                if (($encontrada = static::opcion($etiqueta, $valor)) !== null) {
                    return $encontrada;
                }
            } elseif ((string) $clave === (string) $valor) {
                return (string) $etiqueta;
            }
        }

        return null;
    }

    /** Evalúa algo que podría necesitar el contenedor del formulario; si falla, devuelve null. */
    private static function texto(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
