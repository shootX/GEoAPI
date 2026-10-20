<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ComingSoonGate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DisableComingSoonController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($this->allowed($request), 404);

        $row = Setting::query()->find('coming_soon');
        if ($row instanceof Setting) {
            $row->value = false;
            $row->save();
        }

        return redirect()->back(fallback: url('/admin'))->with('coming_soon_off', true);
    }

    private function allowed(Request $request): bool
    {
        if (ComingSoonGate::allows($request)) {
            return true;
        }

        $user = $request->user();
        if (! $user instanceof User || $user->legacy_platform_user_id === null) {
            return false;
        }

        try {
            return DB::table('platform.users')->where('id', $user->legacy_platform_user_id)->value('role') === 'admin';
        } catch (Throwable) {
            return false;
        }
    }
}
