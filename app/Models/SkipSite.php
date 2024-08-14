<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkipSite extends Model
{
    protected $table = 'skip_sites';
    protected $fillable = [
        'url',
    ];
}
