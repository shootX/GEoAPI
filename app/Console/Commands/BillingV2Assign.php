<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\V2\Catalog;
use App\Billing\V2\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BillingV2Assign extends Command
{
    protected $signature = 'billing:v2-assign {legacyUserId} {plan} {--quota=} {--projects=} {--rpm=} {--months=12}';

    protected $description = 'Set an admin-managed workspace plan. Enterprise is not sold in checkout.';

    public function handle(): int
    {
        $legacyUserId = (int) $this->argument('legacyUserId');
        $plan = (string) $this->argument('plan');
        if (! in_array($plan, ['enterprise', 'free', 'starter', 'growth', 'business'], true)) {
            $this->error('Unknown plan.');

            return self::FAILURE;
        }
        $owner = Workspace::ownerId($legacyUserId);
        if ($owner === null) {
            $this->error('No Laravel user is linked to that platform user.');

            return self::FAILURE;
        }
        if ($plan === 'enterprise') {
            if ($this->option('quota') === null || $this->option('projects') === null || $this->option('rpm') === null) {
                $this->error('Enterprise needs --quota, --projects, and --rpm.');

                return self::FAILURE;
            }
            $quota = (int) $this->option('quota');
            $projects = (int) $this->option('projects');
            $rpm = (int) $this->option('rpm');
        } else {
            $spec = Catalog::plans()[$plan];
            $quota = (int) $spec['quota'];
            $projects = (int) $spec['projects'];
            $rpm = Catalog::rpm($plan);
        }
        if ($quota < 1 || $projects < 1 || $rpm < 1 || $rpm > 1000) {
            $this->error('Quota, projects, and rpm must be positive. rpm stops at 1000.');

            return self::FAILURE;
        }
        $now = Workspace::stamp();
        $end = CarbonImmutable::parse($now, 'UTC')->addMonths(max(1, (int) $this->option('months')))->utc()->format('Y-m-d H:i:s');
        $values = [
            'plan_code' => $plan,
            'interval' => 'month',
            'monthly_quota' => $quota,
            'rpm' => $rpm,
            'included_projects' => $projects,
            'period_start' => $now,
            'period_end' => Workspace::plusMonth($now),
            'term_end' => $end,
            'shared_quota' => true,
            'legacy_preserved' => false,
            'unlimited' => false,
            'source' => 'admin',
        ];
        $existing = Workspace::find($legacyUserId);
        if ($existing === null) {
            Workspace::create($owner, $legacyUserId, 'admin', $values);
        } else {
            DB::table('billing_workspace_subscriptions')->where('workspace_id', $existing->id)->update([
                'plan_code' => $plan,
                'monthly_quota' => $quota,
                'rpm' => $rpm,
                'included_projects' => $projects,
                'period_start' => $now,
                'period_end' => $values['period_end'],
                'term_end' => $end,
                'shared_quota' => true,
                'legacy_preserved' => false,
                'unlimited' => false,
                'pending_plan_code' => null,
                'pending_interval' => null,
                'updated_at' => $now,
            ]);
            Workspace::entitle((int) $existing->id, $plan, 'admin', $now, $end);
        }
        $this->info('Assigned '.$plan.' at '.$rpm.' rpm.');

        return self::SUCCESS;
    }
}
