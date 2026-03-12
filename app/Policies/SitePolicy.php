<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function delete(User $user, Site $site): bool
    {
        return $user->id === $site->server->user_id;
    }
}
