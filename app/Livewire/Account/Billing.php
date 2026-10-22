<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Billing\BillingNotConfigured;
use App\Billing\BogClient;
use App\Billing\Entitlements;
use App\Billing\NbgRate;
use App\Billing\Plans;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use PDOException;

#[Layout('components.layouts.cabinet')]
#[Title('გადახდა')]
final class Billing extends Component
{
    public ?int $projectId = null;

    public function mount(): void
    {
        $first = $this->projects()[0]['id'] ?? null;
        $this->projectId = is_numeric($first) ? (int) $first : null;
    }

    public function chooseFree(): void
    {
        [$user, $projectId] = $this->ownedProject();
        Entitlements::chooseFree((int) $user->id, (int) $user->legacy_platform_user_id, $projectId);
        session()->flash('billing', app()->getLocale() === 'en' ? 'This project now has a monthly limit of 100 successful requests.' : 'ამ პროექტის თვიური ზღვარი ახლა 100 წარმატებული მოთხოვნაა.');
    }

    public function checkout(string $plan, string $interval): mixed
    {
        if (! in_array($plan, [Plans::BASIC, Plans::PREMIUM], true) || ! in_array($interval, ['month', 'year'], true)) {
            abort(404);
        }
        if (! BogClient::configured()) {
            $this->addError('checkout', 'payments not configured');

            return null;
        }
        $rate = NbgRate::latest();
        if ($rate === null) {
            $this->addError('checkout', 'the rate is unavailable');

            return null;
        }
        [$user, $projectId] = $this->ownedProject();
        $buyer = $this->buyer($user);
        $publicId = Entitlements::openOrder((int) $user->id, (int) $user->legacy_platform_user_id, $projectId, $plan, $interval, $rate, $buyer);
        try {
            $created = BogClient::createOrder(
                $publicId,
                $this->gel($plan, $interval, $rate),
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

    public function render()
    {
        $rate = null;
        try {
            $rate = NbgRate::latest();
        } catch (PDOException) {
            $rate = null;
        }
        $orders = [];
        $user = auth()->user();
        if ($user instanceof User && \Illuminate\Support\Facades\Schema::hasTable('billing_orders')) {
            $orders = DB::table('billing_orders')->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get();
        }

        return view('livewire.account.billing', [
            'projects' => $this->projects(),
            'configured' => BogClient::configured(),
            'rate' => $rate,
            'orders' => $orders,
            'notice' => request()->query('paid'),
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
        if (! $user instanceof User || $user->legacy_platform_user_id === null) {
            return [];
        }
        // Same source as the cabinet (legacy platform PDO), not Laravel's default connection,
        // which may point at a different database/search_path than platform.projects.
        try {
            $rows = (new \Carapi\Platform\ProjectRepository(\Carapi\Platform\Connection::pdo()))->forUser((int) $user->legacy_platform_user_id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('billing.projects failed', ['user' => $user->id, 'error' => $e->getMessage()]);

            return [];
        }
        $projects = [];
        foreach ($rows as $row) {
            $projects[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
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

    /** @param  array{code:string, rate:string, quantity:int, rated_on:string}  $rate */
    private function gel(string $plan, string $interval, array $rate): string
    {
        $tetri = Plans::gelTetri(Plans::usdCents($plan, $interval), $rate['rate'], $rate['quantity']);

        return Plans::formatTetri($tetri);
    }
}
