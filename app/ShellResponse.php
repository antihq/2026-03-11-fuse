<?php

namespace App;

class ShellResponse
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public bool $timedOut = false
    ) {}
}
