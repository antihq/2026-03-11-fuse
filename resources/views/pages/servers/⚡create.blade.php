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
    public string $sites_user = 'fuse';

    #[Validate('nullable|string')]
    public string $authorized_keys = '';

    public function save(): void
    {
        $this->validate();

        $this->validate(['authorized_keys' => [new ValidSshKeys]]);

        $server = auth()->user()->servers()->create([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'ram_mb' => (int) $this->ram_mb,
            'sites_user' => $this->sites_user,
            'authorized_keys' => $this->authorized_keys ?: null,
            'ssh_setup_token' => str()->random(64),
            'provision_status' => 'pending',
            'mysql_root_password' => str()->password(32, letters: true, numbers: true, symbols: false),
            'deploy_user_password' => str()->password(32, letters: true, numbers: true, symbols: false),
        ]);

        $this->redirect(route('servers.provision', $server), navigate: true);
    }
};
?>
<div class="max-w-lg">
    <flux:heading>Add Server</flux:heading>

    <div class="mt-8">
        <form wire:submit="save" class="space-y-8">
            <flux:input wire:model="name" label="Name" />
            <flux:input wire:model="ip_address" label="IP Address" />
            <flux:input wire:model="ram_mb" label="RAM (MB)" type="number" />
            <flux:input wire:model="sites_user" label="Sites User" />
            <flux:textarea wire:model="authorized_keys" label="SSH Keys" rows="3" />

            <flux:button type="submit">Create</flux:button>
        </form>
    </div>
</div>
