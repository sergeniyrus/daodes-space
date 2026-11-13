<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthEntryPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_displays_three_separate_entry_paths(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Партнёры DAODES')
            ->assertSee('Сотрудники организаций')
            ->assertSee('Клиенты организаций');
    }

    public function test_existing_partner_login_form_remains_available(): void
    {
        $this->get(route('partner.login'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="password"', false)
            ->assertSee('/img/bottom/login.png', false)
            ->assertSee('/img/bottom/forgot2.png', false)
            ->assertSee('/img/bottom/registrat.png', false);
    }

    public function test_employee_and_customer_login_pages_remain_available(): void
    {
        $this->get(route('employee.login'))
            ->assertOk()
            ->assertSee('name="phone"', false)
            ->assertSee('name="password"', false);

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('Вход клиента Ёлки Иголки!')
            ->assertSee('name="phone"', false)
            ->assertSee('name="password"', false)
            ->assertSee('Первый вход? Регистрация по SMS / Забыли пароль?')
            ->assertDontSee('Получить SMS-код');
    }

    public function test_customer_organization_selector_route_is_registered(): void
    {
        $this->assertTrue(Route::has('customer.organizations.index'));
    }
}
