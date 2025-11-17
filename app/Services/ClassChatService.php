<?php
// app/Services/ClassChatService.php
namespace App\Services;
use App\Models\{ChatRoom, ChatParticipant, Classe, ParentModel, Educateur, User};

class ClassChatService {
    public function ensureRoomForClasse(int $classeId): ChatRoom
    {
        $room = ChatRoom::firstOrCreate(
            ['classe_id' => $classeId],
            ['title' => null]
        );

        $classeTable = (new Classe)->getTable();
        $educateurUserIds = User::whereHas('educateur.classes', function ($q) use ($classeId, $classeTable) {
                $q->where("$classeTable.id", $classeId);  
            })
            ->pluck('id');
        $parentUserIds = User::whereHas('parent.enfants', function ($q) use ($classeId) {
                $q->where('classe_id', $classeId);
            })
            ->pluck('id');

        $toAdd = $educateurUserIds->map(fn ($id) => ['user_id' => $id, 'role' => 'educateur'])
            ->merge($parentUserIds->map(fn ($id) => ['user_id' => $id, 'role' => 'parent']));

        foreach ($toAdd as $p) {
            ChatParticipant::firstOrCreate(
                ['room_id' => $room->id, 'user_id' => $p['user_id']],
                ['role' => $p['role']]
            );
        }

        return $room->loadCount('messages');
    }
}
