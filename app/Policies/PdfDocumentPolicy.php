<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PdfDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PdfDocument');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:PdfDocument');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PdfDocument');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:PdfDocument');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:PdfDocument');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:PdfDocument');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:PdfDocument');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PdfDocument');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PdfDocument');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:PdfDocument');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PdfDocument');
    }
}
