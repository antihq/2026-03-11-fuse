<?php

use App\Models\Server;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    public string $provisionUrl = '';

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;
        $this->provisionUrl = url('/provision/'.$server->provision_token);
    }

    public function regenerateToken(): void
    {
        $server = Server::findOrFail($this->serverId);
        $server->update(['provision_token' => str()->random(64)]);
        $this->provisionUrl = url('/provision/'.$server->provision_token);
    }
};
?>
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Provision Server</h1>
    </div>

    <flux:callout icon="light-bulb" color="blue">
        <flux:callout.heading>How to provision your server</flux:callout.heading>
        <flux:callout.text>
            Run this command as root on a fresh Ubuntu 24.04 server. The link is one-time use and will expire after the script runs.
        </flux:callout.text>
    </flux:callout>

    <div class="mt-6 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Provisioning Command</span>
            <div x-data="{ copied: false }">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="clipboard-document-check"
                    x-on:click="navigator.clipboard.writeText('curl -sSL {{ $provisionUrl }} | sudo bash'); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </flux:button>
            </div>
        </div>
        <code class="block text-sm font-mono bg-zinc-900 text-zinc-100 p-3 rounded overflow-x-auto">
            curl -sSL {{ $provisionUrl }} | sudo bash
        </code>
    </div>

    <div class="mt-6 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Provisioning URL (one-time use)</span>
            <div x-data="{ copied: false }">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="clipboard-document-check"
                    x-on:click="navigator.clipboard.writeText('{{ $provisionUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </flux:button>
            </div>
        </div>
        <code class="block text-sm font-mono bg-zinc-900 text-zinc-100 p-3 rounded overflow-x-auto break-all">
            {{ $provisionUrl }}
        </code>
    </div>

    <flux:callout icon="exclamation-triangle" color="amber" class="mt-6">
        <flux:callout.heading>Ubuntu LTS Required</flux:callout.heading>
        <flux:callout.text>
            This script must be run on the latest Ubuntu LTS version (24.04) on a fresh server.
        </flux:callout.text>
    </flux:callout>

    <div class="mt-6 flex gap-2">
        <flux:button wire:click="regenerateToken" wire:confirm="This will invalidate the current URL. Continue?">
            Regenerate URL
        </flux:button>
        <flux:button variant="primary" href="{{ route('servers.index') }}" wire:navigate>
            Done
        </flux:button>
    </div>
</div>
