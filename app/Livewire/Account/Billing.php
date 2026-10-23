<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Billing\BillingNotConfigured;
use App\Billing\BogClient;
use App\Billing\Entitlements;
use App\Billing\MockPay;
use App\Billing\Plans;
use App\Billing\V2\Catalog;
use App\Billing\V2\Checkout;
use App\Billing\V2\Flag;
use App\Billing\V2\Lifecycle;
use App\Billing\V2\Workspace;
use App\Models\User;
use Carapi\Platform\Connection;
use Carapi\Platform\ProjectRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url as QueryParam;
use Livewire\Component;

#[Layout('components.layouts.cabinet')]
#[Title('გადახდა')]
final class Billing extends Component
{
    public ?int $projectId = null;

    /** The plan picked on /pricing (?plan=starter), marked on the plan cards. */
    #[QueryParam(as: 'plan', history: false, keep: false)]
    public ?string $chosen = null;

    public function mount(): void
    {
        if (! in_array($this->chosen, ['starter', 'growth', 'business'], true)) {
            $this->chosen = null;
        }
        $first = $this->projects()[0]['id'] ?? null;
        $this->projectId = is_numeric($first) ? (int) $first : null;
    }

    public function chooseFree(): void
    {
        if (Flag::on()) {
            $this->scheduleDowngrade('free');

            return;
        }
        [$user, $projectId] = $this->ownedProject();
        Entitlements::chooseFree((int) $user->id, (int) $user->legacy_platform_user_id, $projectId);
        session()->flash('billing', app()->getLocale() === 'en' ? 'This project now has a monthly limit of 100 successful requests.' : 'ამ პროექტის თვიური ზღვარი ახლა 100 წარმატებული მოთხოვნაა.');
    }

    public function checkout(string $plan, string $interval): mixed
    {
        if (Flag::on()) {
            return $this->checkoutV2($plan, $interval);
        }
        if (! in_array($plan, [Plans::BASIC, Plans::PREMIUM], true) || ! in_array($interval, ['month', 'year'], true)) {
            abort(404);
        }
        $ids = array_map(static fn ($p): int => (int) $p['id'], $this->projects());
        if ($this->projectId === null || ! in_array((int) $this->projectId, $ids, true)) {
            $this->addError('checkout', 'no project');

            return null;
        }
        $mock = MockPay::allowedFor(auth()->user());
        if (! $mock && ! BogClient::configured()) {
            $this->addError('checkout', 'payments not configured');

            return null;
        }
        [$user, $projectId] = $this->ownedProject();
        $buyer = $this->buyer($user);
        $publicId = Entitlements::openOrder((int) $user->id, (int) $user->legacy_platform_user_id, $projectId, $plan, $interval, $buyer);
        if ($mock) {
            DB::table('billing_orders')->where('public_id', $publicId)->update(['bog_order_id' => MockPay::flag($publicId)]);

            return redirect()->to(URL::temporarySignedRoute('billing.mock.show', now()->addMinutes(30), ['order' => $publicId]));
        }
        try {
            $created = BogClient::createOrder(
                $publicId,
                Plans::formatTetri(Plans::tetri($plan, $interval)),
                Plans::code($plan, $interval),
                route('billing.bog.callback'),
                route('account.billing', ['paid' => 'return']),
                route('account.billing', ['paid' => 'fail']),
            );
        } catch (BillingNotConfigured) {
            Entitlements::failOrder($publicId);
            $this->addError('checkout', 'payments not configured');

            return null;
        }
        Entitlements::attachBogOrder($publicId, $created['id']);

        return redirect()->away($created['redirect']);
    }

