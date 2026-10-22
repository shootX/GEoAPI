<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Access\AdminAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('electrik::components.layouts.app')]
#[Title('შეცდომები')]
final class Errors extends Component
{
    public function mount(): void
    {
        abort_unless(self::visible(auth()->user()), 403);
    }

    public function render()
    {
        return view('livewire.account.errors', [
            'reports' => self::reports(),
        ]);
    }

    public static function visible(mixed $user): bool
    {
        return $user instanceof User && AdminAccess::allows($user);
    }

    /** @return list<array{body: string, at: string}> */
    public static function reports(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('notifications')) {
            return [];
        }

        $rows = [];
        $seen = [];
        $found = DB::table('notifications')->orderByDesc('created_at')->limit(200)->get(['data', 'created_at']);
        foreach ($found as $row) {
            $data = json_decode((string) $row->data, true);
            if (! is_array($data) || ($data['title'] ?? '') !== 'არასწორი ფოტო') {
                continue;
            }
            $body = trim((string) ($data['body'] ?? ''));
            if ($body === '' || isset($seen[$body])) {
                continue;
            }
            $seen[$body] = true;
            $rows[] = [
                'body' => $body,
                'at' => (string) $row->created_at,
            ];
        }

        return $rows;
    }
}
