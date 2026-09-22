<?php

namespace App\Policies;

use App\Models\Universe;
use App\Models\User;

class UniversePolicy
{
    public function update(User $user, Universe $universe): bool
    {
        return $user->role === 'admin' || $user->id === $universe->user_id;
    }

    public function delete(User $user, Universe $universe): bool
    {
        return $this->update($user, $universe);
    }
}
