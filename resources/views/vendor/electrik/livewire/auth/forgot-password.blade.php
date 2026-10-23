@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <a class="s-9e6ac2 sm row lnk" href="{{ route('login') }}"><x-ga.icon name="left" :size="14" />{{ __('Back to sign in') }}</a>
    <h1>{{ __('Forgot password') }}</h1>
    <p class="s-96b813 mut">{{ __('Enter your email and we will send a reset link.') }}</p>
    <form wire:submit="sendResetLink" class="s-f6ce4d col">
        <div class="fld">
            <label for="fp-email">{{ __('Email') }}</label>
            <input id="fp-email" class="inp @error('email') err @enderror" @error('email') aria-invalid="true" aria-describedby="fp-email-error" @enderror wire:model="email" type="email" autocomplete="username" required>
            @error('email')<span class="emsg" id="fp-email-error" role="alert"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
        </div>
        <button type="submit" class="s-e6a3b7 btn b-pri b-lg" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('Email reset link') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('Sending…') }}</span>
        </button>
        @if ($status)
            <div class="al al-in"><x-ga.icon name="mail" :size="18" /><div><b>{{ $status }}</b>{{ $en ? 'The link is valid for 30 minutes. Check the Spam folder too.' : 'ბმული მოქმედებს 30 წუთი. შეამოწმეთ Spam საქაღალდეც.' }}</div></div>
        @endif
    </form>
</div>
