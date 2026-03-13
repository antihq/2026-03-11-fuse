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
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Servers</h1>
        <flux:button variant="primary" href="{{ route('servers.create') }}" wire:navigate>Add Server</flux:button>
    </div>

    @if($this->servers->isEmpty())
        <p class="text-zinc-500">No servers yet.</p>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>IP Address</flux:table.column>

                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->servers as $server)
                    <flux:table.row :key="$server->id">
                        <flux:table.cell variant="strong">
                            @if($server->provisioned_at)
                                <a href="{{ route('servers.show', $server) }}" wire:navigate class="hover:underline">{{ $server->name }}</a>
                            @else
                                {{ $server->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $server->ip_address }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    @if($server->provisioned_at)
                                        <flux:menu.item icon="eye" href="{{ route('servers.show', $server) }}" wire:navigate>View</flux:menu.item>
                                    @endif
                                    <flux:menu.item icon="pencil-square" href="{{ route('servers.edit', $server) }}" wire:navigate>Edit</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteServer({{ $server->id }})" wire:confirm="Are you sure you want to delete this server?">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
