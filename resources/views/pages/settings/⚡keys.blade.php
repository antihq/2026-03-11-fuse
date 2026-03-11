<?php

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team Keys')] class extends Component
{
    #[Locked]
    public int $teamId;

    public function mount(): void
    {
        $team = Auth::user()->team;

        if (! $team) {
            abort(404, 'No team found for this user.');
        }

        $this->teamId = $team->id;
    }

    #[Computed]
    public function team(): Team
    {
        return Team::findOrFail($this->teamId);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Team Keys') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Team Keys')" :subheading="__('SSH keys for your team')">
        <div class="my-6 w-full space-y-6">
            <div>
                <flux:heading size="md" class="mb-2">{{ __('Public Key') }}</flux:heading>
                <pre class="whitespace-pre-wrap break-all text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded">{{ $this->team->ssh_public_key }}</pre>
            </div>

            <div>
                <flux:heading size="md" class="mb-2">{{ __('Private Key') }}</flux:heading>
                <pre class="whitespace-pre-wrap break-all text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded">{{ $this->team->ssh_private_key }}</pre>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
