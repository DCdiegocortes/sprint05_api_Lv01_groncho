<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Models\Item;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(User::all());
    }

    public function show(User $user)
    {
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $itemIds = Item::where('user_id', $user->id)->select('id');

        abort_if(
            Exchange::whereIn('requested_item_id', $itemIds)->orWhereIn('offered_item_id', $itemIds)->exists(),
            409,
            'Cannot delete a user whose items are part of an exchange.'
        );

        $user->delete();

        return response()->json(['message' => 'User deleted'], 200);
    }
}
