<?php

use App\Rules\ValidSshKeys;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
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

    public function save(): void
    {
        $this->validate();

        $this->validate(['authorized_keys' => [new ValidSshKeys]]);

        $server = auth()->user()->team->servers()->create([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'ram_mb' => (int) $this->ram_mb,
            'sites_user' => $this->sites_user,
            'authorized_keys' => $this->authorized_keys ?: null,
            'provision_token' => str()->random(64),
        ]);

        $this->redirect(route('servers.provision', $server), navigate: true);
    }
};
?>
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Add Server</h1>
    </div>

    <form wire:submit="save" class="space-y-4">
        <flux:input
            wire:model="name"
            label="Server Name"
            placeholder="Production Web 1"
            required
        />

        <flux:input
            wire:model="ip_address"
            label="IP Address"
            placeholder="192.168.1.100"
            required
        />

        <flux:input
            wire:model="ram_mb"
            label="RAM (MB)"
            placeholder="2048"
            type="number"
            required
        />

        <flux:input
            wire:model="sites_user"
            label="Sites User"
            placeholder="deploy"
            hint="Username that will own all hosted sites (lowercase, starts with letter)"
            required
        />

        <flux:textarea
            wire:model="authorized_keys"
            label="Authorized SSH Keys"
            placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI... user@example.com"
            hint="One public key per line - these will be added to the sites user"
            rows="4"
        />

        @error('authorized_keys')
            <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text>
        @enderror

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Create Server</flux:button>
            <flux:button variant="ghost" href="{{ route('servers.index') }}" wire:navigate>Cancel</flux:button>
        </div>
    </form>
</div>
