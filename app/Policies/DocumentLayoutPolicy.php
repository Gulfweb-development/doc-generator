<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentLayoutPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentLayout');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:DocumentLayout');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentLayout');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:DocumentLayout');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:DocumentLayout');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:DocumentLayout');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:DocumentLayout');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentLayout');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentLayout');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:DocumentLayout');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentLayout');
    }
}
