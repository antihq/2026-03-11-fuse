<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionServer;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class SshSetupController extends Controller
{
    public function show(string $token): Response
    {
        $server = Server::where('ssh_setup_token', $token)->first();

        if (! $server) {
            abort(410, 'This SSH setup link has expired or already been used.');
        }

        if (empty($server->user->ssh_public_key)) {
            abort(400, 'No SSH public key configured for this account.');
        }

        $callbackUrl = URL::signedRoute(
            'ssh-setup.callback',
            ['token' => $token],
            now()->addHours(24)
        );

        $script = view('scripts.add-ssh-key', [
            'sshPublicKey' => $server->user->ssh_public_key,
            'callbackUrl' => $callbackUrl,
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="add-ssh-key.sh"',
        ]);
    }

    public function callback(Request $request, string $token): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid callback signature.');
        }

        $server = Server::where('ssh_setup_token', $token)->first();

        if (! $server) {
            return response('Token already used or expired', 410);
        }

        $server->update([
            'ssh_setup_token' => null,
            'ssh_ready_at' => now(),
            'provision_status' => 'ssh_setup',
        ]);

        ProvisionServer::dispatch($server->id);

        return response('SSH setup confirmed', 200);
    }
}
