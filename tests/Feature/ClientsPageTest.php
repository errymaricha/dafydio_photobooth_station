<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('clients.index'))
            ->assertRedirect(route('login'));

        $this->get(route('clients.show', ['customerWhatsapp' => '6281234567890']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_clients_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/Index'));

        $this->get(route('clients.show', ['customerWhatsapp' => '6281234567890']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('clients/Show')
                ->where('customerWhatsapp', '6281234567890'));
    }
}
