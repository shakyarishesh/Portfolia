<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;

/** Admins manage every portfolio. An owner (portfolios.user_id) may preview their own unpublished portfolio. */
class PortfolioPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Portfolio $portfolio): bool
    {
        return false;
    }

    public function delete(User $user, Portfolio $portfolio): bool
    {
        return false;
    }

    public function preview(User $user, Portfolio $portfolio): bool
    {
        return $portfolio->user_id !== null && $portfolio->user_id === $user->id;
    }
}
