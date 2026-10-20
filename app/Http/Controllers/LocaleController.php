<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class LocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['ka', 'en'], true), 404);

        $request->session()->put('locale', $locale);

        $user = $request->user();
        if ($user !== null && Schema::hasColumn($user->getTable(), 'locale')) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back(fallback: url('/'));
    }
}
