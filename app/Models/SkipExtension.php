<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkipExtension extends Model
{
    protected $table = 'skip_extensions';
    protected $fillable = [
        'extension',
    ];
}
