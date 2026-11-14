<?php

namespace App\Domain\Academic\Actions;

use App\Domain\Academic\Models\SchoolClass;
use App\Domain\Academic\Models\Section;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the sections of a class in one go: a number of classrooms, each
 * with the same capacity, named A, B, C and so on. Names already used in the
 * class are skipped.
 */
class CreateClassrooms
{
    /** @var list<string> */
    private const NAMES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    /**
     * @return Collection<int, Section>
     */
    public function handle(SchoolClass $schoolClass, int $count, int $capacity): Collection
    {
        return DB::transaction(function () use ($schoolClass, $count, $capacity): Collection {
            SchoolClass::query()->whereKey($schoolClass->id)->lockForUpdate()->first();

            $free = array_values(array_diff(self::NAMES, $schoolClass->sections()->pluck('name')->all()));

            if (count($free) < $count) {
                throw ValidationException::withMessages([
                    'count' => count($free) === 0
                        ? "{$schoolClass->name} has a section for every letter already."
                        : 'Only '.count($free)." section names are free in {$schoolClass->name}. Choose a smaller number.",
                ]);
            }

            return collect(array_slice($free, 0, $count))
                ->map(fn (string $name): Section => $schoolClass->sections()->create(['name' => $name, 'capacity' => $capacity]))
                ->values();
        });
    }
}
