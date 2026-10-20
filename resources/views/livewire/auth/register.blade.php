<div>
    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>ანგარიშის შექმნა</x-slate::card-title>
            <x-slate::card-description>სახელი, ელფოსტა და პაროლი. პაროლი მინიმუმ 15 სიმბოლოა.</x-slate::card-description>
        </x-slate::card-header>
        <x-slate::card-content>
            <x-slate::form wire:submit="register" class="space-y-4">
                <x-slate::input wire:model="name" type="text" label="სახელი და გვარი" autocomplete="name" required />
                <x-slate::input wire:model="email" type="email" label="ელფოსტა" autocomplete="username" required :readonly="$inviteLockedEmail" />
                <x-slate::input wire:model="password" type="password" label="პაროლი" autocomplete="new-password" required />
                <p class="text-sm text-muted-foreground">{{ __('Password must be at least 15 characters.') }}</p>
                <x-slate::input wire:model="password_confirmation" type="password" label="გაიმეორეთ პაროლი" autocomplete="new-password" required />
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="accept_terms" class="mt-1" value="1">
                    <span>ვეთანხმები <a href="{{ url('/terms') }}" class="underline">გამოყენების წესებს</a>.</span>
                </label>
                @error('accept_terms')
                    <p class="text-sm text-destructive">წესებზე თანხმობა აუცილებელია.</p>
                @enderror
                <x-slate::button type="submit" class="w-full" size="lg">ანგარიშის შექმნა</x-slate::button>
            </x-slate::form>
        </x-slate::card-content>
        <x-slate::card-footer class="justify-center">
            <p class="text-sm text-muted-foreground">უკვე გაქვთ ანგარიში? <a href="{{ route('login') }}" class="font-medium text-foreground underline">შესვლა</a></p>
        </x-slate::card-footer>
    </x-slate::card>
</div>
