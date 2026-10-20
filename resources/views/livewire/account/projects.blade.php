<div class="mx-auto max-w-3xl space-y-6 p-6">
    <div class="flex items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">პროექტები</h1>
            <p class="mt-2 text-sm text-muted-foreground">თითო პროექტს საკუთარი გასაღები და ლიმიტი აქვს.</p>
        </div>
        <x-slate::button as="a" href="{{ url('/account/products') }}" variant="outline">API პროდუქტები</x-slate::button>
    </div>

    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>ახალი პროექტი</x-slate::card-title>
        </x-slate::card-header>
        <x-slate::card-content>
            <form wire:submit="create" class="space-y-4">
                <x-slate::input wire:model="name" label="სახელი" required />
                <x-slate::input wire:model="website_url" label="საიტი" placeholder="https://example.com" />
                <x-slate::button type="submit">შექმნა</x-slate::button>
            </form>
        </x-slate::card-content>
    </x-slate::card>

    @forelse ($projects as $project)
        <x-slate::card>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="font-medium">{{ $project['name'] }}</h2>
                    <p class="text-sm text-muted-foreground">{{ $project['website_url'] ?: 'საიტი არ არის მითითებული' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs">{{ $project['status'] === 'active' ? 'აქტიური' : 'გამორთული' }}</span>
                    <x-slate::button as="a" href="{{ url('/account/projects/'.$project['id']) }}" variant="outline">გახსნა</x-slate::button>
                </div>
            </div>
        </x-slate::card>
    @empty
        <p class="text-sm text-muted-foreground">პროექტი ჯერ არ არის.</p>
    @endforelse
</div>
