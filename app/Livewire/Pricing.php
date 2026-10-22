<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Pricing')]
final class Pricing extends Component
{
    public function render()
    {
        return view('public.pricing')->layoutData([
            'title' => __('Pricing').' — GeoAPI',
            'description' => config('billing.v2')
                ? 'Free, Starter, and Growth. Prices include VAT. A year is 15% less and does not renew by itself.'
                : 'Free, Basic, and Premium. Prices include VAT.',
            'active' => 'pricing',
            'bleed' => true,
        ]);
    }
}
