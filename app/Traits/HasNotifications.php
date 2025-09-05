<?php
// app/Traits/HasNotifications.php

namespace App\Traits;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasNotifications
{
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')
                    ->orderBy('created_at', 'desc');
    }

    public function notificationsNonLues()
    {
        return $this->notifications()->nonLues();
    }

    public function notificationsNonArchivees()
    {
        return $this->notifications()->nonArchivees();
    }

    public function getNombreNotificationsNonLues()
    {
        return $this->notificationsNonLues()->count();
    }

    public function marquerToutesNotificationsCommeLues()
    {
        $this->notificationsNonLues()->update([
            'lu' => true,
            'lu_at' => now()
        ]);
    }

    public function archiverToutesNotifications()
    {
        $this->notifications()->update([
            'archive' => true,
            'archive_at' => now()
        ]);
    }
}