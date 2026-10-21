<div class="mx-auto max-w-3xl space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-semibold">გადახდა</h1>
        <p class="mt-2 text-sm text-muted-foreground">ფასი დოლარშია და დღგ-ს შეიცავს. წელიწადი 12 თვის ფასზე 12%-ით ნაკლებია. ლარი ნაჩვენებია ეროვნული ბანკის კურსით.</p>
    </div>

    @if (session('billing'))
        <p class="text-sm">{{ session('billing') }}</p>
    @endif
    @if ($notice === 'return')
        <p class="text-sm">ბანკის დადასტურება ჯერ არ არის. გადახდის გვერდი უფლებას არ ჩართავს.</p>
    @endif
    @if ($notice === 'fail')
        <p class="text-sm">გადახდა არ დასრულდა. კვოტა არ შეცვლილა.</p>
    @endif
    @unless ($configured)
        <p class="text-sm">payments not configured. გადახდის პროვაიდერი არ არის მიერთებული.</p>
    @endunless
    @if ($rate === null)
        <p class="text-sm">the rate is unavailable. გადახდილი შეკვეთა კურსის გარეშე არ იქმნება.</p>
    @else
        <p class="text-sm">ეროვნული ბანკის კურსი: 1 USD = {{ $rate['rate'] }} GEL, თარიღი {{ $rate['rated_on'] }}.</p>
    @endif
    @error('checkout')
        <p class="text-sm">{{ $message }}</p>
    @enderror

    @if ($projects === [])
        <p class="text-sm">პროექტი ჯერ არ არის.</p>
    @else
        <label class="block text-sm">პროექტი
            <select wire:model="projectId" class="mt-1 block w-full">
                @foreach ($projects as $project)
                    <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                @endforeach
            </select>
        </label>

        <section class="space-y-2 rounded border p-4">
            <h2 class="text-lg font-medium">Free</h2>
            <p class="text-sm">100 წარმატებული მოთხოვნა თვეში. 0 USD.</p>
            <p class="text-sm text-muted-foreground">არჩევა ამ პროექტის თვიურ ზღვარს 100-მდე დაწევს, მათ შორის მაშინაც, თუ ახლა 10 000 აქვს.</p>
            <button type="button" wire:click="chooseFree">უფასო გეგმის არჩევა</button>
        </section>

        @foreach (['basic' => 'Basic', 'premium' => 'Premium'] as $code => $label)
            @php
                $monthCents = \App\Billing\Plans::usdCents($code, 'month');
                $yearCents = \App\Billing\Plans::usdCents($code, 'year');
                $monthUsd = number_format($monthCents / 100, 2, '.', '');
                $yearUsd = number_format($yearCents / 100, 2, '.', '');
                $monthGel = $rate ? \App\Billing\Plans::formatTetri(\App\Billing\Plans::gelTetri($monthCents, $rate['rate'], $rate['quantity'])) : null;
                $yearGel = $rate ? \App\Billing\Plans::formatTetri(\App\Billing\Plans::gelTetri($yearCents, $rate['rate'], $rate['quantity'])) : null;
            @endphp
            <section class="space-y-2 rounded border p-4">
                <h2 class="text-lg font-medium">{{ $label }}</h2>
                <p class="text-sm">
                    @if ($code === 'premium')
                        შეუზღუდავი წარმატებული მოთხოვნა თვეში.
                    @else
                        2 000 წარმატებული მოთხოვნა თვეში.
                    @endif
                </p>
                <p class="text-sm">თვე: {{ $monthUsd }} USD @if ($monthGel) / {{ $monthGel }} GEL @endif</p>
                <p class="text-sm">წელი: {{ $yearUsd }} USD @if ($yearGel) / {{ $yearGel }} GEL @endif</p>
                <div class="flex gap-2">
                    <button type="button" wire:click="checkout('{{ $code }}', 'month')" @if (! $configured || $rate === null) disabled @endif>თვე</button>
                    <button type="button" wire:click="checkout('{{ $code }}', 'year')" @if (! $configured || $rate === null) disabled @endif>წელი</button>
                </div>
            </section>
        @endforeach
    @endif

    @if (count($orders) > 0)
        <div class="space-y-2">
            <h2 class="text-lg font-medium">შეკვეთები</h2>
            @foreach ($orders as $order)
                <p class="text-sm">{{ $order->plan_code }} · {{ $order->status }} · {{ $order->gel_amount }} GEL</p>
            @endforeach
        </div>
    @endif
</div>
