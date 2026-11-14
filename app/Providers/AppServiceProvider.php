<?php

namespace App\Providers;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Policies\AttendancePolicy;
use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Communication\Notifiers\LogGuardianNotifier;
use App\Domain\Communication\Notifiers\WhatsAppGuardianNotifier;
use App\Domain\Examination\Models\Exam;
use App\Domain\Examination\Policies\ExamPolicy;
use App\Domain\Identity\Listeners\RecordTokenUse;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Student\Models\Student;
use App\Domain\Student\Policies\StudentPolicy;
use App\Support\AcademicYearContext;
use App\Support\OrganizationContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Events\TokenAuthenticated;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class);
        $this->app->scoped(AcademicYearContext::class);

        $this->app->bind(GuardianNotifier::class, fn (Application $app): GuardianNotifier => match ($app['config']->get('edunexus.guardian_notifier')) {
            'whatsapp' => new WhatsAppGuardianNotifier($app['config']->get('services.whatsapp')),
            default => new LogGuardianNotifier,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production, lazy loading (N+1 queries) and reading or
        // filling attributes that don't exist throw instead of passing silently.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Policies live in their domain modules, outside the discovery path.
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Exam::class, ExamPolicy::class);
        Gate::policy(AttendanceRecord::class, AttendancePolicy::class);

        Event::listen(TokenAuthenticated::class, RecordTokenUse::class);

        $this->configureRateLimiting();
    }

    /**
     * A school's users usually share one public IP, so per-IP limits are
     * set for a whole school; per-account limits do the real work.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => $request->user()
            ? Limit::perMinute(120)->by('user:'.$request->user()->getAuthIdentifier())
            : Limit::perMinute(600)->by('ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(300)->by((string) $request->ip()),
        ]);
    }
}
