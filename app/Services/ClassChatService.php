<?php
// app/Services/ClassChatService.php
namespace App\Services;
use App\Models\{ChatRoom, ChatParticipant, Classe, ParentModel, Educateur, User};

class ClassChatService {
    /** crée ou retourne le salon de la classe et s'assure des participants */
    public function ensureRoomForClasse(int $classeId): ChatRoom
    {
        $room = ChatRoom::firstOrCreate(
            ['classe_id' => $classeId],
            ['title' => null]
        );

        // nom réel de la table "classe"/"classes"
        $classeTable = (new Classe)->getTable();

        // Éducateurs de la classe (relation educateur.classes)
        $educateurUserIds = User::whereHas('educateur.classes', function ($q) use ($classeId, $classeTable) {
                $q->where("$classeTable.id", $classeId);  // <-- pas de nom en dur
            })
            ->pluck('id');

        // Parents ayant au moins un enfant dans la classe
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
