<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Account;
use App\Domain\Company\Models\Company;
use App\Domain\Tax\Models\TaxRate;
use App\Domain\Tax\Models\TaxType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2CoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
    }

    private function account(string $code): Account
    {
        return Account::query()->where('company_id', 1)->where('code', $code)->firstOrFail();
    }

    // ───────────────────────── Chart of Accounts ─────────────────────────

    public function test_chart_of_accounts_seeded_for_company(): void
    {
        $accounts = Account::query()->where('company_id', 1)->get();

        $this->assertGreaterThan(20, $accounts->count());

        foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $type) {
            $this->assertTrue(
                $accounts->contains('type', $type),
                "Expected at least one {$type} account in the seeded chart."
            );
        }

        // Codes must be unique per company: the row count equals distinct codes.
        $this->assertSame(
            $accounts->count(),
            $accounts->pluck('code')->unique()->count()
        );

        // Every leaf is postable; nobody that has children is postable.
        $this->assertTrue(
            $accounts->where('level', 0)->every(fn (Account $a) => ! $a->is_postable)
        );

        $leaf = $this->account('1111');
        $this->assertTrue($leaf->is_postable);

        $parent = $this->account('1110');
        $this->assertFalse($parent->is_postable);
    }

    public function test_super_admin_can_view_chart_of_accounts(): void
    {
        $this->actingAs($this->admin)
            ->get('/accounts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounts/Index')
                ->has('accounts')
                ->has('parentOptions')
                ->has('typeOptions'));
    }

    public function test_account_can_be_created_with_parent_inheriting_type_and_level(): void
    {
        $cash = $this->account('1111');

        $this->assertTrue($cash->is_postable);

        $this->actingAs($this->admin)->post('/accounts', [
            'code' => '1114',
            'name' => 'Cash at Bank - DBBL',
            'type' => 'asset',
            'parent_id' => $cash->id,
            'is_active' => true,
        ])->assertRedirect(route('accounts.index'));

        $child = Account::query()->where('company_id', 1)->where('code', '1114')->firstOrFail();

        $this->assertSame($cash->id, $child->parent_id);
        $this->assertSame('asset', $child->type);
        $this->assertSame($cash->level + 1, $child->level);
        $this->assertSame('debit', $child->normal_balance);
        $this->assertTrue($child->is_postable);

        // Adding a child revokes postability from the previous leaf.
        $this->assertFalse($cash->fresh()->is_postable);
    }

    public function test_account_code_is_unique_per_company(): void
    {
        $this->actingAs($this->admin)->post('/accounts', [
            'code' => '9001',
            'name' => 'Test Control',
            'type' => 'asset',
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/accounts', [
            'code' => '9001',
            'name' => 'Duplicate Control',
            'type' => 'asset',
        ])->assertSessionHasErrors('code');

        $this->assertSame(1, Account::query()->where('code', '9001')->count());
    }

    public function test_account_cannot_be_moved_under_its_own_descendant(): void
    {
        $cash = $this->account('1111');

        $child = Account::query()->create([
            'company_id' => 1,
            'code' => '1199',
            'name' => 'Child of Cash',
            'type' => 'asset',
            'parent_id' => $cash->id,
            'level' => $cash->level + 1,
            'normal_balance' => 'debit',
        ]);

        // Move Cash under its own child → rejected.
        $this->actingAs($this->admin)
            ->put("/accounts/{$cash->id}", [
                'code' => $cash->code,
                'name' => $cash->name,
                'type' => $cash->type,
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_parent_and_system_accounts_cannot_be_deleted(): void
    {
        // Root accounts have children → protected.
        $root = $this->account('1000');

        $this->actingAs($this->admin)
            ->delete("/accounts/{$root->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($root->fresh());
    }

    public function test_account_referenced_by_settings_or_tax_cannot_be_deleted(): void
    {
        $sales = $this->account('4111'); // default_sales_account_id

        $this->actingAs($this->admin)
            ->delete("/accounts/{$sales->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($sales->fresh());
    }

    // ───────────────────────── Tax / VAT ─────────────────────────

    public function test_tax_seeder_created_types_and_rates(): void
    {
        $vat = TaxType::query()->where('company_id', 1)->where('name', 'VAT')->firstOrFail();

        $this->assertTrue(
            $vat->rates()->where('name', 'Standard Rate 15%')->exists()
        );

        $standard = $vat->rates()->where('name', 'Standard Rate 15%')->firstOrFail();
        $this->assertSame('15', (string) $standard->rate_percent);
        $this->assertTrue($standard->is_inclusive);
        $this->assertSame($this->account('1151')->id, $standard->input_account_id);
        $this->assertSame($this->account('2121')->id, $standard->output_account_id);

        $this->assertTrue(
            TaxType::query()->where('company_id', 1)->where('name', 'Withholding Tax')->exists()
        );
    }

    public function test_super_admin_can_view_tax_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/tax')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tax/Index')
                ->has('taxTypes')
                ->has('accountOptions'));
    }

    public function test_tax_type_crud_via_http(): void
    {
        $this->actingAs($this->admin)->post('/tax', ['name' => 'Excise Duty'])
            ->assertRedirect(route('tax.index'));

        $excise = TaxType::query()->where('company_id', 1)->where('name', 'Excise Duty')->firstOrFail();

        $this->actingAs($this->admin)->put("/tax/{$excise->id}", ['name' => 'Excise Duty (Updated)'])
            ->assertRedirect();

        $this->assertSame('Excise Duty (Updated)', $excise->fresh()->name);
    }

    public function test_tax_rate_can_be_added_with_linked_accounts(): void
    {
        $vat = TaxType::query()->where('company_id', 1)->where('name', 'VAT')->firstOrFail();

        $this->actingAs($this->admin)->post("/tax/{$vat->id}/rates", [
            'name' => 'Special Rate 10%',
            'rate_percent' => 10,
            'is_inclusive' => 0,
            'input_account_id' => $this->account('1151')->id,
            'output_account_id' => $this->account('2121')->id,
            'effective_date' => '2026-01-01',
        ])->assertRedirect(route('tax.index'));

        $rate = $vat->rates()->where('name', 'Special Rate 10%')->firstOrFail();
        $this->assertSame('10', (string) $rate->rate_percent);
        $this->assertSame($this->account('1151')->id, $rate->input_account_id);
    }

    public function test_tax_type_with_rates_cannot_be_deleted(): void
    {
        $vat = TaxType::query()->where('company_id', 1)->where('name', 'VAT')->firstOrFail();

        $this->assertTrue($vat->rates()->exists());

        $this->actingAs($this->admin)
            ->delete("/tax/{$vat->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotNull($vat->fresh());
    }

    // ───────────────────────── Accounting Settings ─────────────────────────

    public function test_accounting_settings_page_renders_with_options(): void
    {
        $this->actingAs($this->admin)
            ->get('/accounting-settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AccountingSettings/Index')
                ->has('setting.default_sales_account_id')
                ->has('accountOptions'));
    }

    public function test_accounting_settings_update_persists_and_validates(): void
    {
        $this->actingAs($this->admin)->put('/accounting-settings', [
            'default_sales_account_id' => $this->account('4112')->id,
            'default_purchase_account_id' => $this->account('5211')->id,
            'default_inventory_account_id' => $this->account('1131')->id,
            'default_ar_account_id' => $this->account('1121')->id,
            'default_ap_account_id' => $this->account('2111')->id,
            'default_cash_account_id' => $this->account('1111')->id,
            'default_bank_account_id' => $this->account('1112')->id,
            'default_tax_input_account_id' => $this->account('1151')->id,
            'default_tax_output_account_id' => $this->account('2121')->id,
            'voucher_numbering' => json_encode(['sales' => 'SL-{sequence:5}']),
        ])->assertRedirect(route('accounting-settings.index'));

        $setting = AccountingSetting::query()->where('company_id', 1)->firstOrFail();

        $this->assertSame($this->account('4112')->id, $setting->default_sales_account_id);
        $this->assertSame('SL-{sequence:5}', $setting->voucher_numbering['sales']);
    }

    // ───────────────────────── Provisioning the next company ─────────────────────────

    public function test_new_company_is_provisioned_with_phase2_defaults(): void
    {
        $this->actingAs($this->admin)->post('/companies', [
            'name' => 'Phase Two Industries',
            'legal_name' => 'Phase Two Industries Ltd',
            'country_code' => 'BD',
            'currency_code' => 'BDT',
            'accounting_basis' => 'accrual',
            'status' => 'active',
        ])->assertRedirect(route('companies.index'));

        $second = Company::query()->where('name', 'Phase Two Industries')->firstOrFail();

        $this->assertGreaterThan(20, Account::query()->where('company_id', $second->id)->count());
        $this->assertTrue(TaxType::query()->where('company_id', $second->id)->exists());
        $this->assertTrue(AccountingSetting::query()->where('company_id', $second->id)->exists());

        // The new company's VAT rate must point at its OWN accounts, not company 1's.
        $vat = TaxType::query()->where('company_id', $second->id)->where('name', 'VAT')->firstOrFail();
        $standard = $vat->rates()->firstOrFail();
        $this->assertNotSame($this->account('2121')->id, $standard->output_account_id);
    }

    public function test_non_super_admin_without_permission_is_blocked_from_accounts(): void
    {
        $manager = User::factory()->create([
            'company_id' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($manager)->get('/accounts')->assertForbidden();
    }
}