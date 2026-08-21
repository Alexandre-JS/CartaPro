<?php

namespace Tests\Unit;

use App\Services\DeployFinalizer;
use Illuminate\Contracts\Console\Kernel;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DeployFinalizerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_runs_only_the_commands_needed_after_copying_the_artifact(): void
    {
        $artisan = Mockery::mock(Kernel::class);
        $artisan->shouldReceive('call')->once()->ordered()->with('optimize:clear', [])->andReturn(0);
        $artisan->shouldReceive('call')->once()->ordered()->with('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ])->andReturn(0);
        $artisan->shouldReceive('call')->once()->ordered()->with('optimize', [
            '--no-interaction' => true,
        ])->andReturn(0);

        (new DeployFinalizer($artisan))->run();

        $this->assertTrue(true);
    }

    public function test_it_stops_when_a_command_fails(): void
    {
        $artisan = Mockery::mock(Kernel::class);
        $artisan->shouldReceive('call')->once()->with('optimize:clear', [])->andReturn(1);

        $this->expectException(RuntimeException::class);

        (new DeployFinalizer($artisan))->run();
    }
}
