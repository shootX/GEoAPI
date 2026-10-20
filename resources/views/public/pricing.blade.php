<div>
<div class="max-w-3xl">
    <h1 class="text-3xl font-extrabold leading-tight">{{ __('Pricing') }}</h1>
    <p class="mt-3 text-base leading-7">{{ __('The free plan is the current limit. Pro is not on sale yet.') }}</p>
</div>
<div class="mt-8 grid gap-4 md:grid-cols-2">
    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>Free</x-slate::card-title>
        </x-slate::card-header>
        <x-slate::card-content class="space-y-4">
            <p class="text-sm leading-6">{{ __('60 requests per minute and 10,000 successful requests per calendar month, per project.') }}</p>
            <x-slate::button as="a" href="{{ route('register') }}" class="hero-cta">{{ __('Create a free account') }}</x-slate::button>
        </x-slate::card-content>
    </x-slate::card>
    <x-slate::card>
        <x-slate::card-header>
            <x-slate::card-title>Pro — {{ __('Soon') }}</x-slate::card-title>
        </x-slate::card-header>
        <x-slate::card-content>
            <p class="text-sm leading-6 text-muted-foreground">{{ __('This plan is not available yet.') }}</p>
        </x-slate::card-content>
    </x-slate::card>
</div>
<div class="mt-8 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="py-2 text-left"></th>
                <th class="py-2 text-left">Free</th>
                <th class="py-2 text-left">Pro</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Requests per minute') }}</th>
                <td>60</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Successful requests per month') }}</th>
                <td>10 000</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Server API') }}</th>
                <td>{{ __('Included') }}</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Widget') }}</th>
                <td>{{ __('Included') }}</td>
                <td>{{ __('Soon') }}</td>
            </tr>
        </tbody>
    </table>
</div>
</div>
