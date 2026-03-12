<?php

use App\Jobs\DeploySite;
use App\Models\Server;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    public ?string $connectionStatus = null;

    public ?bool $connectionSuccess = null;

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
        return Server::with('sites')->findOrFail($this->serverId);
    }

    public function testConnection(): void
    {
        $server = $this->server;
        $user = auth()->user();

        if (empty($user->ssh_private_key)) {
            $this->connectionStatus = 'No SSH private key configured for your account.';
            $this->connectionSuccess = false;

            return;
        }

        $task = Task::create([
            'user_id' => $user->id,
            'server_id' => $server->id,
            'ssh_user' => 'root',
            'script' => 'echo "Connected to $(hostname)" && uname -a',
            'timeout' => 10,
        ]);

        $task->run();

        if ($task->status === 'timeout') {
            $this->connectionStatus = 'Connection timed out.';
            $this->connectionSuccess = false;
        } elseif ($task->exit_code === 0) {
            $this->connectionStatus = 'Connection successful!';
            $this->connectionSuccess = true;
        } else {
            $this->connectionStatus = 'Connection failed: '.$task->output;
            $this->connectionSuccess = false;
        }
    }

    public function deploy(int $siteId): void
    {
        $site = $this->server->sites()->findOrFail($siteId);

        if ($site->status !== 'ready') {
            return;
        }

        DeploySite::dispatch($site->id);
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
            <flux:button variant="ghost" wire:click="testConnection" wire:loading.attr="disabled">Test Connection</flux:button>
            <flux:button variant="ghost" href="{{ route('servers.edit', $this->server) }}" wire:navigate>Edit</flux:button>
            <flux:button variant="danger" wire:click="delete" wire:confirm="Are you sure you want to delete this server?">Delete</flux:button>
        </div>
    </div>

    <div class="space-y-6">
        <div class="space-y-4">
            <flux:callout variant="success" icon="check-circle">
                This server has been provisioned.
            </flux:callout>

            @if($connectionStatus)
                <flux:callout variant="{{ $connectionSuccess ? 'success' : 'warning' }}">
                    {{ $connectionStatus }}
                </flux:callout>
            @endif

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

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Sites</h2>
                <flux:button variant="primary" href="{{ route('servers.sites.create', $this->server) }}" wire:navigate>Add Site</flux:button>
            </div>

            @if($this->server->sites->isEmpty())
                <p class="text-zinc-500">No sites configured yet.</p>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Hostname</flux:table.column>
                        <flux:table.column>PHP</flux:table.column>
                        <flux:table.column>Branch</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach($this->server->sites as $site)
                            <flux:table.row :key="$site->id">
                                <flux:table.cell>
                                    <span class="font-mono">{{ $site->hostname }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $site->php_version }}</flux:table.cell>
                                <flux:table.cell>
                                    <span class="font-mono text-sm">{{ $site->repository_branch }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($site->status === 'active')
                                        <span class="text-green-600">Active</span>
                                    @elseif($site->status === 'ready')
                                        <span class="text-blue-600">Ready to Deploy</span>
                                    @elseif($site->status === 'deploying')
                                        <span class="text-yellow-600">Deploying...</span>
                                    @elseif($site->status === 'configuring')
                                        <span class="text-yellow-600">Configuring...</span>
                                    @elseif($site->status === 'failed')
                                        <span class="text-red-600">Failed</span>
                                    @else
                                        <span class="text-zinc-500">Pending</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($site->status === 'ready')
                                        <flux:button size="sm" wire:click="deploy({{ $site->id }})" wire:confirm="Deploy {{ $site->hostname }}?">
                                            Deploy
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>
</div>
