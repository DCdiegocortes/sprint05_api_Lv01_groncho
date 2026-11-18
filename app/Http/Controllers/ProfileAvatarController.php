<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateAvatarRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileAvatarController extends Controller
{
    public function store(UpdateAvatarRequest $request)
    {
        $user = $request->user();
        $previousAvatar = $user->avatar;

        $user->update(['avatar' => $request->file('avatar')->store('avatars', 'public')]);

        if ($previousAvatar) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return response()->json($user->fresh());
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json($user->fresh());
    }
}
