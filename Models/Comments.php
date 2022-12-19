<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'cases_id',
        'description',
        'is_system',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_by', 'updated_at',
    ];

    /**
     * user function
     *
     * @return user list
     */
    public function user()
    {
        return $this->belongsTo(user::class)->select(['id', 'first_name', 'last_name', 'role']);
    }

    /**
     * commentsAttachments function
     *
     * @return comments attachments
     */
    public function commentsAttachments()
    {
        return $this->hasMany(CommentsAttachments::class);
    }
}
