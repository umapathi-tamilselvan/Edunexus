<?php

namespace App\Support\Settings;

use Illuminate\Database\Eloquent\Model;

/**
 * An institution-level setting in the client DB, changed by its admins
 * (e.g. attendance edit window, receipt prefix).
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
