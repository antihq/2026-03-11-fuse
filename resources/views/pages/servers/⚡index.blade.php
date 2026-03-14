<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        if (auth()->user()->servers()->doesntExist()) {
            $this->redirect(route('servers.create'), navigate: true);
        }
    }

    #[Computed]
    public function servers()
    {
        return auth()->user()->servers()->latest()->get();
    }

    public function deleteServer(int $serverId): void
    {
        $server = $this->servers->firstWhere('id', $serverId);

        if ($server) {
            $server->delete();
        }

        unset($this->servers);
    }
};
?>
<div>
    <div class="flex items-center justify-between mb-8">
        <flux:heading>Servers</flux:heading>
        <div class="flex gap-2">
            <flux:button icon="arrow-path" wire:click="$refresh" />
            <flux:button href="{{ route('servers.create') }}" wire:navigate>Add Server</flux:button>
        </div>
    </div>

    @if($this->servers->isEmpty())
        <p>No servers yet.</p>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>IP Address</flux:table.column>
                <flux:table.column>Status</flux:table.column>

                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->servers as $server)
                    <flux:table.row :key="$server->id">
                        <flux:table.cell variant="strong">
                            @if($server->provisioned_at)
                                <a href="{{ route('servers.show', $server) }}" wire:navigate>{{ $server->name }}</a>
                            @else
                                <a href="{{ route('servers.provision', $server) }}" wire:navigate>{{ $server->name }}</a>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs font-mono">{{ $server->ip_address }}</flux:table.cell>
                        <flux:table.cell>
                            @if($server->isProvisioned())
                                <flux:badge size="sm" color="green">Provisioned</flux:badge>
                            @elseif($server->isProvisioning())
                                <flux:badge size="sm" color="blue">Provisioning</flux:badge>
                            @else
                                <flux:badge size="sm" color="yellow">Pending</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-horizontal" variant="ghost" inset="top bottom" />
                                <flux:menu>
                                    @if($server->provisioned_at)
                                        <flux:menu.item href="{{ route('servers.show', $server) }}" wire:navigate>View</flux:menu.item>
                                    @else
                                        <flux:menu.item href="{{ route('servers.provision', $server) }}" wire:navigate>Provision</flux:menu.item>
                                        <flux:menu.item href="{{ route('servers.edit', $server) }}" wire:navigate>Edit</flux:menu.item>
                                    @endif
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" wire:click="deleteServer({{ $server->id }})" wire:confirm="Are you sure you want to delete this server?">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
