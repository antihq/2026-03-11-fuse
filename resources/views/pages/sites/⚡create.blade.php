<?php

use App\Jobs\ConfigureSite;
use App\Models\Server;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    #[Validate('required|string|max:255|regex:/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/')]
    public string $hostname = '';

    #[Validate('required|in:8.2,8.3,8.4')]
    public string $php_version = '8.4';

    #[Validate('required|string|max:255')]
    public string $repository_url = '';

    #[Validate('required|string|max:255')]
    public string $repository_branch = 'main';

    #[Computed]
    public function server(): Server
    {
        return Server::findOrFail($this->serverId);
    }

    public function mount(int $server): void
    {
        $serverModel = Server::findOrFail($server);

        if ($serverModel->user_id !== auth()->id()) {
            $this->redirect(route('servers.index'), navigate: true);

            return;
        }

        if ($serverModel->provisioned_at === null) {
            $this->redirect(route('servers.provision', $serverModel), navigate: true);

            return;
        }

        $this->serverId = $serverModel->id;
    }

    public function save(): void
    {
        $this->validate();

        $this->validate([
            'hostname' => 'unique:sites,hostname,NULL,id,server_id,'.$this->serverId,
        ]);

        $site = $this->server()->sites()->create([
            'hostname' => $this->hostname,
            'php_version' => $this->php_version,
            'size' => 'large',
            'repository_url' => $this->repository_url,
            'repository_branch' => $this->repository_branch,
        ]);

        ConfigureSite::dispatch($site->id);

        $this->redirect(route('servers.show', $this->serverId), navigate: true);
    }
};
?>
<div>
    <div class="mb-6">
        <flux:button variant="ghost" href="{{ route('servers.show', $this->server) }}" wire:navigate>
            &larr; Back to {{ $this->server->name }}
        </flux:button>
    </div>

    <h1 class="text-xl font-semibold mb-4">Add Site</h1>

    <form wire:submit="save" class="space-y-4 max-w-lg">
        <flux:input wire:model="hostname" label="Hostname" placeholder="example.com" />

        <flux:select wire:model="php_version" label="PHP Version">
            <flux:select.option value="8.2">PHP 8.2</flux:select.option>
            <flux:select.option value="8.3">PHP 8.3</flux:select.option>
            <flux:select.option value="8.4">PHP 8.4</flux:select.option>
        </flux:select>

        <flux:input wire:model="repository_url" label="Repository URL" placeholder="git@github.com:user/repo.git" />

        <flux:input wire:model="repository_branch" label="Branch" placeholder="main" />

        <flux:button type="submit" variant="primary">Create Site</flux:button>
    </form>
</div>
