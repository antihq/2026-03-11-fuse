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
<div class="max-w-lg">
    <div class="flex justify-between mb-8">
        <flux:heading>Provision Server</flux:heading>
    </div>

    @if($this->server->isProvisioned())
        <div>
            <flux:heading>Server Provisioned</flux:heading>
            <flux:text class="mt-2">
                This server was provisioned on {{ $this->server->provisioned_at->format('M j, Y \a\t g:i A') }}.
            </flux:text>
        </div>

        <div class="mt-8">
            <flux:button href="{{ route('servers.show', $this->server) }}" wire:navigate>
                View Server
            </flux:button>
        </div>
    @elseif($this->server->provision_status === 'failed')
        <div>
            <flux:heading>Provisioning Failed</flux:heading>
            <flux:text class="mt-2">
                @if($this->server->task && $this->server->task->output)
                    <pre class="max-h-96 overflow-auto text-sm">{{ $this->server->task->output }}</pre>
                @else
                    An error occurred during provisioning. Please try again.
                @endif
            </flux:text>
        </div>

        <div class="mt-8 flex gap-2">
            <flux:button wire:click="retryProvision">
                Retry Provisioning
            </flux:button>
            <flux:button href="{{ route('servers.index') }}" wire:navigate>
                Back to Servers
            </flux:button>
        </div>
    @elseif($this->server->isProvisioning())
        <div wire:poll.2s>
            <div>
                <flux:heading>
                    @if($this->server->provision_status === 'ssh_setup')
                        Setting up SSH Access...
                    @else
                        Provisioning in Progress...
                    @endif
                </flux:heading>
                <flux:text class="mt-2">
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
                </flux:text>
            </div>

            @if($this->server->task)
                <div class="mt-8">
                    <div class="flex justify-between mb-2">
                        <flux:heading>Provisioning Output</flux:heading>
                        <div class="flex gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="stopPolling" wire:loading.attr="disabled">
                                Stop Polling
                            </flux:button>
                        </div>
                    </div>
                    <pre class="max-h-96 overflow-auto text-sm">{{ $this->server->task->output ?: 'Waiting for output...' }}</pre>
                </div>
            @endif
        </div>
    @else
        <div>
            <flux:heading>Set up SSH Access</flux:heading>
            <flux:text class="mt-2">
                Run this command as root on your server. This will add your SSH public key so we can provision server remotely.
            </flux:text>
        </div>

        <div class="mt-8">
            <div class="flex justify-between mb-2">
                <flux:heading>SSH Setup Command</flux:heading>
                <div x-data="{ copied: false }">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        x-on:click="navigator.clipboard.writeText('curl -sSL {{ $sshSetupUrl }} | sudo bash'); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                    </flux:button>
                </div>
            </div>
            <code class="block overflow-auto break-all text-sm">
                curl -sSL {{ $sshSetupUrl }} | sudo bash
            </code>
        </div>

        <div class="mt-8">
            <flux:heading>Ubuntu LTS Required</flux:heading>
            <flux:text class="mt-2">
                This script must be run on the latest Ubuntu LTS version (24.04) on a fresh server.
            </flux:text>
        </div>

        <div class="mt-8 flex gap-2">
            <flux:button wire:click="startPolling">
                Run Command & Monitor Progress
            </flux:button>
            <flux:button href="{{ route('servers.index') }}" wire:navigate>
                Back to Servers
            </flux:button>
        </div>
    @endif
</div>
