<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): mixed {
                if (is_array($value)) {
                    return $value;
                }

                $decoded = json_decode((string) $value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE) ?: 'null',
        );
    }
}
