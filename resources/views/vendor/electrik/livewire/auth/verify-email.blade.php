@php $en = app()->getLocale() === 'en'; @endphp
<div>
    <div style="width:64px;height:64px;border-radius:16px;background:var(--soft);color:var(--ac);display:grid;place-items:center;margin-bottom:22px"><x-ga.icon name="mail" :size="30" /></div>
    <h1>{{ __('Verify your email') }}</h1>
    <p class="mut" style="margin:8px 0 24px">{{ __('We sent a verification link to your inbox. Open it to continue.') }} @if (auth()->user())<b style="color:var(--ink)">{{ auth()->user()->email }}</b>.@endif {{ $en ? 'The link is valid for 24 hours.' : 'ბმული მოქმედებს 24 საათი.' }}</p>
    <div class="al al-wa" style="margin-bottom:20px"><x-ga.icon name="lock" :size="18" /><div><b>{{ $en ? 'No secret key until you confirm' : 'დადასტურებამდე საიდუმლო გასაღები არ იქმნება' }}</b>{{ __('Didn’t get the email? Check spam, or request another link.') }}</div></div>
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
        <div class="al al-ok" style="margin-top:20px"><x-ga.icon name="ok" :size="18" /><div>{{ $status }}</div></div>
    @endif
</div>
