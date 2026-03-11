<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function servers()
    {
        return auth()->user()->team->servers()->latest()->get();
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
                <flux:table.column>RAM</flux:table.column>
                <flux:table.column>Keys</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($this->servers as $server)
                    <flux:table.row :key="$server->id">
                        <flux:table.cell variant="strong">{{ $server->name }}</flux:table.cell>
                        <flux:table.cell>{{ $server->ip_address }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($server->ram_mb) }} MB</flux:table.cell>
                        <flux:table.cell>{{ $server->authorizedKeysCount() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" href="{{ route('servers.edit', $server) }}" wire:navigate>Edit</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="deleteServer({{ $server->id }})" wire:confirm="Are you sure you want to delete this server?">Delete</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
