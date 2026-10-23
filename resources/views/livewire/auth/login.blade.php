@php $en = app()->getLocale() === 'en'; @endphp
<div>
    @if ($requiresTwoFactor)
        <div class="s-34bcf1"><x-ga.icon name="lock" :size="30" /></div>
        <h1>{{ $en ? 'Two-step sign in' : 'ორსაფეხურიანი შესვლა' }}</h1>
        <p class="s-387404 mut">{{ $en ? 'Enter the 6-digit code from your authenticator app.' : 'შეიყვანეთ 6-ნიშნა კოდი ავთენტიფიკატორის აპიდან.' }}</p>
    @else
        <h1>{{ __('Sign in') }}</h1>
        <p class="s-0596e9 mut">{{ $en ? 'Continue to your cabinet' : 'გააგრძელეთ თქვენს კაბინეტში' }}</p>
    @endif
    @if (session('status'))
        <div class="s-905d8b al al-ok"><x-ga.icon name="ok" :size="18" /><div>{{ session('status') }}</div></div>
    @endif
    @if (session('error'))
        <div class="s-905d8b al al-er"><x-ga.icon name="err" :size="18" /><div>{{ session('error') }}</div></div>
    @endif
    <form wire:submit="login" class="s-f6ce4d col">
        @unless ($requiresTwoFactor)
            <div class="fld">
                <label for="login-email">{{ __('Email') }}</label>
                <input id="login-email" class="inp @error('email') err @enderror" @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror wire:model="email" type="email" autocomplete="username" required @if ($inviteLockedEmail) readonly @endif>
                @error('email')<span class="emsg" id="login-email-error" role="alert"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
            <div class="fld">
                <div class="s-a562f4 row"><label for="login-password">{{ __('Password') }}</label><a href="{{ route('password.request') }}" class="sm lnk">{{ __('Forgot password?') }}</a></div>
                <input id="login-password" class="inp @error('password') err @enderror" @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror wire:model="password" type="password" autocomplete="current-password" required>
                @error('password')<span class="emsg" id="login-password-error" role="alert"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
            <label class="chk-l"><input type="checkbox" wire:model="remember"> {{ __('Remember me') }}</label>
        @else
            <div class="fld">
                <label for="login-code">კოდი</label>
                <input id="login-code" class="s-4c3751 inp mono @error('twoFactorCode') err @enderror" @error('twoFactorCode') aria-invalid="true" aria-describedby="login-code-error" @enderror wire:model="twoFactorCode" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required>
                @error('twoFactorCode')<span class="emsg" id="login-code-error" role="alert"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
            </div>
        @endunless
        <button type="submit" class="s-e6a3b7 btn b-pri b-lg">{{ $requiresTwoFactor ? 'დადასტურება' : __('Sign in') }}</button>
    </form>
    @if (config('electrik.auth.registration', true) && ! $requiresTwoFactor)
        <p class="s-4e97dd sm mut">{{ __('No account?') }} <a href="{{ route('register') }}" class="lnk">{{ __('Create one') }}</a></p>
    @endif
</div>
