<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('SSH Keys')] class extends Component {}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('SSH Keys') }}</flux:heading>

    <x-pages::settings.layout :heading="__('SSH Keys')" :subheading="__('SSH keys for your account')">
        <div class="my-6 w-full space-y-6">
            <div>
                <flux:heading size="md" class="mb-2">{{ __('Public Key') }}</flux:heading>
                <pre class="whitespace-pre-wrap break-all text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded">{{ Auth::user()->ssh_public_key }}</pre>
            </div>

            <div>
                <flux:heading size="md" class="mb-2">{{ __('Private Key') }}</flux:heading>
                <pre class="whitespace-pre-wrap break-all text-sm bg-zinc-100 dark:bg-zinc-800 p-3 rounded">{{ Auth::user()->ssh_private_key }}</pre>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
