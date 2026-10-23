<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Account\IdentitySync;
use App\Models\User;
use Geoapi\Platform\Passwords;
use Electrik\Models\Role;
use Electrik\Support\EnsuresTeamRoles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class IssueAdminToken extends Command
{
    protected $signature = 'admin:issue-token {email : The operator email}';

    protected $description = 'Ensure an admin user and print a new Sanctum personal access token';

    public function handle(EnsuresTeamRoles $roles): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('email is invalid');

            return self::FAILURE;
        }

        try {
            $legacyId = $this->ensurePlatformUser($email);
            if ($legacyId !== null) {
                IdentitySync::ensure($legacyId);
                DB::table('platform.users')->where('id', $legacyId)->update([
                    'role' => 'admin',
                    'status' => 'active',
                    'email_verified_at' => DB::raw('coalesce(email_verified_at, now())'),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            $this->error('platform user was not updated');
            report($e);

            return self::FAILURE;
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => 'drom.amin',
                'email' => $email,
                'password' => bin2hex(random_bytes(24)),
                'email_verified_at' => now(),
            ]);
        }

        $user->forceFill([
            'name' => 'drom.amin',
            'email_verified_at' => $user->email_verified_at ?? now(),
            'suspended_at' => null,
        ])->save();

        $team = $user->ownedTeams()->first() ?? $user->teams()->first();
        if ($team === null && method_exists($user, 'createOwnedTeam')) {
            $team = $user->createOwnedTeam(['name' => 'drom.amin'], true);
        }
        if ($team === null) {
            $this->error('admin team was not created');

            return self::FAILURE;
        }

        $roles->ensureForTeam($team);
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        Role::findOrCreateForTeam('admin', 'web', (int) $team->getKey());
        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->ensureOperator($email);

        $user->tokens()->where('name', 'drom.amin')->delete();
        $token = $user->createToken('drom.amin', ['*']);
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }

    private function ensurePlatformUser(string $email): ?int
    {
        $ready = DB::selectOne("select to_regclass('platform.users') as name");
        if ($ready === null || $ready->name === null) {
            return null;
        }

        $id = DB::table('platform.users')->whereRaw('lower(email) = ?', [$email])->value('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $hash = Passwords::hash(bin2hex(random_bytes(24)));
        $columns = [
            'first_name' => 'drom.amin',
            'last_name' => 'admin',
            'email' => $email,
            'password_hash' => $hash,
            'status' => 'active',
            'role' => 'admin',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $hasTerms = DB::selectOne("select 1 as ok from information_schema.columns where table_schema = 'platform' and table_name = 'users' and column_name = 'terms_version'");
        if ($hasTerms !== null) {
            $columns['terms_version'] = '2026-09-26';
            $columns['terms_accepted_at'] = now();
        }

        return (int) DB::table('platform.users')->insertGetId($columns);
    }

    private function ensureOperator(string $email): void
    {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return;
        }
        if (preg_match('/^ELECTRIK_OPERATOR_EMAILS=(.*)$/m', $contents, $matches) === 1) {
            $current = trim($matches[1], " \t\"'");
            $emails = array_values(array_filter(array_map('trim', explode(',', $current))));
            $lower = array_map(static fn (string $item): string => mb_strtolower($item), $emails);
            if (! in_array($email, $lower, true)) {
                $emails[] = $email;
            }
            $line = 'ELECTRIK_OPERATOR_EMAILS='.implode(',', $emails);
            $updated = preg_replace('/^ELECTRIK_OPERATOR_EMAILS=.*$/m', $line, $contents, 1);
        } else {
            $updated = rtrim($contents).PHP_EOL.'ELECTRIK_OPERATOR_EMAILS='.$email.PHP_EOL;
        }
        if (is_string($updated)) {
            file_put_contents($path, $updated);
        }
    }
}
