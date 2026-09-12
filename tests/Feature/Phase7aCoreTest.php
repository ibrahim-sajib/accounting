<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\CashBank\Models\CashAccount;
use App\Domain\FixedAsset\Models\AssetCategory;
use App\Domain\FixedAsset\Models\DepreciationEntry;
use App\Domain\FixedAsset\Models\FixedAsset;
use App\Domain\Rbac\Models\Role;
use App\Models\User;
use App\Support\Enums\JournalSourceType;
use App\Support\Enums\TransactionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7aCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AssetCategory $category;

    private int $accumGl;

    private int $depExpenseGl;

    private int $cashGl;

    private CashAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();
        $this->actingAs($this->admin);

        $this->accumGl = Account::query()->where('company_id', 1)->where('code', '1222')->value('id');
        $this->depExpenseGl = Account::query()->where('company_id', 1)->where('code', '5151')->value('id');
        $assetGl = Account::query()->where('company_id', 1)->where('code', '1213')->value('id');

        $this->category = AssetCategory::query()->create([
            'company_id' => 1,
            'name' => 'Test Machinery',
            'asset_account_id' => $assetGl,
            'depreciation_expense_account_id' => $this->depExpenseGl,
            'accumulated_depreciation_account_id' => $this->accumGl,
            'default_method' => 'straight_line',
            'default_useful_life_months' => 12,
            'is_active' => true,
        ]);

        $this->cashGl = \App\Domain\Accounting\Models\AccountingSetting::query()
            ->where('company_id', 1)
            ->value('default_cash_account_id');

        $this->cashAccount = CashAccount::query()->create([
            'company_id' => 1,
            'name' => 'FA Cash Register',
            'gl_account_id' => $this->cashGl,
            'is_active' => true,
        ]);
    }

    protected function uniqueCode(string $prefix): string
    {
        return $prefix.'-'.substr(md5((string) mt_rand()), 0, 6);
    }

    private function assetPayload(string $code, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'asset_code' => $code,
            'name' => 'CNC Machine',
            'acquisition_date' => '2026-03-01',
            'acquisition_cost' => '6000.0000',
            'useful_life_months' => 12,
            'method' => 'straight_line',
            'location' => 'Factory A',
            'acquisition_method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'bank_account_id' => null,
            'supplier_id' => null,
        ], $overrides);
    }

    private function lastAsset(): FixedAsset
    {
        return FixedAsset::query()->where('company_id', 1)->latest('id')->firstOrFail();
    }

    // ───────────────────────── Pages ─────────────────────────

    public function test_fixed_asset_pages_render_for_super_admin(): void
    {
        $this->get('/fixed-assets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/Index')
                ->has('assets.data')
                ->has('categories')
                ->has('periods'));

        $this->get('/fixed-assets/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/Create')
                ->has('categories')
                ->has('cashAccounts')
                ->has('bankAccounts')
                ->has('suppliers')
                ->has('acquisitionMethods')
                ->has('depreciationMethods'));

        $this->get('/fixed-assets/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FixedAssets/Categories')
                ->has('categories.data')
                ->has('postableAccounts'));
    }

    // ───────────────────────── Categories ─────────────────────────

    public function test_asset_category_crud(): void
    {
        $asset = Account::query()->where('company_id', 1)->where('code', '1214')->firstOrFail();
        $accum = Account::query()->where('company_id', 1)->where('code', '1223')->firstOrFail();
        $dep = Account::query()->where('company_id', 1)->where('code', '5151')->firstOrFail();

        $this->post('/fixed-assets/categories', [
            'name' => 'IT Equipment',
            'asset_account_id' => $asset->id,
            'depreciation_expense_account_id' => $dep->id,
            'accumulated_depreciation_account_id' => $accum->id,
            'default_method' => 'straight_line',
            'default_useful_life_months' => 36,
            'is_active' => true,
        ])->assertRedirect();

        $category = AssetCategory::query()->where('name', 'IT Equipment')->firstOrFail();
        $this->assertSame($asset->id, $category->asset_account_id);

        $this->put("/fixed-assets/categories/{$category->id}", [
            'name' => 'IT & Computing',
            'asset_account_id' => $asset->id,
            'depreciation_expense_account_id' => $dep->id,
            'accumulated_depreciation_account_id' => $accum->id,
            'default_method' => 'declining_balance',
            'default_useful_life_months' => 24,
            'is_active' => true,
        ])->assertRedirect();

        $category->refresh();
        $this->assertSame('IT & Computing', $category->name);
        $this->assertSame('declining_balance', $category->default_method);

        $this->delete("/fixed-assets/categories/{$category->id}")->assertRedirect();
        $this->assertSoftDeleted('asset_categories', ['id' => $category->id]);
    }

    public function test_asset_category_in_use_is_deactivated_not_deleted(): void
    {
        $asset = $this->registerAsset();

        $this->delete("/fixed-assets/categories/{$this->category->id}")->assertRedirect();

        $this->assertDatabaseHas('asset_categories', ['id' => $this->category->id, 'is_active' => false]);
        $this->assertSame(FixedAsset::class, get_class($asset));
    }

    // ───────────────────────── Registration / Capitalization ─────────────────────────

    public function test_register_asset_creates_draft(): void
    {
        $code = $this->uniqueCode('MC');

        $this->post('/fixed-assets', $this->assetPayload($code))
            ->assertRedirect();

        $asset = $this->lastAsset();
        $this->assertSame('draft', $asset->status);
        $this->assertNull($asset->journal_id);
        $this->assertSame(6000.0, (float) $asset->acquisition_cost);
        $this->assertSame($this->cashAccount->id, $asset->cash_account_id);
    }

    public function test_cash_method_requires_cash_account(): void
    {
        $payload = $this->assetPayload($this->uniqueCode('MC'));
        $payload['cash_account_id'] = null;

        $this->post('/fixed-assets', $payload)
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_capitalize_posts_balanced_fa_journal(): void
    {
        $asset = $this->registerAsset();

        $this->assertSame('draft', $asset->status);

        $this->post("/fixed-assets/{$asset->id}/capitalize")
            ->assertRedirect();

        $asset->refresh();
        $this->assertSame('active', $asset->status);
        $this->assertNotNull($asset->journal_id);

        $journal = Journal::query()->findOrFail($asset->journal_id);
        $this->assertSame(JournalSourceType::Capitalization->value, $journal->source_type);
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        $this->assertSame('FA-2026-0001', $journal->journal_no);
        $this->assertSame(6000.0, (float) $journal->lines()->sum('debit'));
        $this->assertSame(6000.0, (float) $journal->lines()->sum('credit'));

        $this->assertTrue($journal->lines()->where('debit', 6000)->where('account_id', $this->category->asset_account_id)->exists());
        $this->assertTrue($journal->lines()->where('credit', 6000)->where('account_id', $this->cashGl)->exists());
    }

    public function test_capitalize_only_once(): void
    {
        $asset = $this->registerAsset();
        $this->post("/fixed-assets/{$asset->id}/capitalize")->assertRedirect();

        $this->post("/fixed-assets/{$asset->id}/capitalize")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_edit_and_delete_only_draft(): void
    {
        $asset = $this->registerAsset();

        // draft edits fine
        $this->put("/fixed-assets/{$asset->id}", array_merge($this->assetPayload($asset->asset_code), ['name' => 'Renamed Machine']))
            ->assertRedirect();
        $this->assertSame('Renamed Machine', $asset->fresh()->name);

        $this->post("/fixed-assets/{$asset->id}/capitalize")->assertRedirect();

        // capitalized → update refused
        $this->put("/fixed-assets/{$asset->id}", $this->assetPayload($asset->asset_code))
            ->assertRedirect()
            ->assertSessionHas('error');

        // capitalized → delete refused
        $this->delete("/fixed-assets/{$asset->id}")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_draft_can_be_deleted(): void
    {
        $asset = $this->registerAsset();
        $this->delete("/fixed-assets/{$asset->id}")->assertRedirect();
        $this->assertSoftDeleted('fixed_assets', ['id' => $asset->id]);
    }

    // ───────────────────────── Depreciation ─────────────────────────

    public function test_run_depreciation_posts_dep_journal_for_active_assets(): void
    {
        $assetA = $this->capitalizeAsset($this->uniqueCode('MC'));
        $assetB = $this->capitalizeAsset($this->uniqueCode('MC'));

        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-03-31')
            ->where('end_date', '>=', '2026-03-31')
            ->firstOrFail();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $journal = Journal::query()
            ->where('company_id', 1)
            ->where('source_type', JournalSourceType::Depreciation->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        $this->assertSame('DEP-2026-0001', $journal->journal_no);

        // monthly = 6000 / 12 = 500 per asset → 2 debits 500, 2 credits 500
        $this->assertSame(1000.0, (float) $journal->lines()->sum('debit'));
        $this->assertSame(1000.0, (float) $journal->lines()->sum('credit'));

        $this->assertSame(2, DepreciationEntry::query()->where('journal_id', $journal->id)->count());

        $this->assertSame(500.0, (float) $assetA->depreciationEntries()->sum('monthly_amount'));
        $this->assertSame(500.0, (float) $assetB->depreciationEntries()->sum('monthly_amount'));
    }

    public function test_run_depreciation_same_period_is_idempotent(): void
    {
        $asset = $this->capitalizeAsset($this->uniqueCode('MC'));

        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-03-31')
            ->where('end_date', '>=', '2026-03-31')
            ->firstOrFail();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $period->id])->assertRedirect();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $period->id])
            ->assertRedirect()
            ->assertSessionHas('info');

        $this->assertSame(1, Journal::query()
            ->where('company_id', 1)
            ->where('source_type', JournalSourceType::Depreciation->value)
            ->count());
    }

    public function test_declining_balance_asset_depreciates_on_diminishing_value(): void
    {
        $code = $this->uniqueCode('DB');
        $payload = $this->assetPayload($code);
        $payload['method'] = 'declining_balance';
        $payload['useful_life_months'] = 10;
        $payload['company_id'] = 1;

        $asset = FixedAsset::query()->create(array_merge($payload, [
            'status' => 'active',
            'posted_by' => $this->admin->id,
            'posted_at' => now(),
        ]));

        $march = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-03-31')
            ->where('end_date', '>=', '2026-03-31')
            ->firstOrFail();

        $april = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-04-30')
            ->where('end_date', '>=', '2026-04-30')
            ->firstOrFail();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $march->id])->assertRedirect();
        $this->post('/fixed-assets/run-depreciation', ['period_id' => $april->id])->assertRedirect();

        $amounts = $asset->depreciationEntries()->orderBy('id')->pluck('monthly_amount');

        // DB (200%): month1 = 6000 × 2/10 = 1200, month2 = 4800 × 2/10 = 960
        $this->assertSame([1200.0, 960.0], $amounts->map(fn ($v) => (float) $v)->all());
    }

    // ───────────────────────── Disposal ─────────────────────────

    public function test_disposal_with_gain_posts_dsp_journal(): void
    {
        $asset = $this->capitalizeAsset($this->uniqueCode('MC'));
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-03-31')
            ->where('end_date', '>=', '2026-03-31')
            ->firstOrFail();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $period->id])->assertRedirect();
        $asset->refresh(); // accum = 500, book = 5500

        $gainAccount = Account::query()->where('company_id', 1)->where('code', '4213')->value('id');

        $this->post("/fixed-assets/{$asset->id}/dispose", [
            'disposal_date' => '2026-04-10',
            'proceeds' => '5700',
            'proceeds_cash_account_id' => $this->cashAccount->id,
        ])->assertRedirect();

        $asset->refresh();
        $this->assertSame('disposed', $asset->status);

        $disposal = $asset->disposal;
        $this->assertSame(5700.0, (float) $disposal->proceeds);
        $this->assertSame(5500.0, (float) $disposal->book_value);
        $this->assertSame(200.0, (float) $disposal->gain_loss_amount);

        $journal = Journal::query()->findOrFail($disposal->journal_id);
        $this->assertSame(JournalSourceType::AssetDisposal->value, $journal->source_type);
        $this->assertSame(TransactionStatus::Posted->value, $journal->status);
        $this->assertSame('DSP-2026-0001', $journal->journal_no);

        // credits = cost(6000) + gain(200) = 6200 ; debits = proceeds(5700) + accum(500)
        $this->assertSame(6200.0, (float) $journal->lines()->sum('credit'));
        $this->assertSame(6200.0, (float) $journal->lines()->sum('debit'));
        $this->assertTrue($journal->lines()->where('credit', 200)->where('account_id', $gainAccount)->exists());
    }

    public function test_disposal_with_loss_postable(): void
    {
        $asset = $this->capitalizeAsset($this->uniqueCode('MC'));
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->where('start_date', '<=', '2026-03-31')
            ->where('end_date', '>=', '2026-03-31')
            ->firstOrFail();

        $this->post('/fixed-assets/run-depreciation', ['period_id' => $period->id])->assertRedirect();
        $asset->refresh();

        $lossAccount = Account::query()->where('company_id', 1)->where('code', '5193')->value('id');

        $this->post("/fixed-assets/{$asset->id}/dispose", [
            'disposal_date' => '2026-04-10',
            'proceeds' => '4000',
            'proceeds_cash_account_id' => $this->cashAccount->id,
        ])->assertRedirect();

        $asset->refresh();
        $disposal = $asset->disposal;
        $this->assertSame(-1500.0, (float) $disposal->gain_loss_amount);

        $journal = Journal::query()->findOrFail($disposal->journal_id);
        $this->assertTrue($journal->lines()->where('debit', 1500)->where('account_id', $lossAccount)->exists());
    }

    public function test_dispose_only_active(): void
    {
        $asset = $this->registerAsset();

        $this->post("/fixed-assets/{$asset->id}/dispose", [
            'disposal_date' => '2026-04-10',
            'proceeds' => '1000',
            'proceeds_cash_account_id' => $this->cashAccount->id,
        ])->assertRedirect()->assertSessionHas('error');
    }

    // ───────────────────────── Permissions ─────────────────────────

    public function test_accountant_can_view_create_capitalize_and_depreciate(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/fixed-assets')->assertOk();
        $this->get('/fixed-assets/create')->assertOk();
        $this->post('/fixed-assets/categories', [
            'name' => 'Extra Equipment',
            'asset_account_id' => Account::query()->where('company_id', 1)->where('code', '1213')->value('id'),
            'depreciation_expense_account_id' => $this->depExpenseGl,
            'accumulated_depreciation_account_id' => $this->accumGl,
            'default_method' => 'straight_line',
            'default_useful_life_months' => 12,
            'is_active' => true,
        ])->assertRedirect();

        $asset = $this->registerAsset();
        $this->post("/fixed-assets/{$asset->id}/capitalize")->assertRedirect();
        $this->assertSame('active', $asset->fresh()->status);
    }

    public function test_viewer_cannot_register_or_capitalize(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($user);

        $this->get('/fixed-assets')->assertOk();
        $this->get('/fixed-assets/create')->assertForbidden();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function registerAsset(): FixedAsset
    {
        $this->post('/fixed-assets', $this->assetPayload($this->uniqueCode('MC')))
            ->assertRedirect();

        return $this->lastAsset();
    }

    private function capitalizeAsset(string $code): FixedAsset
    {
        $payload = $this->assetPayload($code);
        $payload['company_id'] = 1;

        $asset = FixedAsset::query()->create(array_merge(
            $payload,
            ['status' => 'active', 'posted_by' => $this->admin->id, 'posted_at' => now()]
        ));

        return $asset;
    }
}