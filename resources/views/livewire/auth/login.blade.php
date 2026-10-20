<div class="mx-auto w-full max-w-md">
    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>{{ __('Sign in') }}</x-slate::card-title>
            <x-slate::card-description>{{ __('Welcome back. Enter your credentials to continue.') }}</x-slate::card-description>
        </x-slate::card-header>
        <x-slate::card-content>
            @if (session('status'))
                <x-slate::alert variant="success" :title="session('status')" class="mb-4" />
            @endif
            @if (session('error'))
                <x-slate::alert variant="destructive" :title="session('error')" class="mb-4" />
            @endif
            <x-slate::form wire:submit="login" class="space-y-4">
                @unless ($requiresTwoFactor)
                    <x-slate::input wire:model="email" type="email" label="{{ __('Email') }}" autocomplete="username" required :readonly="$inviteLockedEmail" />
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <x-slate::field-label for="login-password">{{ __('Password') }}</x-slate::field-label>
                            <a href="{{ route('password.request') }}" class="text-sm text-muted-foreground underline-offset-4 hover:underline">{{ __('Forgot password?') }}</a>
                        </div>
                        <x-slate::input id="login-password" wire:model="password" type="password" autocomplete="current-password" required />
                    </div>
                    <x-slate::checkbox wire:model="remember" label="{{ __('Remember me') }}" />
                @else
                    <x-slate::input wire:model="twoFactorCode" label="კოდი" inputmode="numeric" autocomplete="one-time-code" required />
                @endunless
                <x-slate::button type="submit" class="w-full" size="lg">{{ $requiresTwoFactor ? 'დადასტურება' : __('Sign in') }}</x-slate::button>
            </x-slate::form>
        </x-slate::card-content>
        @if (config('electrik.auth.registration', true) && ! $requiresTwoFactor)
            <x-slate::card-footer class="justify-center">
                <p class="text-sm text-muted-foreground">{{ __('No account?') }} <a href="{{ route('register') }}" class="font-medium text-foreground underline">{{ __('Create one') }}</a></p>
            </x-slate::card-footer>
        @endif
    </x-slate::card>
</div>
