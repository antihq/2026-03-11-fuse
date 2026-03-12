<?php

use App\Models\Server;
use App\Models\Site;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    #[Locked]
    public int $siteId;

    #[Validate('nullable|string')]
    public string $hook_before_updating_repository = '';

    #[Validate('nullable|string')]
    public string $hook_after_updating_repository = '';

    public function mount(int $server, int $site): void
    {
        $serverModel = Server::findOrFail($server);
        $siteModel = $serverModel->sites()->findOrFail($site);

        if ($serverModel->user_id !== auth()->id()) {
            abort(403);
        }

        $this->serverId = $serverModel->id;
        $this->siteId = $siteModel->id;
        $this->hook_before_updating_repository = $siteModel->hook_before_updating_repository ?? '';
        $this->hook_after_updating_repository = $siteModel->hook_after_updating_repository ?? '';
    }

    #[Computed]
    public function server(): Server
    {
        return Server::findOrFail($this->serverId);
    }

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    public function save(): void
    {
        $this->validate();

        $this->site()->update([
            'hook_before_updating_repository' => $this->hook_before_updating_repository ?: null,
            'hook_after_updating_repository' => $this->hook_after_updating_repository ?: null,
        ]);

        session()->flash('status', 'Deployment hooks updated successfully.');

        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }
};
?>
<div>
    <div class="mb-6">
        <flux:button variant="ghost" href="{{ route('servers.show', $this->server) }}" wire:navigate>
            &larr; Back to {{ $this->server->name }}
        </flux:button>
    </div>

    <h1 class="text-xl font-semibold mb-1">Site Settings</h1>
    <p class="text-zinc-500 mb-6">{{ $this->site->hostname }}</p>

    @if(session('status'))
        <flux:callout variant="success" icon="check-circle" class="mb-6">
            {{ session('status') }}
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div>
            <flux:textarea
                wire:model="hook_before_updating_repository"
                label="Hook: Before Updating Repository"
                rows="6"
                placeholder="Runs before git pull/clone. Current working directory: repository/"
            />
            <p class="text-sm text-zinc-500 mt-1">
                This hook runs in the repository directory before pulling changes.
            </p>
        </div>

        <div>
            <flux:textarea
                wire:model="hook_after_updating_repository"
                label="Hook: After Updating Repository"
                rows="20"
                placeholder="Runs after git pull/clone. Current working directory: repository/"
            />
            <p class="text-sm text-zinc-500 mt-1">
                This hook runs after pulling changes. Use this for composer install, npm build, artisan commands, etc.
            </p>
        </div>

        <flux:button type="submit" variant="primary">Save Hooks</flux:button>
    </form>
</div>
