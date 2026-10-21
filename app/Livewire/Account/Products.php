<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Access\ProductAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('electrik::components.layouts.app')]
#[Title('API პროდუქტები')]
final class Products extends Component
{
    public function activate(string $slug): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->legacy_platform_user_id !== null, 403);
        $product = DB::table('api_products')->where('slug', $slug)->first();
        abort_unless($product !== null && (string) $product->status === 'published', 404);
        $plan = DB::table('api_plans')
            ->where('api_product_id', $product->id)
            ->where('code', 'cars-included')
            ->where('status', 'published')
            ->first();
        abort_unless($plan !== null, 404);

        $existing = DB::table('api_entitlements')
            ->where('legacy_user_id', $user->legacy_platform_user_id)
            ->where('api_product_id', $product->id)
            ->first();
        $teamId = $user->currentTeam->id ?? null;

        if ($existing === null) {
            DB::table('api_entitlements')->insert([
                'team_id' => $teamId,
                'legacy_user_id' => $user->legacy_platform_user_id,
                'api_product_id' => $product->id,
                'api_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('api_entitlements')->where('id', $existing->id)->update([
            'team_id' => $teamId ?? $existing->team_id,
            'api_plan_id' => $plan->id,
            'status' => 'active',
            'ends_at' => null,
            'cancelled_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function cancel(string $slug): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->legacy_platform_user_id !== null, 403);
        $product = DB::table('api_products')->where('slug', $slug)->where('status', 'published')->first();
        abort_unless($product !== null, 404);
        DB::table('api_entitlements')
            ->where('legacy_user_id', $user->legacy_platform_user_id)
            ->where('api_product_id', $product->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ends_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function render()
    {
        $user = auth()->user();
        $legacyId = $user?->legacy_platform_user_id;
        $products = DB::table('api_products')->orderBy('id')->get();
        $plans = DB::table('api_plans')->where('code', 'cars-included')->get()->groupBy('api_product_id');
        $entitlements = $legacyId === null
            ? collect()
            : DB::table('api_entitlements')->where('legacy_user_id', $legacyId)->get()->keyBy('api_product_id');

        return view('livewire.account.products', [
            'products' => $products,
            'plans' => $plans,
            'entitlements' => $entitlements,
            'abilities' => $user instanceof User ? ProductAccess::tokenAbilitiesFor($user) : [],
        ]);
    }
}
