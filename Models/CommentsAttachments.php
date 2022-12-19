<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommentsAttachments extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cases_id',
        'user_id',
        'comments_id',
        'document',
        'document_path',
        'document_type',
        'dispay_path',
        'id',
    ];
}
