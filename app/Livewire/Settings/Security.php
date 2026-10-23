<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Livewire\Attributes\Layout;

/** Electrik's password, passkey and two-factor page in the cabinet design. */
#[Layout('components.layouts.cabinet')]
final class Security extends \Electrik\Livewire\Settings\Security
{
    public function render()
    {
        $en = app()->getLocale() === 'en';

        return view('livewire.settings.security', parent::render()->getData())
            ->layoutData(['title' => ($en ? 'Security' : 'უსაფრთხოება').' — GeoAPI']);
    }
}
