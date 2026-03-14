<?php

use App\Helpers\RemoteFile;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $serverId;

    #[Locked]
    public int $siteId;

    #[Validate('nullable|string')]
    public string $caddyfileContent = '';

    public bool $caddyfileLoaded = false;

    public string $loadError = '';

    public string $saveError = '';

    public string $validationError = '';

    public function mount(int $server, int $site): void
    {
        $serverModel = Server::findOrFail($server);
        $siteModel = $serverModel->sites()->findOrFail($site);

        if ($serverModel->user_id !== auth()->id()) {
            abort(403);
        }

        $this->serverId = $serverModel->id;
        $this->siteId = $siteModel->id;
    }

    #[Computed]
    public function server(): Server
    {
        return Server::findOrFail($this->serverId);
    }

    #[Computed]
    public function site(): Site
    {
        return Site::findOrFail($this->siteId);
    }

    public function loadCaddyfile(): void
    {
        $site = $this->site();

        $this->authorize('update', $site);

        try {
            $content = RemoteFile::read($site, $site->caddyfilePath());

            if ($content === '') {
                $this->loadError = 'Unable to read Caddyfile or file is empty.';

                return;
            }

            $this->caddyfileContent = $content;
            $this->caddyfileLoaded = true;
            $this->loadError = '';
        } catch (Exception $e) {
            $this->loadError = 'Failed to load Caddyfile: '.$e->getMessage();
        }
    }

    public function saveCaddyfile(): void
    {
        $this->validate();

        $site = $this->site();

        $this->authorize('update', $site);

        $this->saveError = '';
        $this->validationError = '';

        try {
            $success = RemoteFile::write($site, $site->caddyfilePath(), $this->caddyfileContent);

            if (! $success) {
                $this->saveError = 'Failed to save Caddyfile.';

                return;
            }

            $validationTask = Task::create([
                'user_id' => auth()->id(),
                'server_id' => $site->server_id,
                'ssh_user' => $site->server->sites_user,
                'script' => view('scripts.validate-caddyfile', [
                    'caddyfilePath' => $site->caddyfilePath(),
                ])->render(),
                'timeout' => 30,
            ]);

            $validationTask->run();

            if (! $validationTask->successful()) {
                $this->validationError = 'Caddyfile validation failed: '.$validationTask->output;

                return;
            }

            $reloadTask = Task::create([
                'user_id' => auth()->id(),
                'server_id' => $site->server_id,
                'ssh_user' => 'root',
                'script' => 'service caddy reload',
                'timeout' => 30,
            ]);

            $reloadTask->run();

            if (! $reloadTask->successful()) {
                $this->saveError = 'Caddyfile saved successfully but failed to reload Caddy: '.$reloadTask->output;

                return;
            }

            session()->flash('status', 'Caddyfile saved and reloaded successfully.');
            $this->caddyfileLoaded = false;
            $this->caddyfileContent = '';
        } catch (Exception $e) {
            $this->saveError = 'Failed to save Caddyfile: '.$e->getMessage();
        }
    }
};
?>
<div>
    <div class="mb-6">
        <flux:button href="{{ route('servers.show', $this->server) }}" wire:navigate>
            &larr; Back to {{ $this->server->name }}
        </flux:button>
    </div>

    <flux:heading>Edit Caddyfile</flux:heading>
    <flux:text class="mb-8">{{ $this->site->hostname }}</flux:text>

    @if(session('status'))
        <div class="mt-8 mb-4">
            <flux:text color="green">{{ session('status') }}</flux:text>
        </div>
    @endif

    @if(!$caddyfileLoaded)
        <flux:button wire:click="loadCaddyfile">Load Caddyfile</flux:button>
    @endif

    @if($loadError)
        <flux:text color="red" class="mt-2">{{ $loadError }}</flux:text>
    @endif

    @if($caddyfileLoaded)
        <form wire:submit="saveCaddyfile" class="mt-4">
            <flux:textarea
                wire:model="caddyfileContent"
                rows="30"
                placeholder="Caddyfile configuration..."
                monospace
            />
            <p class="text-sm mt-1">
                Path: {{ $this->site->caddyfilePath() }}
            </p>

            @if($validationError)
                <flux:text color="red" class="mt-2">{{ $validationError }}</flux:text>
            @endif

            @if($saveError)
                <flux:text color="red" class="mt-2">{{ $saveError }}</flux:text>
            @endif

            <div class="flex gap-2 mt-4">
                <flux:button type="submit" wire:loading.attr="disabled">Save & Reload Caddy</flux:button>
                <flux:button wire:click="$set('caddyfileLoaded', false)" variant="subtle">Cancel</flux:button>
            </div>
        </form>
    @endif
</div>
