<?php

use App\Jobs\DeploySite;
use App\Jobs\UninstallSite;
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

        if (! in_array($site->status, ['ready', 'active'])) {
            return;
        }

        DeploySite::dispatch($site->id);
    }

    public function deleteSite(int $siteId): void
    {
        $site = $this->server->sites()->findOrFail($siteId);

        $this->authorize('delete', $site);

        $hostname = $site->hostname;

        $site->delete();

        UninstallSite::dispatch($this->serverId, $hostname);

        session()->flash('status', 'Site deleted and will be removed from server shortly.');
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
<div class="max-w-lg">
    <div class="flex items-center justify-between mb-8">
        <flux:heading>{{ $this->server->name }}</flux:heading>
        <div class="flex gap-2">
            <flux:button wire:click="testConnection" wire:loading.attr="disabled">Test Connection</flux:button>
            <flux:button href="{{ route('servers.edit', $this->server) }}" wire:navigate>Edit</flux:button>
            <flux:button variant="danger" wire:click="delete" wire:confirm="Are you sure you want to delete this server?">Delete</flux:button>
        </div>
    </div>

    <div class="space-y-6">
        <div class="space-y-4">
            <div>
                <flux:heading>Server Status</flux:heading>
                <flux:text class="mt-2">This server has been provisioned.</flux:text>
            </div>

            @if($connectionStatus)
                <div>
                    <flux:heading>Connection Test</flux:heading>
                    <flux:text class="mt-2">{{ $connectionStatus }}</flux:text>
                </div>
            @endif

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt>IP Address</dt>
                    <dd>{{ $this->server->ip_address }}</dd>
                </div>
                <div>
                    <dt>RAM</dt>
                    <dd>{{ number_format($this->server->ram_mb) }} MB</dd>
                </div>
                <div>
                    <dt>Sites User</dt>
                    <dd>{{ $this->server->sites_user }}</dd>
                </div>
                <div>
                    <dt>SSH Keys</dt>
                    <dd>{{ $this->server->authorizedKeysCount() }} configured</dd>
                </div>
                <div>
                    <dt>Provisioned</dt>
                    <dd>{{ $this->server->provisioned_at->format('M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading>Sites</flux:heading>
                <flux:button href="{{ route('servers.sites.create', $this->server) }}" wire:navigate>Add Site</flux:button>
            </div>

            @if($this->server->sites->isEmpty())
                <p>No sites configured yet.</p>
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
                                    <span>{{ $site->hostname }}</span>
                                </flux:table.cell>
                                <flux:table.cell>{{ $site->php_version }}</flux:table.cell>
                                <flux:table.cell>
                                    <span>{{ $site->repository_branch }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if($site->status === 'active')
                                        <span>Active</span>
                                    @elseif($site->status === 'ready')
                                        <span>Ready to Deploy</span>
                                    @elseif($site->status === 'deploying')
                                        <span>Deploying...</span>
                                    @elseif($site->status === 'configuring')
                                        <span>Configuring...</span>
                                    @elseif($site->status === 'failed')
                                        <span>Failed</span>
                                    @else
                                        <span>Pending</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-2">
                                        <flux:button size="sm" href="{{ route('servers.sites.settings', [$this->server, $site]) }}" wire:navigate>
                                            Settings
                                        </flux:button>
                                        @if(in_array($site->status, ['ready', 'active']))
                                            <flux:button size="sm" wire:click="deploy({{ $site->id }})" wire:confirm="Deploy {{ $site->hostname }}?">
                                                {{ $site->status === 'ready' ? 'Deploy' : 'Deploy Again' }}
                                            </flux:button>
                                        @endif
                                        <flux:button size="sm" variant="danger" wire:click="deleteSite({{ $site->id }})" wire:confirm="Delete {{ $site->hostname }}? This cannot be undone.">
                                            Delete
                                        </flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>
</div>
