<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\FiscalYear;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class Phase10aCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Collection $periods;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);
        session(['active_company_id' => 1]);

        $fy = FiscalYear::query()->where('company_id', 1)->orderBy('start_date')->firstOrFail();

        $this->periods = AccountingPeriod::query()
            ->where('fiscal_year_id', $fy->id)
            ->orderBy('start_date')
            ->get();
    }

    private function first(): AccountingPeriod
    {
        return $this->periods->first();
    }

    private function last(): AccountingPeriod
    {
        return $this->periods->last();
    }

    private function accountant(): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        return $user;
    }

    private function viewer(): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        return $user;
    }

    private function draftJournalIn(AccountingPeriod $period): Journal
    {
        $cash = Account::query()->where('company_id', 1)->where('code', '1111')->firstOrFail();
        $income = Account::query()->where('company_id', 1)->where('code', '4111')->firstOrFail();

        $this->actingAs($this->admin)->post('/journals', [
            'journal_date' => $period->start_date->toDateString(),
            'period_id' => $period->id,
            'reference' => 'P10A',
            'description' => 'Phase 10a test',
            'lines' => [
                ['account_id' => $cash->id, 'description' => 'Dr', 'debit' => 1000.00, 'credit' => ''],
                ['account_id' => $income->id, 'description' => 'Cr', 'debit' => '', 'credit' => 1000.00],
            ],
        ])->assertRedirect();

        return Journal::query()->where('company_id', 1)->latest('id')->firstOrFail();
    }

    public function test_close_marks_period_closed_and_blocks_posting_into_it(): void
    {
        $p1 = $this->first();

        $this->post(route('accounting-periods.close', $p1->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $p1->refresh();
        $this->assertSame('closed', $p1->status);
        $this->assertNotNull($p1->closed_at);
        $this->assertSame($this->admin->id, $p1->closed_by);

        // a journal dated inside the now-closed period cannot be posted
        $journal = $this->draftJournalIn($p1);

        $this->post(route('journals.post', $journal->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'not open'));
    }

    public function test_close_enforces_sequential_order(): void
    {
        $this->post(route('accounting-periods.close', $this->last()->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'in sequence'));

        $this->assertNotSame('closed', $this->last()->fresh()->status);
    }

    public function test_close_then_reopen_restores_open_period(): void
    {
        $p1 = $this->first();

        $this->post(route('accounting-periods.close', $p1->id))->assertRedirect();
        $this->post(route('accounting-periods.reopen', $p1->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $p1->refresh();
        $this->assertSame('open', $p1->status);
        $this->assertNull($p1->closed_at);
        $this->assertNull($p1->closed_by);
    }

    public function test_locked_later_period_blocks_reopening_an_earlier_one(): void
    {
        $p1 = $this->first();
        $p2 = $this->periods->get(1);

        $this->post(route('accounting-periods.close', $p1->id))->assertRedirect();
        $this->post(route('accounting-periods.close', $p2->id))->assertRedirect();
        $this->post(route('accounting-periods.lock', $p2->id))->assertRedirect();

        $this->post(route('accounting-periods.reopen', $p1->id))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'locked'));

        $this->assertSame('closed', $p1->fresh()->status);
    }

    public function test_set_active_and_delete_are_rejected_on_closed_periods(): void
    {
        $p1 = $this->first();

        $this->post(route('accounting-periods.close', $p1->id))->assertRedirect();

        $this->post(route('accounting-periods.set-active', $p1->id))
            ->assertStatus(422);

        $this->delete(route('accounting-periods.destroy', $p1->id))
            ->assertStatus(422);

        $this->assertDatabaseHas('accounting_periods', ['id' => $p1->id]);
    }

    public function test_close_is_scoped_to_the_active_company(): void
    {
        Company::query()->create([
            'id' => 2,
            'name' => 'Foreign Corp',
            'country_code' => 'US',
            'currency_code' => 'USD',
            'accounting_basis' => 'accrual',
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $foreignFy = FiscalYear::query()->create([
            'company_id' => 2,
            'name' => 'FY Foreign',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
            'status' => 'open',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $foreignPeriod = AccountingPeriod::query()->create([
            'fiscal_year_id' => $foreignFy->id,
            'name' => 'Jan',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_active' => false,
            'status' => 'open',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->post(route('accounting-periods.close', $foreignPeriod->id))
            ->assertStatus(403);

        $this->assertSame('open', $foreignPeriod->fresh()->status);
    }

    public function test_accountant_can_close_but_viewer_cannot(): void
    {
        $p1 = $this->first();
        $accountant = $this->accountant();

        $this->actingAs($accountant)
            ->post(route('accounting-periods.close', $p1->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('closed', $p1->fresh()->status);

        $p2 = $this->periods->get(1);
        $viewer = $this->viewer();

        $this->actingAs($viewer)
            ->post(route('accounting-periods.close', $p2->id))
            ->assertForbidden();
    }
}