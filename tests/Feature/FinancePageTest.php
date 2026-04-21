<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login_page(): void
    {
        $response = $this->get(route('finance.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_page_for_finance_transactions(): void
    {
        $response = $this->get(route('finance.transactions'));

        $response->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_to_login_page_for_finance_expenses(): void
    {
        $response = $this->get(route('finance.expenses'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_finance_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('finance.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Index'),
            );
    }

    public function test_authenticated_users_can_visit_finance_transactions_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('finance.transactions'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Transactions'),
            );
    }

    public function test_authenticated_users_can_visit_finance_expenses_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('finance.expenses'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Expenses'),
            );
    }
}
