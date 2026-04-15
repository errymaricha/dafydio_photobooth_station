<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SessionsShowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('sessions.show', ['id' => 'demo']));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_sessions_show(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('sessions.show', ['id' => 'demo']));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sessions/Show'),
            );
    }
}
