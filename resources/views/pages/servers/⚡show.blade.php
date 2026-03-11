<?php

use App\Models\Server;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    private Server $serverModel;

    public function mount(int $server): void
    {
        $this->serverModel = Server::findOrFail($server);

        if ($this->serverModel->user_id !== auth()->id()) {
            $this->redirect(route('servers.index'), navigate: true);

            return;
        }

        if ($this->serverModel->provisioned_at === null) {
            $this->redirect(route('servers.provision', $this->serverModel), navigate: true);

            return;
        }

        $this->serverId = $this->serverModel->id;
    }

    #[Computed]
    public function server(): Server
    {
        return Server::findOrFail($this->serverId);
    }

    public function delete(): void
    {
        $server = $this->server;

        if ($server->user_id !== auth()->id()) {
            return;
        }

        $server->delete();

        $this->redirect(route('servers.index'), navigate: true);
    }
};
?>
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">{{ $this->server->name }}</h1>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route('servers.edit', $this->server) }}" wire:navigate>Edit</flux:button>
            <flux:button variant="danger" wire:click="delete" wire:confirm="Are you sure you want to delete this server?">Delete</flux:button>
        </div>
    </div>

    <div class="space-y-4">
        <flux:callout variant="success" icon="check-circle">
            This server has been provisioned.
        </flux:callout>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm text-zinc-500">IP Address</dt>
                <dd class="font-mono">{{ $this->server->ip_address }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">RAM</dt>
                <dd>{{ number_format($this->server->ram_mb) }} MB</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Sites User</dt>
                <dd>{{ $this->server->sites_user }}</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">SSH Keys</dt>
                <dd>{{ $this->server->authorizedKeysCount() }} configured</dd>
            </div>
            <div>
                <dt class="text-sm text-zinc-500">Provisioned</dt>
                <dd>{{ $this->server->provisioned_at->format('M j, Y g:i A') }}</dd>
            </div>
        </dl>
    </div>
</div>
