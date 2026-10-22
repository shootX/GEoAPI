<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.auth')]
#[Title('შესვლა')]
final class Login extends \Electrik\Livewire\Auth\Login
{
    public function render()
    {
        return view('livewire.auth.login')->layoutData([
            'title' => __('Sign in').' — GeoAPI',
            'description' => __('Welcome back. Enter your credentials to continue.'),
        ]);
    }
}
