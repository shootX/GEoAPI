<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Setting;
use Electrik\Livewire\Dashboard as ElectrikDashboard;
use Illuminate\Contracts\View\View;
use Throwable;

class Dashboard extends ElectrikDashboard
{
    public function render(): View
    {
        $view = parent::render();

        return view('livewire.dashboard', array_merge($view->getData(), [
            'comingSoonOn' => $this->comingSoonOn(),
        ]));
    }

    private function comingSoonOn(): bool
    {
        try {
            return Setting::query()->find('coming_soon')?->value === true;
        } catch (Throwable) {
            return false;
        }
    }
}
