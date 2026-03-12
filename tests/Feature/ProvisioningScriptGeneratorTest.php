<?php

use App\Models\Server;
use App\Models\User;
use App\Services\ProvisioningScriptGenerator;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('swap size for 512MB RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 512,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(512);
});

test('swap size for 1024MB RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(512);
});

test('swap size for 2048MB RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(1024);
});

test('swap size for 4096MB RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 4096,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(2048);
});

test('swap size for 8192MB RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 8192,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(3072);
});

test('swap size for 16384MB RAM defaults to 4096', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 16384,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swapInMegabytes())->toBe(4096);
});

test('swappiness for small servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 512,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swappiness())->toBe(10);
});

test('swappiness for medium servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swappiness())->toBe(20);
});

test('swappiness for large servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 16384,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->swappiness())->toBe(50);
});

test('mysql max connections for small servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 512,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->mysqlMaxConnections())->toBe(75);
});

test('mysql max connections for 2GB servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->mysqlMaxConnections())->toBe(150);
});

test('mysql max connections for large servers', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 16384,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->mysqlMaxConnections())->toBe(1000);
});

test('mysql innodb buffer pool is 50 percent of RAM', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->mysqlInnodbBufferPoolSize())->toBe(1024);
});

test('mysql innodb buffer pool has minimum of 128', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 64,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->mysqlInnodbBufferPoolSize())->toBe(128);
});

test('php pool max children for 1GB server', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->maxChildrenPhpPool())->toBe(5);
});

test('php pool max children for 4GB server', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 4096,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    expect($generator->maxChildrenPhpPool())->toBe(14);
});

test('generate returns bash script', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deployuser',
    ]);

    $generator = new ProvisioningScriptGenerator($server, 'ssh-ed25519 AAAA root@key');

    $script = $generator->generate();

    expect($script)
        ->toContain('Server Provisioning Script')
        ->toContain('Production Server')
        ->toContain('SITES_USER="deployuser"')
        ->toContain('MEMORY_MB=2048');
});

test('generate includes root ssh key', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, 'ssh-ed25519 AAAAROOT root@key');

    $script = $generator->generate();

    expect($script)->toContain('ssh-ed25519 AAAAROOT root@key');
});

test('root ssh key is authorized for both root and sites user', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
    ]);

    $generator = new ProvisioningScriptGenerator($server, 'ssh-ed25519 AAAAROOT root@key');

    $script = $generator->generate();

    expect($script)
        ->toContain('/root/.ssh/authorized_keys')
        ->toContain("cat <<'ROOTKEY' >> /home/\$SITES_USER/.ssh/authorized_keys\nssh-ed25519 AAAAROOT root@key\nROOTKEY");
});

test('generate includes sites user ssh keys', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'authorized_keys' => "ssh-ed25519 AAAAUSER1 user1@key\nssh-ed25519 AAAAUSER2 user2@key",
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    $script = $generator->generate();

    expect($script)
        ->toContain('ssh-ed25519 AAAAUSER1 user1@key')
        ->toContain('ssh-ed25519 AAAAUSER2 user2@key');
});

test('generate handles empty ssh keys gracefully', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'authorized_keys' => null,
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    $script = $generator->generate();

    expect($script)->toContain('Server Provisioning Script');
});

test('generate creates mysql user and database with sites_user variable', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'customuser',
    ]);

    $generator = new ProvisioningScriptGenerator($server, '');

    $script = $generator->generate();

    expect($script)
        ->toContain('SITES_USER="customuser"')
        ->toContain("CREATE USER '\$SITES_USER'@'localhost'")
        ->toContain("GRANT ALL PRIVILEGES ON *.* TO '\$SITES_USER'@'localhost'")
        ->toContain('CREATE DATABASE $SITES_USER');
});
