<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ComingSoonSignup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ComingSoonSignupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => __('Enter a valid email.'),
            'email.email' => __('Enter a valid email.'),
        ]);

        $email = mb_strtolower(trim($data['email']));
        ComingSoonSignup::query()->updateOrCreate(
            ['email' => $email],
            ['locale' => app()->getLocale()],
        );

        return redirect('/')->with('coming_soon_saved', true);
    }
}
