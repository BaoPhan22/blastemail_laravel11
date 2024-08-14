<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitedUrls extends Model
{
    protected $table = 'visited_urls';
    protected $fillable = [
        'url',
    ];
}
