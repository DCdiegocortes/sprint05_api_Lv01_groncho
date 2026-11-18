<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->get();
        $actors = $this->actorsOf($notifications);

        return response()->json(
            $notifications->map(fn (DatabaseNotification $notification) => $this->present($notification, $actors))->values()
        );
    }

    public function unreadCount(Request $request)
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();

        return response()->json($this->present($notification, $this->actorsOf(collect([$notification]))));
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read'], 200);
    }

    public function destroy(Request $request, string $notification)
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return response()->json(['message' => 'Notification deleted'], 200);
    }

    private function actorsOf(Collection $notifications): Collection
    {
        $actorIds = $notifications->pluck('data.actor_id')->filter()->unique()->values();

        return User::whereIn('id', $actorIds)->get(['id', 'name', 'avatar'])->keyBy('id');
    }

    private function present(DatabaseNotification $notification, Collection $actors): array
    {
        $actorId = $notification->data['actor_id'] ?? null;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'actor' => $actorId ? $actors->get($actorId) : null,
            ...Arr::except($notification->data, ['actor_id']),
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}
