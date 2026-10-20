<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use Carapi\Platform\Connection;
use Carapi\Platform\ProjectRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('electrik::components.layouts.app')]
#[Title('პროექტები')]
final class Projects extends Component
{
    public string $name = '';

    public string $website_url = '';

    public function create(): void
    {
        $legacyId = $this->legacyId();
        $name = trim($this->name);
        $website = trim($this->website_url);
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        if ($website !== '') {
            $parts = parse_url($website);
            $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
            if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
                $this->addError('website_url', 'შეიყვანეთ საიტის მისამართი http ან https პროტოკოლით.');

                return;
            }
        }
        $id = (new ProjectRepository(Connection::pdo()))->insert($legacyId, $name, $website === '' ? null : $website);
        $this->reset('name', 'website_url');
        $this->redirect('/account/projects/'.$id);
    }

    public function render()
    {
        $rows = (new ProjectRepository(Connection::pdo()))->forUser($this->legacyId());

        return view('livewire.account.projects', ['projects' => $rows]);
    }

    private function legacyId(): int
    {
        $id = auth()->user()?->legacy_platform_user_id;
        abort_unless(is_numeric($id), 403);

        return (int) $id;
    }
}
