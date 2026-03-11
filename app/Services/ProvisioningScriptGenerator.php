<?php

namespace App\Services;

use App\Models\Server;

class ProvisioningScriptGenerator
{
    public function __construct(
        public Server $server,
        public string $rootSshKey
    ) {}

    public function swapInMegabytes(): int
    {
        return match (true) {
            $this->server->ram_mb <= 1024 => 512,
            $this->server->ram_mb <= 2048 => 1024,
            $this->server->ram_mb <= 4096 => 2048,
            $this->server->ram_mb <= 8192 => 3072,
            default => 4096
        };
    }

    public function swappiness(): int
    {
        return match (true) {
            $this->server->ram_mb <= 1024 => 10,
            $this->server->ram_mb <= 2048 => 20,
            $this->server->ram_mb <= 4096 => 35,
            default => 50
        };
    }

    public function mysqlMaxConnections(): int
    {
        return match (true) {
            $this->server->ram_mb <= 1024 => 75,
            $this->server->ram_mb <= 2048 => 150,
            $this->server->ram_mb <= 4096 => 300,
            $this->server->ram_mb <= 8192 => 500,
            default => 1000
        };
    }

    public function mysqlInnodbBufferPoolSize(): int
    {
        $memoryForMysql = (int) ($this->server->ram_mb * 0.5);

        return max(128, $memoryForMysql);
    }

    public function maxChildrenPhpPool(): int
    {
        $gigabytes = max(1, (int) floor($this->server->ram_mb / 1024) - 1);

        return (int) ceil($gigabytes * 5 * 0.9);
    }

    public function generate(): string
    {
        $script = view('scripts.provision-server', [
            'serverName' => $this->server->name,
            'sitesUser' => $this->server->sites_user,
            'rootSshKey' => $this->rootSshKey,
            'sitesUserSshKeys' => $this->server->authorized_keys ?? '',
            'memory' => $this->server->ram_mb,
            'swapInMegabytes' => $this->swapInMegabytes(),
            'swappiness' => $this->swappiness(),
            'mysqlMaxConnections' => $this->mysqlMaxConnections(),
            'mysqlInnodbBufferPoolSize' => $this->mysqlInnodbBufferPoolSize(),
            'maxChildrenPhpPool' => $this->maxChildrenPhpPool(),
            'mysqlRootPassword' => $this->server->mysql_root_password,
            'deployUserPassword' => $this->server->deploy_user_password,
        ])->render();

        return $this->formatScript($script);
    }

    protected function formatScript(string $script): string
    {
        $script = preg_replace('/^(\s*\n){2,}/m', "\n", trim($script));

        return $script."\n";
    }
}