    public function scheduleDowngrade(string $plan): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->legacy_platform_user_id !== null, 403);
        $ok = Lifecycle::schedule((int) $user->legacy_platform_user_id, $plan);
        if (! $ok) {
            $this->addError('checkout', app()->getLocale() === 'en' ? 'That change waits until the current term ends, and only a lower plan can be scheduled.' : 'შეცვლა მიმდინარე ვადის ბოლოს ხდება და მხოლოდ უფრო დაბალი გეგმა იგეგმება.');

            return;
        }
        session()->flash('billing', app()->getLocale() === 'en'
            ? 'The lower plan starts when the paid term ends. Nothing is charged now, and the term does not renew by itself.'
            : 'დაბალი გეგმა გადახდილი ვადის ბოლოს ჩაირთვება. ახლა თანხა არ ჩამოიჭრება და ვადა თავისით არ გრძელდება.');
    }

    public function buyExtraProject(): mixed
    {
        return $this->checkout('extra-project', 'month');
    }

    public function buyCredits(string $pack): mixed
    {
        return $this->checkout('credits:'.$pack, 'month');
    }

    private function checkoutV2(string $plan, string $interval): mixed
    {
        if (! in_array($interval, ['month', 'year'], true)) {
            abort(404);
        }
        $ids = array_map(static fn ($p): int => (int) $p['id'], $this->projects());
        if ($this->projectId === null || ! in_array((int) $this->projectId, $ids, true)) {
            $this->addError('checkout', 'no project');

            return null;
        }
        $mock = MockPay::allowedFor(auth()->user());
        if (! $mock && ! BogClient::configured()) {
            $this->addError('checkout', 'payments not configured');

            return null;
        }
        [$user, $projectId] = $this->ownedProject();
        try {
            $publicId = Checkout::open((int) $user->id, (int) $user->legacy_platform_user_id, $projectId, $plan, $interval, $this->buyer($user));
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'term') {
                $this->addError('checkout', 'term');

                return null;
            }
            abort(404);
        }
        if ($mock) {
            DB::table('billing_orders')->where('public_id', $publicId)->update(['bog_order_id' => MockPay::flag($publicId)]);

            return redirect()->to(URL::temporarySignedRoute('billing.mock.show', now()->addMinutes(30), ['order' => $publicId]));
        }
        try {
            $created = BogClient::createOrder(
                $publicId,
                Plans::formatTetri(Catalog::tetri($plan, $interval)),
                Catalog::code($plan, $interval),
                route('billing.bog.callback'),
                route('account.billing', ['paid' => 'return']),
                route('account.billing', ['paid' => 'fail']),
            );
        } catch (BillingNotConfigured) {
            Entitlements::failOrder($publicId);
            $this->addError('checkout', 'payments not configured');

            return null;
        }
        Entitlements::attachBogOrder($publicId, $created['id']);

        return redirect()->away($created['redirect']);
    }

    public function render()
    {
        $currentPlan = null;
        $projects = $this->projects();
        $ownedIds = array_map(static fn ($p): int => (int) $p['id'], $projects);
        // projectId is a client-writable Livewire property: read plans only for the caller's own projects.
        if ($this->projectId !== null && in_array((int) $this->projectId, $ownedIds, true)) {
            try {
                $currentPlan = Entitlements::currentPlan((int) $this->projectId);
            } catch (\Throwable) {
                $currentPlan = null;
            }
        }
        $orders = [];
        $user = auth()->user();
        if ($user instanceof User && Schema::hasTable('billing_orders')) {
            $orders = DB::table('billing_orders')->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get();
        }

        $v2 = null;
        if (Flag::on() && $user instanceof User && $user->legacy_platform_user_id !== null) {
            try {
                $v2 = Workspace::present((int) $user->legacy_platform_user_id);
            } catch (\Throwable) {
                $v2 = null;
            }
        }

        return view('livewire.account.billing', [
            'projects' => $projects,
            'configured' => BogClient::configured() || MockPay::allowedFor(auth()->user()),
            'mock' => MockPay::allowedFor(auth()->user()),
            'currentPlan' => $currentPlan,
            'orders' => $orders,
            'notice' => request()->query('paid'),
            'v2' => $v2,
        ]);
    }

    /** @return array{0: User, 1: int} */
    private function ownedProject(): array
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->legacy_platform_user_id !== null, 403);
        $projectId = (int) $this->projectId;
        $owned = false;
        foreach ($this->projects() as $project) {
            if ((int) $project['id'] === $projectId) {
                $owned = true;
                break;
            }
        }
        abort_unless($owned, 404);

        return [$user, $projectId];
    }

    /** @return list<array{id:int, name:string}> */
    private function projects(): array
    {
        $user = auth()->user();
        $demo = filter_var(env('CARAPI_CABINET_DEMO', false), FILTER_VALIDATE_BOOL);
        if (! $user instanceof User || $user->legacy_platform_user_id === null) {
            return $demo && $user instanceof User ? [['id' => 1, 'name' => 'დემო პროექტი']] : [];
        }
        // Same source as the cabinet (legacy platform PDO), not Laravel's default connection,
        // which may point at a different database/search_path than platform.projects.
        try {
            $rows = (new ProjectRepository(Connection::pdo()))->forUser((int) $user->legacy_platform_user_id);
        } catch (\Throwable $e) {
            Log::error('billing.projects failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            $rows = [];
        }
        $projects = [];
        foreach ($rows as $row) {
            $projects[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }
        if ($projects === [] && $demo) {
            // Local demo cabinet (never set on prod): same demo project the overview shows.
            $projects[] = ['id' => 1, 'name' => 'დემო პროექტი'];
        }

        return $projects;
    }

    /** @return array{kind:string, name:string, company_name:string, company_code:string, company_address:string, vat_payer:bool} */
    private function buyer(User $user): array
    {
        return [
            'kind' => (string) ($user->buyer_kind ?? 'individual'),
            'name' => (string) $user->name,
            'company_name' => (string) ($user->company_name ?? ''),
            'company_code' => (string) ($user->company_code ?? ''),
            'company_address' => (string) ($user->company_address ?? ''),
            'vat_payer' => (bool) ($user->vat_payer ?? false),
        ];
    }
}
