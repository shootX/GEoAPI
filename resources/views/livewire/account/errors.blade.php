<div class="mx-auto max-w-3xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">შეცდომები</h1>
        <p class="mt-2 text-sm text-muted-foreground">ფოტოზე გამოგზავნილი ყველა შეტყობინება.</p>
    </div>

    @forelse ($reports as $report)
        <x-slate::card>
            <h2 class="font-medium">არასწორი ფოტო</h2>
            <p class="mt-1 text-sm">{{ $report['body'] }}</p>
            <p class="mt-2 text-xs text-muted-foreground">{{ $report['at'] }}</p>
        </x-slate::card>
    @empty
        <p class="text-sm text-muted-foreground">შეტყობინება ჯერ არ არის.</p>
    @endforelse
</div>
