<?php

namespace Tests\Feature;

use App\Domain\Approval\Models\ApprovalRequest;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Party\Models\Customer;
use App\Domain\Product\Models\Product;
use App\Domain\Rbac\Models\Role;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class Phase9cCoreTest extends TestCase
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
        $this->actingAs($this->admin);
        session(['active_company_id' => 1]);

        $this->vatRate = TaxRate::query()->where('company_id', 1)->where('name', 'Standard Rate 15%')->firstOrFail();

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-P9C',
            'name' => 'Notice Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P9C',
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

    private function createWorkflow(int $approverRoleId): ApprovalWorkflow
    {
        return ApprovalWorkflow::query()->create([
            'company_id' => 1,
            'module' => 'sales_invoice',
            'name' => 'Gated sales invoice',
            'min_amount' => 0,
            'max_amount' => null,
            'approver_role_id' => $approverRoleId,
            'approver_user_id' => null,
            'sequence' => 1,
            'is_active' => true,
        ]);
    }

    private function draftInvoice(): SalesInvoice
    {
        $this->actingAs($this->admin)
            ->post('/sales/invoices', [
                'customer_id' => $this->customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'reference' => 'PO-9C',
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

    private function notificationsPage(User $user)
    {
        return $this->actingAs($user)->get(route('notifications.index'));
    }

    public function test_approvers_get_notified_when_a_request_is_submitted(): void
    {
        $accountant = $this->accountant();
        $this->createWorkflow((int) Role::query()->where('slug', 'accountant')->value('id'));

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $accountant->id)
            ->firstOrFail();

        $data = $notification->data;
        $this->assertSame('approval', $data['category']);
        $this->assertSame('A document awaits your approval', $data['title']);
        $this->assertStringContainsString('Sales Invoice', $data['body']);
        $this->assertSame(1, $data['company_id']);
    }

    public function test_admin_gets_notified_when_request_is_approved(): void
    {
        $accountant = $this->accountant();
        $this->createWorkflow((int) Role::query()->where('slug', 'accountant')->value('id'));

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $request = ApprovalRequest::query()->where('approvable_id', $invoice->id)->firstOrFail();

        $this->actingAs($accountant)
            ->post(route('approvals.approve', $request->id))
            ->assertRedirect();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $this->admin->id)
            ->whereNull('read_at')
            ->latest()
            ->firstOrFail();

        $this->assertSame('Approval request approved', $notification->data['title']);
    }

    public function test_admin_gets_notified_when_request_is_rejected(): void
    {
        $accountant = $this->accountant();
        $this->createWorkflow((int) Role::query()->where('slug', 'accountant')->value('id'));

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $request = ApprovalRequest::query()->where('approvable_id', $invoice->id)->firstOrFail();

        $this->actingAs($accountant)
            ->post(route('approvals.reject', $request->id), ['reject_reason' => 'Rejected in review'])
            ->assertRedirect();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $this->admin->id)
            ->latest()
            ->firstOrFail();

        $this->assertSame('Approval request rejected', $notification->data['title']);
        $this->assertStringContainsString('Rejected in review', $notification->data['body']);
    }

    public function test_notifications_page_lists_and_marks_read(): void
    {
        $accountant = $this->accountant();
        $this->createWorkflow((int) Role::query()->where('slug', 'accountant')->value('id'));

        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        $this->notificationsPage($accountant)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->where('unread_count', 1));

        // shared bell badge reflects the unread count for the active company
        $this->actingAs($accountant)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('notifications.unread_count', 1));

        $n = DatabaseNotification::query()->where('notifiable_id', $accountant->id)->firstOrFail();

        $this->actingAs($accountant)
            ->post(route('notifications.read', $n->id))
            ->assertRedirect();

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_is_scoped_to_the_active_company(): void
    {
        $accountant = $this->accountant();
        $this->createWorkflow((int) Role::query()->where('slug', 'accountant')->value('id'));

        // a notification for company 1
        $invoice = $this->draftInvoice();
        $this->actingAs($this->admin)->post(route('sales.invoices.post', $invoice->id))->assertRedirect();

        // and a stray notification for a different company via a raw insert
        DatabaseNotification::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\Domain\Notification\Notifications\DocumentNeedsApproval',
            'notifiable_type' => User::class,
            'notifiable_id' => $accountant->id,
            'data' => json_encode(['company_id' => 99, 'title' => 'Other company', 'body' => 'x']),
        ]);

        $this->actingAs($accountant)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, DatabaseNotification::query()
            ->where('notifiable_id', $accountant->id)
            ->whereNull('read_at')
            ->where('data->company_id', 1)
            ->count());

        // the foreign-company one stays unread
        $this->assertSame(1, DatabaseNotification::query()
            ->where('notifiable_id', $accountant->id)
            ->whereNull('read_at')->count());
    }
}