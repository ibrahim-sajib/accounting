<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingSetting;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Approval\Models\ApprovalRequest;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Party\Models\Customer;
use App\Domain\Product\Models\Product;
use App\Domain\Rbac\Models\Role;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9aCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TaxRate $vatRate;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->vatRate = TaxRate::query()->where('company_id', 1)->where('name', 'Standard Rate 15%')->firstOrFail();

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-P9A',
            'name' => 'Approval Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P9A',
            'name' => 'Widget',
            'type' => 'product',
            'purchase_price' => 400,
            'sales_price' => 500,
            'tax_rate_id' => $this->vatRate->id,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function accountant(): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 1]);

        return $user;
    }

    private function companyAdmin(): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', 'company-admin')->value('id'), ['company_id' => 1]);

        return $user;
    }

    private function roleId(string $slug): int
    {
        return (int) Role::query()->where('slug', $slug)->value('id');
    }

    private function createWorkflow(array $overrides = []): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()->create(array_merge([
            'company_id' => 1,
            'module' => 'sales_invoice',
            'name' => 'Large invoices require sign-off',
            'min_amount' => 0,
            'max_amount' => null,
            'approver_role_id' => $this->roleId('accountant'),
            'approver_user_id' => null,
            'sequence' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function draftInvoice(): SalesInvoice
    {
        $this->actingAs($this->admin)
            ->post('/sales/invoices', [
                'customer_id' => $this->customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'reference' => 'PO-901',
                'notes' => null,
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'description' => '',
                        'quantity' => '2',
                        'unit_price' => '500',
                        'discount_amount' => '0',
                        'tax_rate_id' => $this->vatRate->id,
                    ],
                ],
            ])
            ->assertRedirect();

        return SalesInvoice::query()->where('company_id', 1)->latest('id')->firstOrFail();
    }

    private function requestFor(SalesInvoice $invoice): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('approvable_type', SalesInvoice::class)
            ->where('approvable_id', $invoice->id)
            ->latest('id')
            ->firstOrFail();
    }

    // ───────────────────────── Pages & permissions ─────────────────────────

    public function test_approval_and_workflow_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Approval/Index')
                ->has('pending')
                ->has('decided')
                ->has('pending_count'));

        $this->actingAs($this->admin)
            ->get(route('approval-workflows.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Approval/Workflows')
                ->has('workflows')
                ->has('modules')
                ->has('roles'));
    }

    public function test_workflow_configuration_requires_configure_permission(): void
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);

        $this->actingAs($user)
            ->post(route('approval-workflows.store'), [
                'name' => 'Nope',
                'module' => 'sales_invoice',
                'min_amount' => '0',
                'max_amount' => '',
                'approver_role_id' => null,
                'sequence' => '1',
                'is_active' => 1,
            ])
            ->assertForbidden();

        // accountant may view but not configure
        $this->actingAs($this->accountant())
            ->post(route('approval-workflows.store'), [
                'name' => 'Nope',
                'module' => 'expense',
                'min_amount' => '0',
                'max_amount' => '',
                'approver_role_id' => null,
                'sequence' => '1',
                'is_active' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('approval-workflows.store'), [
                'name' => 'Large invoices',
                'module' => 'sales_invoice',
                'min_amount' => '0',
                'max_amount' => '',
                'approver_role_id' => null,
                'sequence' => '1',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('approval_workflows', ['name' => 'Large invoices', 'company_id' => 1]);
    }

    // ───────────────────────── The gating engine ─────────────────────────

    public function test_posting_a_gated_invoice_is_blocked_and_creates_a_pending_request(): void
    {
        $this->createWorkflow();

        $invoice = $this->draftInvoice();

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertDatabaseMissing('journals', [
            'company_id' => 1,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
        ]);

        $request = $this->requestFor($invoice);
        $this->assertSame('pending', $request->status);
        $this->assertSame('sales_invoice', $request->module);
        $this->assertEqualsWithDelta(1150.0, (float) $request->amount, 0.0001);
        $this->assertSame(1, $request->total_steps);
    }

    public function test_a_pending_request_stays_blocking_until_approved(): void
    {
        $this->createWorkflow();

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        // a second submit reuses the SAME pending request — no journal, still draft
        $this->assertSame(1, ApprovalRequest::query()->where('approvable_id', $invoice->id)->count());
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_approved_request_allows_posting(): void
    {
        $this->createWorkflow();

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $accountant = $this->accountant();
        $request = $this->requestFor($invoice);
        $this->assertTrue($request->isPending());

        $this->actingAs($accountant)
            ->post(route('approvals.approve', $request->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertDatabaseHas('journals', [
            'company_id' => 1,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
        ]);
    }

    public function test_rejected_request_blocks_posting_and_can_be_resubmitted(): void
    {
        $this->createWorkflow();

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $accountant = $this->accountant();
        $request = $this->requestFor($invoice);

        $this->actingAs($accountant)
            ->post(route('approvals.reject', $request->id), ['reject_reason' => 'Budget not approved'])
            ->assertRedirect();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Budget not approved', $request->fresh()->reject_reason);

        // posting stays blocked, but a NEW request is created on the next submit
        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        $latest = $this->requestFor($invoice);
        $this->assertNotSame($request->id, $latest->id);
        $this->assertSame('pending', $latest->status);
        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_multi_step_chain_requires_every_approver(): void
    {
        $this->createWorkflow(['sequence' => 1, 'approver_role_id' => $this->roleId('accountant')]);
        $this->createWorkflow(['sequence' => 2, 'approver_role_id' => $this->roleId('company-admin'), 'name' => 'Final sign-off']);

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $request = $this->requestFor($invoice);
        $this->assertSame(2, $request->total_steps);
        $this->assertSame(1, $request->current_step);

        // step 1 approved by the accountant — still pending, step advances
        $this->actingAs($this->accountant())->post(route('approvals.approve', $request->id))->assertRedirect();
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertSame(2, $request->fresh()->current_step);

        // step 2 approved by the company admin — fully approved
        $this->actingAs($this->companyAdmin())->post(route('approvals.approve', $request->id))->assertRedirect();
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();
        $this->assertSame('posted', $invoice->fresh()->status);
    }

    public function test_amount_below_threshold_posts_without_approval(): void
    {
        $this->createWorkflow(['min_amount' => 5000]);

        $invoice = $this->draftInvoice();

        $this->actingAs($this->admin)
            ->post(route('sales.invoices.post', $invoice->id))
            ->assertRedirect();

        $this->assertSame('posted', $invoice->fresh()->status);
        $this->assertDatabaseMissing('approval_requests', ['approvable_id' => $invoice->id]);
    }

    public function test_viewer_can_view_but_not_approve(): void
    {
        $this->createWorkflow(['approver_role_id' => $this->roleId('accountant')]);

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();
        $request = $this->requestFor($invoice);

        $viewer = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $viewer->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $viewer->roles()->attach(Role::query()->where('slug', 'viewer')->value('id'), ['company_id' => 1]);

        $this->actingAs($viewer)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Approval/Index')
                ->where('pending_count', 1));

        $this->actingAs($viewer)
            ->post(route('approvals.approve', $request->id))
            ->assertForbidden();
    }
}