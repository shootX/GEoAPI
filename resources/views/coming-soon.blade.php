<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Coming soon') }} — CarAPI</title>
    <x-electrik::brand-styles />
    @vite(['resources/css/app.css'])
    <style>
        body { margin: 0; min-height: 100vh; background: #f4faf7; color: #091413; }
        .top { display: flex; justify-content: flex-end; padding: 20px 24px; background: #091413; color: #e7f6ef; }
        main { box-sizing: border-box; max-width: 28rem; margin: 8vh auto 0; padding: 0 24px 64px; text-align: center; }
        img { width: 88px; height: 88px; border-radius: 22px; }
        h1 { margin: 24px 0 8px; font-size: 56px; line-height: 1.05; font-weight: 800; letter-spacing: -0.03em; color: #285A48; }
        .lead { margin: 0; color: #3d564c; font-size: 16px; line-height: 1.6; }
        form { margin-top: 28px; display: flex; flex-direction: column; gap: 10px; text-align: start; }
        label { font-size: 14px; font-weight: 600; }
        input { height: 48px; border-radius: 12px; border: 1px solid #d7e7df; padding: 0 14px; font: inherit; background: #fff; color: inherit; }
        input:focus-visible { outline: 2px solid #285A48; outline-offset: 2px; }
        button { height: 48px; border-radius: 12px; border: 1px solid #285A48; background: #285A48; color: #f4faf7; font: inherit; font-weight: 700; cursor: pointer; }
        .ok { margin-top: 28px; color: #285A48; font-weight: 600; }
        .err { margin: 0; color: #d1264a; font-size: 14px; }
        .login { display: inline-block; margin-top: 28px; color: #285A48; font-weight: 600; }
        @media (max-width: 760px) {
            h1 { font-size: 36px; }
            main { margin-top: 4vh; }
        }
    </style>
</head>
<body>
    <div class="top">
        @include('components.locale-switch')
    </div>
    <main>
        <img src="{{ asset('logo.png') }}" alt="CarAPI" width="88" height="88">
        <h1>{{ __('Coming soon') }}</h1>
        <p class="lead">{{ __('CarAPI opens soon. Leave your email and we will tell you when it does.') }}</p>
        @if (session('coming_soon_saved'))
            <p class="ok">{{ __('Thank you. We will tell you when it opens.') }}</p>
        @else
            <form method="post" action="{{ route('coming-soon.store') }}">
                @csrf
                <label for="coming-soon-email">{{ __('Email') }}</label>
                <input id="coming-soon-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="255" required value="{{ old('email') }}">
                @error('email')
                    <p class="err">{{ $message }}</p>
                @enderror
                <button type="submit">{{ __('Notify me') }}</button>
            </form>
        @endif
        <a class="login" href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </main>
</body>
</html>
