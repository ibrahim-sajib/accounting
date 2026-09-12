<?php

namespace Tests\Feature;

use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\Journal;
use App\Domain\Company\Models\Company;
use App\Domain\Document\Models\Attachment;
use App\Domain\Party\Models\Customer;
use App\Domain\Product\Models\Product;
use App\Domain\Rbac\Models\Role;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Tax\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9dCoreTest extends TestCase
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

        Company::query()->create([
            'id' => 2,
            'name' => 'Second Corp',
            'legal_name' => null,
            'country_code' => 'US',
            'currency_code' => 'USD',
            'accounting_basis' => 'accrual',
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Storage::fake('uploads');

        $this->vatRate = TaxRate::query()->where('company_id', 1)->where('name', 'Standard Rate 15%')->firstOrFail();

        $this->customer = Customer::query()->create([
            'company_id' => 1,
            'code' => 'CUS-P9D',
            'name' => 'Document Buyer',
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'opening_balance' => 0,
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->product = Product::query()->create([
            'company_id' => 1,
            'sku' => 'WIDGET-P9D',
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

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create(['company_id' => 1, 'status' => 'active']);
        $user->companyAccess()->create(['company_id' => 1, 'branch_id' => 1, 'is_default' => true]);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->value('id'), ['company_id' => 1]);

        return $user;
    }

    private function draftInvoice(): SalesInvoice
    {
        $this->actingAs($this->admin)
            ->post('/sales/invoices', [
                'customer_id' => $this->customer->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'reference' => 'PO-9D',
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

    private function fakePdf(): UploadedFile
    {
        return UploadedFile::fake()->create('supporting-document.pdf', 200, 'application/pdf');
    }

    public function test_admin_attaches_file_to_invoice_and_show_lists_it(): void
    {
        $invoice = $this->draftInvoice();

        $this->post(route('sales.invoices.attachments.store', $invoice->id), [
            'file' => $this->fakePdf(),
        ])->assertRedirect()->assertSessionHas('success');

        $attachment = Attachment::query()->sole();

        Storage::disk('uploads')->assertExists($attachment->file_path);
        $this->assertSame('supporting-document.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame(1, $attachment->company_id);
        $this->assertSame($invoice->id, $attachment->attachable_id);
        $this->assertSame(SalesInvoice::class, $attachment->attachable_type);

        $this->get(route('sales.invoices.show', $invoice->id))
            ->assertInertia(fn ($page) => $page->component('Sales/Invoices/Show')
                ->has('invoice.attachments', 1)
                ->where('invoice.attachments.0.original_name', 'supporting-document.pdf'));
    }

    public function test_journal_attachment_store_and_inline_preview(): void
    {
        $period = AccountingPeriod::query()
            ->whereHas('fiscalYear', fn ($q) => $q->where('company_id', 1))
            ->firstOrFail();
        $journal = Journal::query()->create([
            'company_id' => 1,
            'period_id' => $period->id,
            'journal_date' => now()->toDateString(),
            'source_type' => 'manual',
            'status' => 'draft',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->post(route('journals.attachments.store', $journal->id), [
            'file' => $this->fakePdf(),
        ])->assertRedirect()->assertSessionHas('success');

        $attachment = Attachment::query()->sole();
        $this->assertSame(Journal::class, $attachment->attachable_type);

        $this->get(route('journals.show', $journal->id))
            ->assertInertia(fn ($page) => $page->component('Journals/Show')
                ->has('journal.attachments', 1));

        $this->get(route('attachments.preview', $attachment->id))
            ->assertOk();
    }

    public function test_download_serves_the_original_filename(): void
    {
        $invoice = $this->draftInvoice();
        $this->post(route('sales.invoices.attachments.store', $invoice->id), [
            'file' => $this->fakePdf(),
        ])->assertRedirect();

        $attachment = Attachment::query()->sole();

        $response = $this->get(route('attachments.download', $attachment->id));
        $response->assertOk();
        $this->assertStringContainsString(
            'supporting-document.pdf',
            $response->headers->get('content-disposition') ?? ''
        );
    }

    public function test_destroy_removes_the_row_and_stored_file(): void
    {
        $invoice = $this->draftInvoice();
        $this->post(route('sales.invoices.attachments.store', $invoice->id), [
            'file' => $this->fakePdf(),
        ])->assertRedirect();

        $attachment = Attachment::query()->sole();
        Storage::disk('uploads')->assertExists($attachment->file_path);

        $this->delete(route('attachments.destroy', $attachment->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        Storage::disk('uploads')->assertMissing($attachment->file_path);
    }

    public function test_viewer_without_update_permission_cannot_attach(): void
    {
        $invoice = $this->draftInvoice();
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)
            ->post(route('sales.invoices.attachments.store', $invoice->id), [
                'file' => $this->fakePdf(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_attachment_cannot_be_uploaded_across_the_active_company(): void
    {
        $foreignInvoice = SalesInvoice::query()->create([
            'company_id' => 2,
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->post(route('sales.invoices.attachments.store', $foreignInvoice->id), [
            'file' => $this->fakePdf(),
        ])->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_attachments_are_scoped_to_the_company_on_download(): void
    {
        $invoice = $this->draftInvoice();
        $this->post(route('sales.invoices.attachments.store', $invoice->id), [
            'file' => $this->fakePdf(),
        ])->assertRedirect();

        $foreign = Attachment::query()->first();
        // shift the active company (a different user from company 2) and try to download
        $other = User::factory()->create(['company_id' => 2, 'status' => 'active']);
        $other->companyAccess()->create(['company_id' => 2, 'branch_id' => 1, 'is_default' => true]);
        $other->roles()->attach(Role::query()->where('slug', 'accountant')->value('id'), ['company_id' => 2]);
        session(['active_company_id' => 2]);

        $this->actingAs($other)
            ->get(route('attachments.download', $foreign->id))
            ->assertForbidden();
    }
}