<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LastVisitedUrl extends Model
{
    protected $table = 'last_visited_urls';
    protected $fillable = [
        'url',
    ];
}
