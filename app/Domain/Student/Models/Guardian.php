<?php

namespace App\Domain\Student\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A parent or guardian. One guardian can be linked to several students
 * (siblings), and may later get a login of their own.
 *
 * The phone number is encrypted at rest; phone_hash allows exact lookups.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $phone_hash
 * @property string|null $email
 * @property int|null $user_id
 */
#[Fillable(['name', 'phone', 'email'])]
#[Hidden(['phone_hash'])]
#[UseFactory(GuardianFactory::class)]
class Guardian extends Model implements AuditableContract
{
    /** @use HasFactory<GuardianFactory> */
    use Auditable, HasFactory;

    /**
     * Audit rows must not hold the decrypted phone number or its hash.
     *
     * @var list<string>
     */
    protected $auditExclude = ['phone', 'phone_hash'];

    protected static function booted(): void
    {
        static::saving(function (self $guardian): void {
            $guardian->phone_hash = self::hashPhone($guardian->phone);
        });
    }

    /**
     * Keyed hash of a normalised phone number, for searching without
     * decrypting every row.
     */
    public static function hashPhone(string $phone): string
    {
        return hash_hmac('sha256', self::normalisePhone($phone), (string) config('app.key'));
    }

    /**
     * Digits only, without an Indian +91 / 0 prefix.
     */
    public static function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return preg_replace('/^(91|0)(?=\d{10}$)/', '', $digits) ?? $digits;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
        ];
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot(['relation', 'is_primary'])->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
