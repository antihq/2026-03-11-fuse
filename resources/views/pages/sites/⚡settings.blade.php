<?php

use App\Helpers\RemoteFile;
use App\Jobs\InstallSiteNightwatch;
use App\Jobs\InstallSiteQueue;
use App\Jobs\InstallSiteScheduler;
use App\Jobs\UninstallSiteNightwatch;
use App\Jobs\UninstallSiteQueue;
use App\Jobs\UninstallSiteScheduler;
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

    #[Validate('nullable|integer|min:1|max:10')]
    public string $queue_processes = '1';

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
        $this->queue_processes = (string) $siteModel->queue_processes;
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
            'queue_processes' => (int) $this->queue_processes,
        ]);

        session()->flash('status', 'Deployment hooks updated successfully.');

        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function enableQueue(): void
    {
        $site = $this->site();

        $site->update([
            'queue_enabled' => true,
            'queue_processes' => (int) $this->queue_processes,
        ]);

        dispatch(new InstallSiteQueue($site->id));

        session()->flash('queueStatus', 'Queue workers enabled successfully.');
        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function disableQueue(): void
    {
        $site = $this->site();

        $site->update(['queue_enabled' => false]);

        dispatch(new UninstallSiteQueue($site->id));

        session()->flash('queueStatus', 'Queue workers disabled successfully.');
        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function enableNightwatch(): void
    {
        $site = $this->site();

        $site->update(['nightwatch_enabled' => true]);

        dispatch(new InstallSiteNightwatch($site->id));

        session()->flash('nightwatchStatus', 'Nightwatch agent enabled successfully.');
        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function disableNightwatch(): void
    {
        $site = $this->site();

        $site->update(['nightwatch_enabled' => false]);

        dispatch(new UninstallSiteNightwatch($site->id));

        session()->flash('nightwatchStatus', 'Nightwatch agent disabled successfully.');
        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function enableScheduler(): void
    {
        $site = $this->site();

        $site->update(['scheduler_enabled' => true]);

        dispatch(new InstallSiteScheduler($site->id));

        session()->flash('schedulerStatus', 'Laravel scheduler enabled successfully.');
        $this->redirect(route('servers.sites.settings', [$this->server, $this->site]), navigate: true);
    }

    public function disableScheduler(): void
    {
        $site = $this->site();

        $site->update(['scheduler_enabled' => false]);

        dispatch(new UninstallSiteScheduler($site->id));

        session()->flash('schedulerStatus', 'Laravel scheduler disabled successfully.');
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

    <div class="max-w-lg mt-16 space-y-8">
        <flux:heading class="mb-2">Queue Workers</flux:heading>
        <flux:text class="mb-4">Manage Laravel queue workers for this site using Supervisor</flux:text>

        @if(session('queueStatus'))
            <flux:text color="green" class="mb-4">{{ session('queueStatus') }}</flux:text>
        @endif

        @if($this->site->queue_enabled)
            <div class="flex items-center gap-4 mb-4">
                <flux:text color="green">Queue workers are enabled</flux:text>
                <flux:button wire:click="disableQueue" variant="danger">Disable Queues</flux:button>
            </div>
        @else
            <div class="flex items-center gap-4 mb-4">
                <flux:text>Queue workers are disabled</flux:text>
                <flux:button wire:click="enableQueue">Enable Queues</flux:button>
            </div>
        @endif

        <flux:input
            wire:model="queue_processes"
            label="Number of Worker Processes"
            type="number"
            min="1"
            max="10"
        />
        <p class="text-sm mt-1">
            Number of queue worker processes to run (1-10). Requires restarting queues to take effect.
        </p>
    </div>

    <div class="max-w-lg mt-16 space-y-8">
        <flux:heading class="mb-2">Nightwatch Agent</flux:heading>
        <flux:text class="mb-4">Manage Laravel Nightwatch agent for this site using Supervisor</flux:text>

        @if(session('nightwatchStatus'))
            <flux:text color="green" class="mb-4">{{ session('nightwatchStatus') }}</flux:text>
        @endif

        @if($this->site->nightwatch_enabled)
            <div class="flex items-center gap-4">
                <flux:text color="green">Nightwatch agent is enabled</flux:text>
                <flux:button wire:click="disableNightwatch" variant="danger">Disable Nightwatch</flux:button>
            </div>
        @else
            <div class="flex items-center gap-4">
                <flux:text>Nightwatch agent is disabled</flux:text>
                <flux:button wire:click="enableNightwatch">Enable Nightwatch</flux:button>
            </div>
        @endif
    </div>

    <div class="max-w-lg mt-16 space-y-8">
        <flux:heading class="mb-2">Laravel Scheduler</flux:heading>
        <flux:text class="mb-4">Manage Laravel scheduler for this site using Cron</flux:text>

        @if(session('schedulerStatus'))
            <flux:text color="green" class="mb-4">{{ session('schedulerStatus') }}</flux:text>
        @endif

        @if($this->site->scheduler_enabled)
            <div class="flex items-center gap-4">
                <flux:text color="green">Laravel scheduler is enabled</flux:text>
                <flux:button wire:click="disableScheduler" variant="danger">Disable Scheduler</flux:button>
            </div>
        @else
            <div class="flex items-center gap-4">
                <flux:text>Laravel scheduler is disabled</flux:text>
                <flux:button wire:click="enableScheduler">Enable Scheduler</flux:button>
            </div>
        @endif
    </div>

    <div class="max-w-4xl mt-16">
        <flux:heading class="mb-2">Database Credentials</flux:heading>
        <flux:text class="mb-4">MySQL database credentials for this site</flux:text>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
            <div x-data="{ showPassword: false }" class="space-y-4">
                <div class="flex items-center gap-2">
                    <flux:text class="font-semibold">Database Name:</flux:text>
                    <flux:text>{{ $this->site->database_name ?? 'Not configured' }}</flux:text>
                    @if($this->site->database_name)
                        <div x-data="{ copied: false }">
                            <flux:button
                                size="sm"
                                x-on:click="navigator.clipboard.writeText('{{ $this->site->database_name }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            >
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </flux:button>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:text class="font-semibold">Database Username:</flux:text>
                    <flux:text>{{ $this->site->database_user ?? 'Not configured' }}</flux:text>
                    @if($this->site->database_user)
                        <div x-data="{ copied: false }">
                            <flux:button
                                size="sm"
                                x-on:click="navigator.clipboard.writeText('{{ $this->site->database_user }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            >
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </flux:button>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <flux:text class="font-semibold">Database Password:</flux:text>
                    <flux:text x-text="showPassword ? '{{ $this->site->database_password ?? 'Not configured' }}' : '••••••••'"></flux:text>
                    @if($this->site->database_password)
                        <button @click="showPassword = !showPassword" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                            <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                        </button>
                        <div x-data="{ copied: false }">
                            <flux:button
                                size="sm"
                                x-on:click="navigator.clipboard.writeText('{{ $this->site->database_password }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            >
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </flux:button>
                        </div>
                    @endif
                </div>

                @if($this->site->database_created_at)
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Database created at: {{ $this->site->database_created_at->format('Y-m-d H:i:s') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

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
