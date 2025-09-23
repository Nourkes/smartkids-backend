<?php
// app/Models/ChatMessage.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model {
    protected $fillable=['room_id','user_id','body','type','attachment_path'];
    protected $appends=['attachment_url'];
    public function room(){ return $this->belongsTo(ChatRoom::class,'room_id'); }
    public function user(){ return $this->belongsTo(User::class); }
    public function getAttachmentUrlAttribute(){
        return $this->attachment_path ? asset('storage/'.$this->attachment_path) : null;
    }
}
