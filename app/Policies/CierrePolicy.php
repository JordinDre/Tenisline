<?php

namespace App\Policies;

use App\Models\Cierre;
use App\Models\User;

/**
 * Cierres de caja. Hasta nov-2026 no tenía policy (todo permitido). Se mantiene igual: el personal
 * ve, abre y cierra sus cierres (CierreResource limita cuáles ve cada quien), pero borrar
 * queda solo para super_admin y administrador.
 */
class CierrePolicy
{
    private const PUEDEN_BORRAR = ['super_admin', 'administrador'];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cierre $cierre): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Cierre $cierre): bool
    {
        return true;
    }

    public function delete(User $user, Cierre $cierre): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function forceDelete(User $user, Cierre $cierre): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function restore(User $user, Cierre $cierre): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasAnyRole(self::PUEDEN_BORRAR);
    }

    public function replicate(User $user, Cierre $cierre): bool
    {
        return true;
    }

    public function reorder(User $user): bool
    {
        return true;
    }
}
