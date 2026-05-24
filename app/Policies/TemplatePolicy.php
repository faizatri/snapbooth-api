<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Template $template): bool
    {
        return $template->is_public
            || $template->isSystemTemplate()
            || $template->isOwnedBy($user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner can update; system templates (user_id null) cannot be edited.
     */
    public function update(User $user, Template $template): bool
    {
        return $template->isOwnedBy($user->id);
    }

    /**
     * Only the owner can delete; system templates are protected.
     */
    public function delete(User $user, Template $template): bool
    {
        return $template->isOwnedBy($user->id);
    }
}
