<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Access\ProductAccess;

final class ApiTokens extends \Electrik\Livewire\Settings\ApiTokens
{
    /**
     * @return array<string, string>
     */
    public function abilityCatalog(): array
    {
        $user = auth()->user();

        return $user instanceof \App\Models\User
            ? ProductAccess::tokenAbilitiesFor($user)
            : [];
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    protected function normalizeAbilities(array $abilities): array
    {
        $allowed = array_keys($this->abilityCatalog());
        $abilities = array_values(array_intersect($abilities, $allowed));

        return $abilities;
    }
}
