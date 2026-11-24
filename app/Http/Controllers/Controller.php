<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    /**
     * Permite abrir el documento de un registro solo si el usuario puede ver la tabla del recurso
     * y el registro aparece en ella (mismo alcance que el botón del admin: bodegas, dueño, etc.).
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function autorizarComoEnTabla(string $resource, int|string $id): void
    {
        Gate::authorize('viewAny', $resource::getModel());
        abort_unless($resource::getEloquentQuery()->whereKey($id)->exists(), 403);
    }
}
