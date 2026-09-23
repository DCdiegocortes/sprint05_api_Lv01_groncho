<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function view(User $user, Item $item): bool
    {
        return $user->role === 'admin' || $user->id === $item->user_id;
    }

    public function update(User $user, Item $item): bool
    {
        return $this->view($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->view($user, $item);
    }
}
