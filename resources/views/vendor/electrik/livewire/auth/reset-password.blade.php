@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <h1>{{ __('Reset password') }}</h1>
    <p class="s-96b813 mut">{{ __('Choose a new password for your account.') }}</p>
    <form wire:submit="resetPassword" class="s-f6ce4d col">
        <div class="fld">
            <label for="rp-email">{{ __('Email') }}</label>
            <input id="rp-email" class="inp @error('email') err @enderror" wire:model="email" type="email" autocomplete="username" required>
            @error('email')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
        </div>
        <div class="fld">
            <label for="rp-password">{{ __('New password') }}</label>
            <input id="rp-password" class="inp @error('password') err @enderror" wire:model="password" type="password" autocomplete="new-password" required>
            @error('password')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@else<span class="hint">{{ $en ? 'At least 15 characters' : 'მინიმუმ 15 სიმბოლო' }}</span>@enderror
        </div>
        <div class="fld">
            <label for="rp-password2">{{ __('Confirm password') }}</label>
            <input id="rp-password2" class="inp" wire:model="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button type="submit" class="s-e6a3b7 btn b-pri b-lg" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="resetPassword">{{ __('Reset password') }}</span>
            <span wire:loading wire:target="resetPassword">{{ __('Saving…') }}</span>
        </button>
        <div class="al al-er"><x-ga.icon name="err" :size="18" /><div><b>{{ $en ? 'Link expired?' : 'ბმულს ვადა გაუვიდა?' }}</b>{{ $en ? 'A reset link is valid for 30 minutes.' : 'აღდგენის ბმული 30 წუთი მოქმედებს.' }} <a href="{{ route('password.request') }}" class="s-794980">{{ $en ? 'Request a new one' : 'ახლის მოთხოვნა' }}</a></div></div>
    </form>
</div>
