<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrintersIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('printers.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_printers_index(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('printers.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('printers/Index'),
            );
    }
}
