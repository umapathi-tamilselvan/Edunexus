<?php

namespace App\Support;

use App\Domain\Academic\Models\AcademicYear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The academic year a request works in: the year picked in the
 * X-Academic-Year header (see the SelectedAcademicYear middleware), else
 * the current year. Everything a user browses follows it.
 *
 * Years change about once a year, so the list of them is cached and
 * forgotten whenever one is saved. Scoped to one request.
 */
final class AcademicYearContext
{
    public const HEADER = 'X-Academic-Year';

    private const CACHE_KEY = 'academic_years:v2';

    private ?int $selectedId = null;

    /** @var Collection<int, AcademicYear>|null */
    private ?Collection $years = null;

    /**
     * Work in this year instead of the current one. Checked when the year
     * is first used, so requests that never use it pay nothing.
     */
    public function select(int $yearId): void
    {
        $this->selectedId = $yearId;
    }

    /**
     * The selected year, else the current year; null only for a client
     * without academic years.
     */
    public function year(): ?AcademicYear
    {
        if ($this->selectedId === null) {
            return $this->current();
        }

        return $this->years()->get($this->selectedId)
            ?? throw ValidationException::withMessages(['academic_year' => 'The selected academic year does not exist.']);
    }

    public function id(): ?int
    {
        return $this->year()?->id;
    }

    public function current(): ?AcademicYear
    {
        return $this->years()->first(fn (AcademicYear $year): bool => $year->is_current);
    }

    /**
     * Forget the cached years once the change that called this commits, so
     * no request can cache the old ones again in between.
     */
    public function forgetYears(): void
    {
        $this->years = null;

        DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return Collection<int, AcademicYear> by id
     */
    private function years(): Collection
    {
        // Raw attributes rather than models keep the cached value plain data.
        return $this->years ??= collect(Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => AcademicYear::query()
            ->get()
            ->map(fn (AcademicYear $year): array => $year->getAttributes())
            ->all()))
            ->map(fn (array $attributes): AcademicYear => (new AcademicYear)->newFromBuilder($attributes))
            ->keyBy('id');
    }
}
