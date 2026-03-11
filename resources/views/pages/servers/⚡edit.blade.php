<?php

use App\Models\Server;
use App\Rules\ValidSshKeys;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|ip')]
    public string $ip_address = '';

    #[Validate('required|integer|min:1')]
    public string $ram_mb = '';

    #[Validate('required|string|min:1|max:32|regex:/^[a-z][a-z0-9_-]*$/')]
    public string $sites_user = 'deploy';

    #[Validate('nullable|string')]
    public string $authorized_keys = '';

    public function mount(int $server): void
    {
        $serverModel = Server::findOrFail($server);

        if ($serverModel->user_id !== auth()->id()) {
            $this->redirect(route('servers.index'), navigate: true);

            return;
        }

        $this->serverId = $serverModel->id;
        $this->name = $serverModel->name;
        $this->ip_address = $serverModel->ip_address;
        $this->ram_mb = (string) $serverModel->ram_mb;
        $this->sites_user = $serverModel->sites_user;
        $this->authorized_keys = $serverModel->authorized_keys ?? '';
    }

    public function save(): void
    {
        $this->validate();

        $this->validate(['authorized_keys' => [new ValidSshKeys]]);

        $server = Server::where('user_id', auth()->id())->findOrFail($this->serverId);

        $server->update([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'ram_mb' => (int) $this->ram_mb,
            'sites_user' => $this->sites_user,
            'authorized_keys' => $this->authorized_keys ?: null,
        ]);

        $this->redirect(route('servers.index'), navigate: true);
    }
};
?>
<div>
    <h1 class="text-xl font-semibold mb-4">Edit Server</h1>

    <form wire:submit="save" class="space-y-2">
        <flux:input wire:model="name" label="Name" />
        <flux:input wire:model="ip_address" label="IP Address" />
        <flux:input wire:model="ram_mb" label="RAM (MB)" type="number" />
        <flux:input wire:model="sites_user" label="Sites User" />
        <flux:textarea wire:model="authorized_keys" label="SSH Keys" rows="3" />

        <flux:button type="submit" variant="primary">Update</flux:button>
    </form>
</div>
