<?php

namespace App\Console\Commands;

use App\Enums\PlatformAuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The only way to make someone a platform admin (docs/ADMIN_DASHBOARD.md,
 * "Admin accounts"). It needs shell access to the server; no web page or API
 * endpoint can grant the flag. Every change is in the platform audit log.
 *
 *   php artisan dory:admin list
 *   php artisan dory:admin grant someone@example.com
 *   php artisan dory:admin revoke someone@example.com
 *   php artisan dory:admin create someone@example.com --name="Full Name"
 */
class ManagePlatformAdmins extends Command
{
    protected $signature = 'dory:admin
        {action : list, grant, revoke or create}
        {email? : The account email (grant, revoke, create)}
        {--name= : Name of a new account (create)}';

    protected $description = 'List, grant, revoke or create platform admin accounts';

    public function handle(PlatformAuditLogger $audit): int
    {
        $action = (string) $this->argument('action');
        if ($action === 'list') {
            return $this->listAdmins();
        }
        if (! in_array($action, ['grant', 'revoke', 'create'], true)) {
            $this->components->error('Unknown action. Use list, grant, revoke or create.');

            return self::INVALID;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('Give the account email.');

            return self::INVALID;
        }

        return match ($action) {
            'grant' => $this->grant($email, $audit),
            'revoke' => $this->revoke($email, $audit),
            default => $this->create($email, $audit),
        };
    }

    private function listAdmins(): int
    {
        $admins = User::query()->where('is_platform_admin', true)->orderBy('email')->get(['name', 'email', 'status', 'last_login_at']);
        if ($admins->isEmpty()) {
            $this->components->info('No platform admins yet. Create one with: php artisan dory:admin create <email> --name="..."');

            return self::SUCCESS;
        }
        $this->table(['Name', 'Email', 'Status', 'Last sign-in'], $admins->map(fn (User $user): array => [
            $user->name,
            $user->email,
            $user->status->value,
            $user->last_login_at?->toDateTimeString() ?? '-',
        ])->all());

        return self::SUCCESS;
    }

    private function grant(string $email, PlatformAuditLogger $audit): int
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->components->error('No account with this email. Create it with the "create" action.');

            return self::FAILURE;
        }
        if (! $user->isActive()) {
            $this->components->error('This account is not active.');

            return self::FAILURE;
        }
        if ($user->isPlatformAdmin()) {
            $this->components->info('This account is already a platform admin.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $audit): void {
            $user->forceFill(['is_platform_admin' => true])->saveOrFail();
            $audit->record(PlatformAuditAction::AdminGranted, null, $user, ['email' => $user->email], actorLabel: PlatformAuditLogger::CONSOLE);
        });
        $this->components->info($user->email.' is now a platform admin. They sign in at '.url('/admin').'.');

        return self::SUCCESS;
    }

    private function revoke(string $email, PlatformAuditLogger $audit): int
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null || ! $user->isPlatformAdmin()) {
            $this->components->error('No platform admin with this email.');

            return self::FAILURE;
        }

        $ended = DB::transaction(function () use ($user, $audit): int {
            $user->forceFill(['is_platform_admin' => false])->saveOrFail();
            // Their dashboard sessions end now, not at their next request.
            $ended = DB::table('admin_sessions')->where('user_id', $user->getKey())->delete();
            $audit->record(PlatformAuditAction::AdminRevoked, null, $user, ['email' => $user->email, 'sessions_ended' => $ended], actorLabel: PlatformAuditLogger::CONSOLE);

            return $ended;
        });
        $this->components->info($user->email.' is no longer a platform admin. Sessions ended: '.$ended.'.');

        return self::SUCCESS;
    }

    private function create(string $email, PlatformAuditLogger $audit): int
    {
        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('An account with this email exists. Use "grant" instead.');

            return self::FAILURE;
        }
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $password = (string) $this->secret('Password (at least 8 characters, upper and lower case and a number)');
        $repeat = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $repeat],
            ['name' => ['required', 'string', 'max:120'], 'password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $audit): void {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill([
                'status' => UserStatus::Active,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ])->saveOrFail();
            $audit->record(PlatformAuditAction::AdminCreated, null, $user, ['email' => $email], actorLabel: PlatformAuditLogger::CONSOLE);
        });
        $this->components->info('Platform admin '.$email.' created. Sign in at '.url('/admin').'.');

        return self::SUCCESS;
    }
}
