<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;

class ActivityNotification extends Notification
{
    public function __construct(
        private NotificationType $type,
        private array $data = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type->value;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }
}
