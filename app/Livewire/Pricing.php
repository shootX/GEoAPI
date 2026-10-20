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
            'description' => __('The free plan is the current limit. Pro is not on sale yet.'),
        ]);
    }
}
