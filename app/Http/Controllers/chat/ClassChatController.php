<?php
// app/Http/Controllers/Chat/ClassChatController.php
namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\{ChatRoom, ChatParticipant, ChatMessage, Classe};
use App\Services\ClassChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
class ClassChatController extends Controller
{
    public function file(string $path): StreamedResponse
{
    // On travaille sur le disk 'public' (storage/app/public)
    abort_unless(Storage::disk('public')->exists($path), 404);

    // Optionnel : sécuriser le répertoire
    abort_unless(str_starts_with($path, 'chat_attachments/'), 403);

    // Retourne le fichier (avec les bons headers)
    return Storage::disk('public')->response($path);
}
    public function __construct(private ClassChatService $svc) {}

    // liste des salons où je suis membre
// app/Http/Controllers/Chat/ClassChatController.php
    public function myRooms(Request $r)
    {
        $user = $r->user();
        $classeIds = collect();

        // nom réel de la table "classe"/"classes"
        $classeTable = (new Classe)->getTable();

        // Éducateur -> classes()
        if ($user->educateur) {
            $ids = $user->educateur
                ->classes()
                ->pluck("$classeTable.id");   // <-- pas de nom en dur
            $classeIds = $classeIds->merge($ids);
        }

        // Parent -> classe_id des enfants
        if ($user->parent) {
            $ids = $user->parent->enfants()
                ->whereNotNull('classe_id')
                ->pluck('classe_id');
            $classeIds = $classeIds->merge($ids);
        }

        // Assurer la création des salons + inscription des participants
        foreach ($classeIds->unique() as $cid) {
            $this->svc->ensureRoomForClasse((int)$cid);
        }

        // Lister les salons où je suis participant
        $rooms = ChatRoom::whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with('classe:id,nom,niveau')
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($room) => [
                'id' => $room->id,
                'classe' => [
                    'id' => $room->classe_id,
                    'nom' => $room->classe?->nom,
                    'niveau' => $room->classe?->niveau,
                ],
                'messages_count' => $room->messages_count,
            ]);

        return response()->json(['success' => true, 'data' => $rooms]);
    }


    // créer/retourner le salon d'une classe
    public function ensureRoom(Request $r, Classe $classe) {
        $room = $this->svc->ensureRoomForClasse($classe->id);
        $this->authorizeJoin($r->user()->id, $room->id);
        return response()->json(['success'=>true,'data'=>['room_id'=>$room->id]]);
    }

    // liste des messages
    public function messages(Request $r, ChatRoom $room) {
        $this->authorizeJoin($r->user()->id, $room->id);

        $limit = min(50, (int)$r->query('limit', 30));
        $before = $r->query('before'); // ISO date to paginate up
        $q = $room->messages()->with('user:id,name')
            ->orderBy('id','desc');
        if ($before) { $q->where('created_at','<',$before); }
        $list = $q->limit($limit)->get()->reverse()->values();

        return response()->json(['success'=>true,'data'=>$list]);
    }

    // envoyer un message (texte + fichier optionnel)
    public function send(Request $r, ChatRoom $room) {
        $this->authorizeJoin($r->user()->id, $room->id);
        $r->validate([
            'body'=>'nullable|string',
            'type'=>'nullable|in:text,image,file',
            'attachment'=>'nullable|file|max:8192',
        ]);
        $type = $r->input('type','text');
        $path = null;
        if ($r->hasFile('attachment')) {
            $path = $r->file('attachment')->store('chat_attachments','public');
            $type = str_starts_with($r->file('attachment')->getMimeType(),'image/') ? 'image' : 'file';
        }
        $msg = $room->messages()->create([
            'user_id'=>$r->user()->id,
            'body'=>$r->input('body'),
            'type'=>$type,
            'attachment_path'=>$path,
        ]);
        // MAJ updated_at du room pour tri
        $room->touch();

        return response()->json(['success'=>true,'data'=>$msg->load('user:id,name')], 201);
    }

    public function participants(Request $r, ChatRoom $room) {
        $this->authorizeJoin($r->user()->id, $room->id);
        $parts = $room->participants()->with('user:id,name')->get()
            ->map(fn($p)=>['id'=>$p->user_id,'name'=>$p->user->name,'role'=>$p->role]);
        return response()->json(['success'=>true,'data'=>$parts]);
    }

    public function markRead(Request $r, ChatRoom $room) {
        $this->authorizeJoin($r->user()->id, $room->id);
        $p = ChatParticipant::firstOrCreate(['room_id'=>$room->id,'user_id'=>$r->user()->id]);
        $p->last_read_at = now(); $p->save();
        return response()->json(['success'=>true]);
    }

    private function authorizeJoin(int $userId, int $roomId): void {
        abort_unless(
            \App\Models\ChatParticipant::where('room_id',$roomId)->where('user_id',$userId)->exists(),
            403, 'Not participant of this room.'
        );
    }
}
