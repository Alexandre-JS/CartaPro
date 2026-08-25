<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_backend_package_and_environment_use_prontovia_identity(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertSame('prontovia/backend', $composer['name']);
        $this->assertSame('Backend da plataforma educativa ProntoVia.', $composer['description']);
        $this->assertStringContainsString('APP_NAME="ProntoVia API"', $environment);
        $this->assertStringContainsString('DB_DATABASE=prontovia', $environment);
    }

    public function test_legacy_brand_assets_are_not_shipped(): void
    {
        $this->assertFileDoesNotExist(public_path('images/logo/icon CartaPro.png'));
        $this->assertFileDoesNotExist(public_path('images/logo/Logo cartaPro.png'));
        $this->assertFileExists(public_path('images/prontovia/prontovia.png'));
        $this->assertFileExists(public_path('images/prontovia/iconProntovia.png'));
    }

    public function test_operational_commands_use_new_names_and_keep_known_aliases(): void
    {
        $this->artisan('list')->expectsOutputToContain('prontovia:expire-unlocks')->assertSuccessful();
        $this->artisan('list')->expectsOutputToContain('prontovia:backfill-breakdown')->assertSuccessful();

        // Os aliases evitam quebrar cron jobs existentes durante a transição.
        $this->artisan('cartapro:expire-unlocks')->assertSuccessful();
        $this->artisan('cartapro:backfill-breakdown', ['--dry-run' => true])->assertSuccessful();
    }
}
