<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlastEmail extends Model
{
    protected $table = 'blastemail';
    protected $fillable = [
        'email',
    ];
}
