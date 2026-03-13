<?php

use App\Models\Server;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    public string $sshSetupUrl = '';

    #[Url(as: 'poll', history: false)]
    public bool $poll = false;

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;
        $this->sshSetupUrl = $server->ssh_setup_token ? url('/ssh-setup/'.$server->ssh_setup_token) : '';

        if ($server->isProvisioning()) {
            $this->poll = true;
        }
    }

    #[Computed]
    public function server(): Server
    {
        return Server::with('task')->findOrFail($this->serverId);
    }

    public function startPolling(): void
    {
        $this->poll = true;
    }

    public function stopPolling(): void
    {
        $this->poll = false;
    }

    public function getIsPollingProperty(): bool
    {
        return $this->poll && $this->server->isProvisioning();
    }

    public function retryProvision(): void
    {
        $server = $this->server;

        if ($server->provision_status === 'failed') {
            $server->update([
                'ssh_setup_token' => str()->random(64),
                'ssh_ready_at' => null,
                'provision_status' => 'pending',
                'provision_task_id' => null,
            ]);

            $this->sshSetupUrl = url('/ssh-setup/'.$server->fresh()->ssh_setup_token);
            $this->poll = false;
        }
    }
};
?>
<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Provision Server</h1>
    </div>

    @if($this->server->isProvisioned())
        <flux:callout icon="check-circle" color="green">
            <flux:callout.heading>Server Provisioned</flux:callout.heading>
            <flux:callout.text>
                This server was provisioned on {{ $this->server->provisioned_at->format('M j, Y \a\t g:i A') }}.
            </flux:callout.text>
        </flux:callout>

        <div class="mt-6">
            <flux:button variant="primary" href="{{ route('servers.show', $this->server) }}" wire:navigate>
                View Server
            </flux:button>
        </div>
    @elseif($this->server->provision_status === 'failed')
        <flux:callout icon="exclamation-triangle" color="red">
            <flux:callout.heading>Provisioning Failed</flux:callout.heading>
            <flux:callout.text>
                @if($this->server->task && $this->server->task->output)
                    <pre class="mt-2 text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded overflow-x-auto max-h-96">{{ $this->server->task->output }}</pre>
                @else
                    An error occurred during provisioning. Please try again.
                @endif
            </flux:callout.text>
        </flux:callout>

        <div class="mt-6 flex gap-2">
            <flux:button wire:click="retryProvision" variant="primary">
                Retry Provisioning
            </flux:button>
            <flux:button href="{{ route('servers.index') }}" wire:navigate>
                Back to Servers
            </flux:button>
        </div>
    @elseif($this->server->isProvisioning())
        <div wire:poll.2s>
            <flux:callout icon="arrow-path" color="blue">
                <flux:callout.heading>
                    @if($this->server->provision_status === 'ssh_setup')
                        Setting up SSH Access...
                    @else
                        Provisioning in Progress...
                    @endif
                </flux:callout.heading>
                <flux:callout.text>
                    @if($this->server->provision_status === 'ssh_setup')
                        Waiting for SSH key to be added to your server. Run the command below as root.
                    @else
                        @if($this->server->task)
                            @if($this->server->task->isRunning())
                                Running provisioning tasks on your server. This may take several minutes...
                            @elseif($this->server->task->exit_code !== 0)
                                Provisioning failed with exit code {{ $this->server->task->exit_code }}.
                            @else
                                Server provisioning complete.
                            @endif
                        @else
                            Starting provisioning...
                        @endif
                    @endif
                </flux:callout.text>
            </flux:callout>

            @if($this->server->task)
                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Provisioning Output</span>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="stopPolling" wire:loading.attr="disabled">
                                Stop Polling
                            </flux:button>
                        </div>
                    </div>
                    <pre class="text-sm font-mono bg-zinc-100 dark:bg-zinc-800 p-3 rounded overflow-x-auto max-h-96">{{ $this->server->task->output ?: 'Waiting for output...' }}</pre>
                </div>
            @endif
        </div>
    @else
        <flux:callout icon="light-bulb" color="blue">
            <flux:callout.heading>Set up SSH Access</flux:callout.heading>
            <flux:callout.text>
                Run this command as root on your server. This will add your SSH public key so we can provision the server remotely.
            </flux:callout.text>
        </flux:callout>

        <div class="mt-6 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">SSH Setup Command</span>
                <div x-data="{ copied: false }">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="clipboard-document-check"
                        x-on:click="navigator.clipboard.writeText('curl -sSL {{ $sshSetupUrl }} | sudo bash'); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                    </flux:button>
                </div>
            </div>
            <code class="block text-sm font-mono bg-zinc-900 text-zinc-100 p-3 rounded overflow-x-auto">
                curl -sSL {{ $sshSetupUrl }} | sudo bash
            </code>
        </div>

        <div class="mt-6 p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">SSH Setup URL (one-time use)</span>
                <div x-data="{ copied: false }">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="clipboard-document-check"
                        x-on:click="navigator.clipboard.writeText('{{ $sshSetupUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                    </flux:button>
                </div>
            </div>
            <code class="block text-sm font-mono bg-zinc-900 text-zinc-100 p-3 rounded overflow-x-auto break-all">
                {{ $sshSetupUrl }}
            </code>
        </div>

        <flux:callout icon="exclamation-triangle" color="amber" class="mt-6">
            <flux:callout.heading>Ubuntu LTS Required</flux:callout.heading>
            <flux:callout.text>
                This script must be run on the latest Ubuntu LTS version (24.04) on a fresh server.
            </flux:callout.text>
        </flux:callout>

        <div class="mt-6 flex gap-2">
            <flux:button wire:click="startPolling" variant="primary">
                Run Command & Monitor Progress
            </flux:button>
            <flux:button href="{{ route('servers.index') }}" wire:navigate>
                Back to Servers
            </flux:button>
        </div>
    @endif
</div>
