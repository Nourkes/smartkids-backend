<?php
// app/Models/ChatParticipant.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChatParticipant extends Model {
    protected $fillable=['room_id','user_id','role','last_read_at'];
    public function room(){ return $this->belongsTo(ChatRoom::class,'room_id'); }
    public function user(){ return $this->belongsTo(User::class); }
}

