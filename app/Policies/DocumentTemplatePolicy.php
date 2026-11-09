<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentTemplate');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:DocumentTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentTemplate');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:DocumentTemplate');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:DocumentTemplate');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:DocumentTemplate');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:DocumentTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentTemplate');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:DocumentTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentTemplate');
    }
}
