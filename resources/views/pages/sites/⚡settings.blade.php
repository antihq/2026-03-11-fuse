<?php

use App\Helpers\RemoteFile;
use App\Models\Server;
use App\Models\Site;
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
    public string $hook_before_updating_repository = '';

    #[Validate('nullable|string')]
    public string $hook_after_updating_repository = '';

    #[Validate('nullable|string')]
    public string $envContent = '';

    public bool $envLoaded = false;

    public string $envLoadError = '';

    public string $envSaveError = '';

    public function mount(int $server, int $site): void
    {
        $serverModel = Server::findOrFail($server);
        $siteModel = $serverModel->sites()->findOrFail($site);

        if ($serverModel->user_id !== auth()->id()) {
            abort(403);
        }

        $this->serverId = $serverModel->id;
        $this->siteId = $siteModel->id;
        $this->hook_before_updating_repository = $siteModel->hook_before_updating_repository ?? '';
        $this->hook_after_updating_repository = $siteModel->hook_after_updating_repository ?? '';
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

    public function save(): void
    {
        $this->validate();

        $this->site()->update([
            'hook_before_updating_repository' => $this->hook_before_updating_repository ?: null,
            'hook_after_updating_repository' => $this->hook_after_updating_repository ?: null,
        ]);

        session()->flash('status', 'Deployment hooks updated successfully.');

        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function loadEnv(): void
    {
        $site = $this->site();

        $this->authorize('view', $site);

        try {
            $content = RemoteFile::read($site, $site->envPath());

            if ($content === '') {
                $this->envLoadError = 'Unable to read .env file or file is empty.';

                return;
            }

            $this->envContent = $content;
            $this->envLoaded = true;
            $this->envLoadError = '';
        } catch (Exception $e) {
            $this->envLoadError = 'Failed to load .env file: '.$e->getMessage();
        }
    }

    public function saveEnv(): void
    {
        $site = $this->site();

        $this->authorize('update', $site);

        try {
            $success = RemoteFile::write($site, $site->envPath(), $this->envContent);

            if (! $success) {
                $this->envSaveError = 'Failed to save .env file.';

                return;
            }

            session()->flash('envStatus', '.env file saved successfully.');
            $this->envSaveError = '';
        } catch (Exception $e) {
            $this->envSaveError = 'Failed to save .env file: '.$e->getMessage();
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

    <flux:heading>Site Settings</flux:heading>
    <flux:text class="mb-8">{{ $this->site->hostname }}</flux:text>

    @if(session('status'))
        <div class="mt-8 mb-4">
            <flux:heading>{{ session('status') }}</flux:heading>
        </div>
    @endif

    <form wire:submit="save" class="max-w-lg space-y-8">
        <flux:textarea
            wire:model="hook_before_updating_repository"
            label="Hook: Before Updating Repository"
            rows="6"
            placeholder="Runs before git pull/clone. Current working directory: repository/"
        />
        <p class="text-sm mt-1">
            This hook runs in the repository directory before pulling changes.
        </p>

        <flux:textarea
            wire:model="hook_after_updating_repository"
            label="Hook: After Updating Repository"
            rows="20"
            placeholder="Runs after git pull/clone. Current working directory: repository/"
        />
        <p class="text-sm mt-1">
            This hook runs after pulling changes. Use this for composer install, npm build, artisan commands, etc.
        </p>

        <flux:button type="submit">Save Hooks</flux:button>
    </form>

    <div class="max-w-4xl mt-16">
        <flux:heading class="mb-2">Environment File</flux:heading>
        <flux:text class="mb-4">Edit the .env file for this site</flux:text>

        @if(!$envLoaded)
            <flux:button wire:click="loadEnv">Load .env File</flux:button>
        @endif

        @if($envLoadError)
            <flux:text color="red" class="mt-2">{{ $envLoadError }}</flux:text>
        @endif

        @if($envLoaded)
            <form wire:submit="saveEnv" class="mt-4">
                <flux:textarea
                    wire:model="envContent"
                    rows="30"
                    placeholder="Environment variables..."
                    monospace
                />
                <p class="text-sm mt-1">
                    Path: {{ $this->site->envPath() }}
                </p>

                @if($envSaveError)
                    <flux:text color="red" class="mt-2">{{ $envSaveError }}</flux:text>
                @endif

                @if(session('envStatus'))
                    <flux:text color="green" class="mt-2">{{ session('envStatus') }}</flux:text>
                @endif

                <flux:button type="submit" class="mt-4">Save .env File</flux:button>
            </form>
        @endif
    </div>
</div>
