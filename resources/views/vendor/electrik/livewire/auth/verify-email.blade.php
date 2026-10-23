@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <div class="s-34bcf1"><x-ga.icon name="mail" :size="30" /></div>
    <h1>{{ __('Verify your email') }}</h1>
    <p class="s-3f7179 mut">{{ __('We sent a verification link to your inbox. Open it to continue.') }} @if (auth()->user())<b class="s-789157">{{ auth()->user()->email }}</b>.@endif {{ $en ? 'The link is valid for 24 hours.' : 'ბმული მოქმედებს 24 საათი.' }}</p>
    <div class="s-49f14f al al-wa"><x-ga.icon name="lock" :size="18" /><div><b>{{ $en ? 'No secret key until you confirm' : 'დადასტურებამდე საიდუმლო გასაღები არ იქმნება' }}</b>{{ __('Didn’t get the email? Check spam, or request another link.') }}</div></div>
    <div class="row">
        <button type="button" class="btn b-pri b-lg" wire:click="resend" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="resend">{{ __('Resend verification email') }}</span>
            <span wire:loading wire:target="resend">{{ __('Sending…') }}</span>
        </button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn b-gh b-lg">{{ __('Sign out') }}</button>
        </form>
    </div>
    @if ($status)
        <div class="s-a26bda al al-ok"><x-ga.icon name="ok" :size="18" /><div>{{ $status }}</div></div>
    @endif
</div>
