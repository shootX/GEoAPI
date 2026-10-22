@php $en = app()->getLocale() === 'en'; @endphp
<div>
    @if ($requiresTwoFactor)
        <div style="width:64px;height:64px;border-radius:16px;background:var(--soft);color:var(--ac);display:grid;place-items:center;margin-bottom:22px"><x-ga.icon name="lock" :size="30" /></div>
        <h1>{{ $en ? 'Two-step sign in' : 'ორსაფეხურიანი შესვლა' }}</h1>
        <p class="mut" style="margin:8px 0 28px">{{ $en ? 'Enter the 6-digit code from your authenticator app.' : 'შეიყვანეთ 6-ნიშნა კოდი ავთენტიფიკატორის აპიდან.' }}</p>
    @else
        <h1>{{ __('Sign in') }}</h1>
        <p class="mut" style="margin:6px 0 28px">{{ $en ? 'Continue to your cabinet' : 'გააგრძელეთ თქვენს კაბინეტში' }}</p>
    @endif
    @if (session('status'))
        <div class="al al-ok" style="margin-bottom:18px"><x-ga.icon name="ok" :size="18" /><div>{{ session('status') }}</div></div>
    @endif
    @if (session('error'))
        <div class="al al-er" style="margin-bottom:18px"><x-ga.icon name="err" :size="18" /><div>{{ session('error') }}</div></div>
    @endif
    <form wire:submit="login" class="col" style="gap:16px">
        @unless ($requiresTwoFactor)
            <div class="fld">
                <label for="login-email">{{ __('Email') }}</label>
                <input id="login-email" class="inp @error('email') err @enderror" wire:model="email" type="email" autocomplete="username" required @if ($inviteLockedEmail) readonly @endif>
                @error('email')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
            <div class="fld">
                <div class="row" style="justify-content:space-between"><label for="login-password">{{ __('Password') }}</label><a href="{{ route('password.request') }}" class="sm lnk">{{ __('Forgot password?') }}</a></div>
                <input id="login-password" class="inp @error('password') err @enderror" wire:model="password" type="password" autocomplete="current-password" required>
                @error('password')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
            <label class="chk-l"><input type="checkbox" wire:model="remember"> {{ __('Remember me') }}</label>
        @else
            <div class="fld">
                <label for="login-code">კოდი</label>
                <input id="login-code" class="inp mono @error('twoFactorCode') err @enderror" wire:model="twoFactorCode" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required style="font-size:22px;letter-spacing:.4em;text-align:center;height:60px">
                @error('twoFactorCode')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
        @endunless
        <button type="submit" class="btn b-pri b-lg" style="justify-content:center;width:100%">{{ $requiresTwoFactor ? 'დადასტურება' : __('Sign in') }}</button>
    </form>
    @if (config('electrik.auth.registration', true) && ! $requiresTwoFactor)
        <p class="sm mut" style="text-align:center;margin-top:24px">{{ __('No account?') }} <a href="{{ route('register') }}" class="lnk">{{ __('Create one') }}</a></p>
    @endif
</div>
