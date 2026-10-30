<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Satpam;
use Illuminate\Auth\Access\HandlesAuthorization;

class SatpamPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Satpam');
    }

    public function view(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('View:Satpam');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Satpam');
    }

    public function update(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('Update:Satpam');
    }

    public function delete(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('Delete:Satpam');
    }

    public function restore(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('Restore:Satpam');
    }

    public function forceDelete(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('ForceDelete:Satpam');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Satpam');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Satpam');
    }

    public function replicate(AuthUser $authUser, Satpam $satpam): bool
    {
        return $authUser->can('Replicate:Satpam');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Satpam');
    }

}