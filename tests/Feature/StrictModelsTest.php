<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\Section;
use Illuminate\Database\LazyLoadingViolationException;
use Tests\TestCase;

/**
 * Outside production, Eloquent is strict: an N+1 fails loudly in
 * development and tests instead of reaching production.
 */
class StrictModelsTest extends TestCase
{
    public function test_lazy_loading_a_relation_of_listed_models_throws(): void
    {
        Section::factory()->count(2)->create();
        $sections = Section::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $sections->first()->schoolClass;
    }
}
