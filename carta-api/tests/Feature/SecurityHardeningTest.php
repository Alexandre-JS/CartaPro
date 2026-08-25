<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_responses_receive_baseline_security_headers(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_https_responses_enable_hsts(): void
    {
        $this->get('https://localhost/up')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_production_environment_example_has_secure_session_defaults(): void
    {
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('SESSION_ENCRYPT=true', $environment);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $environment);
        $this->assertStringContainsString('SESSION_HTTP_ONLY=true', $environment);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $environment);
    }
}
