<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_editor_pages(): void
    {
        $paths = [
            '/dashboard',
            '/printers',
            '/print-queue',
            '/sessions',
            '/sessions/example-session',
            '/vouchers',
        ];

        foreach ($paths as $path) {
            $response = $this->get($path);

            $response->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_users_can_visit_phase_one_editor_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $paths = [
            '/dashboard',
            '/printers',
            '/print-queue',
            '/sessions',
            '/sessions/example-session',
            '/vouchers',
        ];

        foreach ($paths as $path) {
            $response = $this->get($path);

            $response->assertOk();
        }
    }
}
