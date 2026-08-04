<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Single-resource JSON responses stay flat (no "data" wrapper);
        // paginated collections still get one from the paginator itself.
        JsonResource::withoutWrapping();

        // A debug-mode production deploy leaks stack traces, the environment and
        // DB credentials on any unhandled exception. Fail loudly instead.
        if (app()->isProduction() && config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }

        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
        $this->configureAuditLogging();
    }

    /**
     * The password policy for every endpoint that sets one.
     *
     * Password::defaults() was never configured, so it resolved to its bare
     * default of "at least 8 characters" - which accepted `password`, `12345678`
     * and `qwertyui`. Configuring it here covers all six call sites at once
     * (register, reset, change, admin create, admin update, guest claim) rather
     * than restating rules at each one.
     *
     * Length-first, no composition rules: see config/security.php for why.
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(config('security.password.min_length'))
                ->max(config('security.password.max_length'));

            return config('security.password.check_breaches')
                ? $rule->uncompromised()
                : $rule;
        });
    }

    /**
     * Failed sign-ins and lockouts were recorded nowhere, so a brute-force run
     * against the guest-login endpoint left no trace at all.
     */
    private function configureAuditLogging(): void
    {
        Event::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);
        Event::listen(Lockout::class, [LogAuthenticationEvents::class, 'handleLockout']);
        Event::listen(PasswordReset::class, [LogAuthenticationEvents::class, 'handlePasswordReset']);
    }

    /**
     * Named limiters. Nothing in this application was rate limited before: 60
     * consecutive API reads, 25 registrations and unlimited password guesses
     * against the guest-login endpoint all succeeded.
     */
    private function configureRateLimiting(): void
    {
        // Per-identity so one hostile client can't exhaust the budget for a
        // whole NAT'd network of legitimate visitors.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Credential-testing surfaces: login, register, password reset/confirm,
        // and the guest-cart merge. Keyed on IP because the whole point is to
        // limit an attacker who is varying the email.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));

        // State-changing booking traffic, including the routes that provision a
        // guest account for an anonymous caller.
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
