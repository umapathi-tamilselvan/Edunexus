<?php

namespace App\Domain\Fee\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a due is for, e.g. "Tuition" or "Transport".
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['name'])]
class FeeHead extends Model {}
