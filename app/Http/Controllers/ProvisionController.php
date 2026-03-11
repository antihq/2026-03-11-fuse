<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ProvisioningScriptGenerator;
use Illuminate\Http\Response;

class ProvisionController extends Controller
{
    public function show(string $token): Response
    {
        $server = Server::where('provision_token', $token)->first();

        if (! $server) {
            abort(410, 'This provisioning link has expired or already been used.');
        }

        $server->update(['provision_token' => null]);

        $rootSshKey = $server->team->ssh_public_key ?? '';

        $generator = new ProvisioningScriptGenerator($server, $rootSshKey);
        $script = $generator->generate();

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="provision.sh"',
        ]);
    }
}
