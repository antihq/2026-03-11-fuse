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
        <ul class="space-y-4">
            @foreach($this->servers as $server)
                <li wire:key="{{ $server->id }}" class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ $server->name }}</flux:heading>
                        <p class="text-sm text-zinc-500">
                            {{ $server->ip_address }} &middot; {{ number_format($server->ram_mb) }} MB RAM &middot; {{ $server->authorizedKeysCount() }} key{{ $server->authorizedKeysCount() !== 1 ? 's' : '' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <flux:button size="sm" variant="ghost" href="{{ route('servers.edit', $server) }}" wire:navigate>
                            Edit
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="deleteServer({{ $server->id }})"
                            wire:confirm="Are you sure you want to delete this server?"
                        >
                            Delete
                        </flux:button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
