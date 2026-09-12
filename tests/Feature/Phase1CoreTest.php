<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Company\Models\Company;
use App\Models\User;
use App\Support\Enums\PeriodStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/companies')->assertRedirect('/login');
    }

    public function test_super_admin_can_view_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('summary'));
    }

    public function test_dashboard_has_active_company_context(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('current_company.id', 1)
                ->where('current_company.name', 'Demo Business Ltd'));
    }

    public function test_non_super_admin_cannot_manage_users(): void
    {
        $unionUser = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($unionUser)->get('/users')->assertForbidden();
    }

    public function test_super_admin_can_view_companies_listing(): void
    {
        $this->actingAs($this->admin)
            ->get('/companies')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Companies/Index')
                ->has('companies.data')
                ->has('companies.links')
                ->has('companies.total'));
    }

    public function test_paginated_and_period_pages_render(): void
    {
        $this->actingAs($this->admin)
            ->get('/branches')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Branches/Index')->has('branches.total'));

        $this->actingAs($this->admin)
            ->get('/accounting-periods')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Periods/Index')->has('periods'));
    }

    public function test_creating_fiscal_year_generates_monthly_periods(): void
    {
        $this->actingAs($this->admin)
            ->post('/fiscal-years', [
                'name' => '2027-2028',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
                'is_active' => 1,
            ])
            ->assertRedirect(route('fiscal-years.index'));

        $fiscalYear = FiscalYear::query()
            ->where('company_id', 1)
            ->where('name', '2027-2028')
            ->firstOrFail();

        $this->assertSame(12, $fiscalYear->periods()->count());

        $periods = $fiscalYear->periods()->orderBy('start_date')->get();

        $this->assertSame('2027-01-01', $periods->first()->start_date->toDateString());
        $this->assertSame('2027-12-31', $periods->last()->end_date->toDateString());
        $this->assertTrue($fiscalYear->periods()->where('status', PeriodStatus::Open->value)->count() === 12);
    }

    public function test_only_one_active_fiscal_year_per_company(): void
    {
        $this->actingAs($this->admin)->post('/fiscal-years', [
            'name' => '2026-2027',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => 1,
        ])->assertRedirect();

        $fiscalYears = FiscalYear::query()->where('company_id', 1)->get();

        $this->assertSame(1, $fiscalYears->where('is_active', true)->count());
    }

    public function test_period_can_be_closed_locked_and_reopened(): void
    {
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post("/accounting-periods/{$period->id}/close")
            ->assertSessionHasNoErrors();

        $this->assertSame(PeriodStatus::Closed->value, $period->fresh()->status);

        // a closed (not locked) period may be reopened
        $this->actingAs($this->admin)
            ->post("/accounting-periods/{$period->id}/reopen")
            ->assertSessionHasNoErrors();

        $this->assertSame(PeriodStatus::Open->value, $period->fresh()->status);

        $this->actingAs($this->admin)
            ->post("/accounting-periods/{$period->id}/lock")
            ->assertSessionHasNoErrors();

        $this->assertSame(PeriodStatus::Locked->value, $period->fresh()->status);

        // locked periods are permanent — reopening is refused (flash error)
        $this->actingAs($this->admin)
            ->post("/accounting-periods/{$period->id}/reopen")
            ->assertSessionHas('error');

        $this->assertSame(PeriodStatus::Locked->value, $period->fresh()->status);
    }

    public function test_company_switch_updates_active_context(): void
    {
        $second = Company::query()->create([
            'name' => 'Second Holding Ltd',
            'legal_name' => 'Second Holding Ltd',
            'country_code' => 'BD',
            'currency_code' => 'BDT',
            'accounting_basis' => 'accrual',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->post('/companies/switch', ['company_id' => $second->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($second->id, (int) session('active_company_id'));
    }

    public function test_permission_middleware_blocks_non_super_admin(): void
    {
        // company-admin role has '*' permissions, but non-super-admin cannot reach super-admin-only company pages.
        $role = \App\Domain\Rbac\Models\Role::query()->where('slug', 'company-admin')->firstOrFail();

        $manager = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);
        $manager->roles()->attach($role, [
            'company_id' => 1,
            'created_at' => now(),
        ]);

        // Companies index is super-admin only despite having company.view granted.
        $this->actingAs($manager)->get('/companies')->assertForbidden();
    }
}