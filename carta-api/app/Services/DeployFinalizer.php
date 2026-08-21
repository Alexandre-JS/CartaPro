<?php

namespace App\Services;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

class DeployFinalizer
{
    public function __construct(private readonly Kernel $artisan) {}

    public function run(): void
    {
        $this->call('optimize:clear');
        $this->call('migrate', ['--force' => true, '--no-interaction' => true]);
        $this->call('optimize', ['--no-interaction' => true]);
    }

    private function call(string $command, array $parameters = []): void
    {
        $exitCode = $this->artisan->call($command, $parameters);

        if ($exitCode !== 0) {
            throw new RuntimeException("O comando {$command} terminou com código {$exitCode}.");
        }
    }
}
