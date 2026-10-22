@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <a class="sm row lnk" href="{{ route('login') }}" style="gap:6px;margin-bottom:24px"><x-ga.icon name="left" :size="14" />{{ __('Back to sign in') }}</a>
    <h1>{{ __('Forgot password') }}</h1>
    <p class="mut" style="margin:6px 0 24px">{{ __('Enter your email and we will send a reset link.') }}</p>
    <form wire:submit="sendResetLink" class="col" style="gap:16px">
        <div class="fld">
            <label for="fp-email">{{ __('Email') }}</label>
            <input id="fp-email" class="inp @error('email') err @enderror" wire:model="email" type="email" autocomplete="username" required>
            @error('email')<span class="emsg"><x-ga.icon name="err" :size="14" />{{ $message }}</span>@enderror
        </div>
        <button type="submit" class="btn b-pri b-lg" style="justify-content:center;width:100%" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sendResetLink">{{ __('Email reset link') }}</span>
            <span wire:loading wire:target="sendResetLink">{{ __('Sending…') }}</span>
        </button>
        @if ($status)
            <div class="al al-in"><x-ga.icon name="mail" :size="18" /><div><b>{{ $status }}</b>{{ $en ? 'The link is valid for 30 minutes. Check the Spam folder too.' : 'ბმული მოქმედებს 30 წუთი. შეამოწმეთ Spam საქაღალდეც.' }}</div></div>
        @endif
    </form>
</div>
