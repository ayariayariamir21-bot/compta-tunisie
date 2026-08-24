<?php

namespace App\Providers;

use App\Enums\AuditAction;
use App\Models\Backup;
use App\Models\User;
use App\Policies\BackupPolicy;
use App\Services\Security\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Auth\Events\Logout as AuthLogout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerBackupPolicy();
        $this->configureRateLimiting();
        $this->listenForAuditEvents();
    }

    /**
     * Backups are filesystem resources without an Eloquent model, so their
     * policy cannot be auto-discovered and is registered explicitly.
     */
    protected function registerBackupPolicy(): void
    {
        Gate::policy(Backup::class, BackupPolicy::class);
    }

    /**
     * Backup operations shell out to expensive pg_dump/pg_restore processes,
     * so creation, validation and restoration are throttled per account.
     * These routes sit behind the auth middleware, so a user is always set.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('backups', function (Request $request) {
            $user = $request->user();

            $identity = $user instanceof User ? 'user-'.$user->id : 'guest';

            return Limit::perMinute(6)->by($identity.'|'.$request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Record authentication and two-factor events in the audit journal.
     */
    protected function listenForAuditEvents(): void
    {
        Event::listen(function (AuthLogin $event): void {
            if ($event->user instanceof User) {
                app(AuditLogService::class)->log(AuditAction::Login, description: 'Connexion réussie.', user: $event->user);
            }
        });

        Event::listen(function (AuthFailed $event): void {
            $credentials = $event->credentials;

            app(AuditLogService::class)->logAction(
                AuditAction::LoginFailed,
                'Échec de connexion.',
                metadata: ['attempted_email' => is_string($credentials['email'] ?? null) ? $credentials['email'] : null],
            );
        });

        Event::listen(function (AuthLogout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogService::class)->log(AuditAction::Logout, description: 'Déconnexion.', user: $event->user);
            }
        });

        Event::listen(function (TwoFactorAuthenticationEnabled $event): void {
            app(AuditLogService::class)->log(AuditAction::TwoFactorEnabled, description: 'Authentification à deux facteurs activée.', user: $event->user);
        });

        Event::listen(function (TwoFactorAuthenticationDisabled $event): void {
            app(AuditLogService::class)->log(AuditAction::TwoFactorDisabled, description: 'Authentification à deux facteurs désactivée.', user: $event->user);
        });
    }
}
