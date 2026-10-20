<div>
@if ($comingSoonOn)
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border/80 bg-card p-5 shadow-xs">
        <div>
            <p class="font-medium">Coming Soon</p>
            <p class="text-sm text-muted-foreground">{{ __('Public pages show the holding page.') }}</p>
        </div>
        <form method="post" action="{{ route('admin.coming-soon.disable') }}">
            @csrf
            <x-slate::button type="submit">{{ __('Turn off Coming Soon') }}</x-slate::button>
        </form>
    </div>
@elseif (session('coming_soon_off') === true)
    <div class="mb-8">
        <x-slate::alert variant="success" title="{{ __('Coming Soon is off. The public site is open.') }}" />
    </div>
@endif

@include('electrik::livewire.dashboard')
</div>
