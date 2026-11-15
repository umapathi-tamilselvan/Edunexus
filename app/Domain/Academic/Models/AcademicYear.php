<?php

namespace App\Domain\Academic\Models;

use App\Domain\Examination\Models\Exam;
use App\Support\AcademicYearContext;
use Carbon\CarbonInterface;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An academic year. Never overwritten; exactly one is current at a time.
 *
 * @property int $id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_current
 * @property bool $admissions_open
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'admissions_open'])]
#[UseFactory(AcademicYearFactory::class)]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_current' => false,
        'admissions_open' => false,
    ];

    protected static function booted(): void
    {
        // AcademicYearContext caches every year.
        static::saved(fn () => app(AcademicYearContext::class)->forgetYears());
        static::deleted(fn () => app(AcademicYearContext::class)->forgetYears());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'admissions_open' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class)->orderByRaw('starts_on IS NULL')->orderBy('starts_on')->orderBy('name');
    }

    /**
     * @return HasMany<Holiday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class)->orderBy('date');
    }

    /**
     * Whether the date falls within this year.
     */
    public function includes(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }

    /**
     * @return HasMany<Period, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(Period::class)->orderBy('sort_order')->orderBy('starts_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
