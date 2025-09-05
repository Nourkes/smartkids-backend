<?php
// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $profil = $this->getProfil($user);
        
        $notifications = $profil->notifications()
            ->nonArchivees()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function nonLues()
    {
        $user = Auth::user();
        $profil = $this->getProfil($user);
        
        $notifications = $profil->notificationsNonLues()->get();
        
        return response()->json([
            'notifications' => $notifications,
            'count' => $notifications->count()
        ]);
    }

    public function marquerCommeLue($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->marquerCommeLue();
        
        return response()->json(['message' => 'Notification marquée comme lue']);
    }

    public function marquerToutesCommeLues()
    {
        $user = Auth::user();
        $profil = $this->getProfil($user);
        
        $profil->marquerToutesNotificationsCommeLues();
        
        return response()->json(['message' => 'Toutes les notifications marquées comme lues']);
    }

    public function archiver($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->archiver();
        
        return response()->json(['message' => 'Notification archivée']);
    }

    private function getProfil($user)
    {
        switch ($user->role) {
            case 'parent':
                return $user->parent;
            case 'educateur':
                return $user->educateur;
            case 'admin':
                return $user->admin;
            default:
                throw new \Exception('Rôle utilisateur non reconnu');
        }
    }
}