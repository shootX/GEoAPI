<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComingSoonSignup extends Model
{
    protected $fillable = [
        'email',
        'locale',
    ];
}
