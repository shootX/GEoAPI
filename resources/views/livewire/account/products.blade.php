<div class="mx-auto max-w-3xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">API პროდუქტები</h1>
        <p class="mt-2 text-sm text-muted-foreground">თითოეული პროდუქტი ცალკე ირთვება. მანქანების წვდომა ქალაქებსა და პარკინგს არ ხსნის. მონახაზი მოქმედ სერვისად არ ჩანს.</p>
    </div>

    @foreach ($products as $product)
        @php
            $plan = ($plans[$product->id] ?? collect())->first();
            $grant = $entitlements[$product->id] ?? null;
            $active = $grant && $grant->status === 'active' && $grant->cancelled_at === null && ($grant->ends_at === null || strtotime((string) $grant->ends_at) > time());
        @endphp
        <x-slate::card>
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-medium">{{ $product->name }}</h2>
                    <span class="text-xs uppercase tracking-wide">{{ $product->status === 'published' ? 'გამოქვეყნებული' : 'მონახაზი' }}</span>
                </div>
                <p class="text-sm">{{ $product->summary }}</p>
                @if ($product->status === 'published' && $plan)
                    <p class="text-sm text-muted-foreground">ლიმიტი: {{ $plan->per_minute }} მოთხოვნა წუთში, {{ number_format((int) $plan->monthly_quota, 0, '.', ' ') }} წარმატებული მოთხოვნა თვეში. კომერციული გეგმები არის <a href="{{ route('account.billing') }}">გადახდის გვერდზე</a>.</p>
                    @if ($product->documentation_path)
                        <p class="text-sm"><a href="{{ $product->documentation_path }}">დოკუმენტაცია</a></p>
                    @endif
                    <div class="flex gap-2">
                        @if ($active)
                            <x-slate::button type="button" variant="outline" wire:click="cancel('{{ $product->slug }}')">წვდომის გაუქმება</x-slate::button>
                        @else
                            <x-slate::button type="button" wire:click="activate('{{ $product->slug }}')">წვდომის ჩართვა</x-slate::button>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">ეს პროდუქტი ჯერ არ მოქმედებს. მონაცემები და შეძენა ხელმისაწვდომი არ არის.</p>
                @endif
            </div>
        </x-slate::card>
    @endforeach
</div>
