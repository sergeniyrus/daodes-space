<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'wallet_address', 'role', 'avatar_url', 'nickname', 'timezone', 
        'languages', 'birth_date', 'education', 'resume', 'portfolio', 'specialization', 
        'rating', 'trust_level', 'sbt_tokens', 'tasks_completed', 'tasks_failed', 
        'recommendations', 'activity_log', 'achievements', 'gender',        
        'telegram_chat_id',  
        'public_key',
        'status_text',
        'status_emoji',
        'status_expires_at',
        'card_position',
        'card_company',
        'card_location',
        'card_about',
        'card_website',
    ];

    protected $casts = [
        'status_expires_at' => 'datetime',
    ];

    public $timestamps = false; 

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
}