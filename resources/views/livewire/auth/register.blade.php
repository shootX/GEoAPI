<div class="mx-auto w-full max-w-md">
    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>{{ __('Create an account') }}</x-slate::card-title>
            <x-slate::card-description>{{ __('Name, email, and a password of at least 15 characters.') }}</x-slate::card-description>
        </x-slate::card-header>
        <x-slate::card-content>
            <x-slate::form wire:submit="register" class="space-y-4">
                <x-slate::input wire:model="name" type="text" label="{{ __('Name and surname') }}" autocomplete="name" required />
                <x-slate::input wire:model="email" type="email" label="{{ __('Email') }}" autocomplete="username" required :readonly="$inviteLockedEmail" />
                <x-slate::input wire:model="password" type="password" label="{{ __('Password') }}" autocomplete="new-password" required />
                <x-slate::input wire:model="password_confirmation" type="password" label="{{ __('Repeat the password') }}" autocomplete="new-password" required />
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="accept_terms" class="mt-1" value="1">
                    <span>{{ __('I agree to the') }} <a href="{{ url('/terms') }}" class="underline">{{ __('terms of use') }}</a>.</span>
                </label>
                @error('accept_terms')
                    <p class="text-sm text-destructive">{{ __('You must accept the terms.') }}</p>
                @enderror
                <x-slate::button type="submit" class="w-full" size="lg">{{ __('Create an account') }}</x-slate::button>
            </x-slate::form>
        </x-slate::card-content>
        <x-slate::card-footer class="justify-center">
            <p class="text-sm text-muted-foreground">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-foreground underline">{{ __('Sign in') }}</a></p>
        </x-slate::card-footer>
    </x-slate::card>
</div>
