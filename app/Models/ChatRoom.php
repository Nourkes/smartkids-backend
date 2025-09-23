<?php
// app/Models/ChatRoom.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model {
    protected $fillable = ['classe_id','title'];
    public function classe(){ return $this->belongsTo(Classe::class); }
    public function participants(){ return $this->hasMany(ChatParticipant::class,'room_id'); }
    public function messages(){ return $this->hasMany(ChatMessage::class,'room_id'); }
}
