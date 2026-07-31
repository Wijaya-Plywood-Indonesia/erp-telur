<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Kandang;
use Illuminate\Auth\Access\HandlesAuthorization;

class KandangPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Kandang');
    }

    public function view(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('View:Kandang');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Kandang');
    }

    public function update(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('Update:Kandang');
    }

    public function delete(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('Delete:Kandang');
    }

    public function restore(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('Restore:Kandang');
    }

    public function forceDelete(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('ForceDelete:Kandang');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Kandang');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Kandang');
    }

    public function replicate(AuthUser $authUser, Kandang $kandang): bool
    {
        return $authUser->can('Replicate:Kandang');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Kandang');
    }

}