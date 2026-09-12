<?php

namespace App\Domain\Document\Http\Controllers;

use App\Domain\Accounting\Models\Journal;
use App\Domain\Document\Http\Requests\AttachmentStoreRequest;
use App\Domain\Document\Models\Attachment;
use App\Domain\Document\Services\AttachmentService;
use App\Domain\Expense\Models\Expense;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\SalesInvoice;
use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController
{
    public function storeInvoice(AttachmentStoreRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        return $this->storeFor($request, $invoice, 'sales.update');
    }

    public function storeBill(AttachmentStoreRequest $request, PurchaseBill $bill): RedirectResponse
    {
        return $this->storeFor($request, $bill, 'purchase.update');
    }

    public function storeExpense(AttachmentStoreRequest $request, Expense $expense): RedirectResponse
    {
        return $this->storeFor($request, $expense, 'expense.update');
    }

    public function storeJournal(AttachmentStoreRequest $request, Journal $journal): RedirectResponse
    {
        return $this->storeFor($request, $journal, 'journal.update');
    }

    private function storeFor(AttachmentStoreRequest $request, mixed $attachable, string $permission): RedirectResponse
    {
        $this->authorizeUpload($attachable, $permission);

        $attachment = AttachmentService::store(
            $attachable,
            $request->file('file'),
            $attachable->company_id,
            $request->user()->id
        );

        AuditLogger::log('document', 'attach', null, $attachment->id, [], [
            'original_name' => $attachment->original_name,
            'attachable_type' => $attachable::class,
            'attachable_id' => $attachable->id,
        ], $attachable->company_id);

        return redirect()->back()->with('success', 'File attached.');
    }

    public function preview(Request $request, Attachment $attachment): StreamedResponse|BinaryFileResponse
    {
        $this->authorizeAccess($attachment);

        return Storage::disk('uploads')->response($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse|BinaryFileResponse
    {
        $this->authorizeAccess($attachment);

        return Storage::disk('uploads')->download($attachment->file_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        $this->authorizeUpload($attachment->attachable, Attachment::ALLOWED_TYPES[$attachment->attachable_type] ?? null);

        AttachmentService::destroy($attachment);

        AuditLogger::log('document', 'detach', null, $attachment->id, ['original_name' => $attachment->original_name], [], $attachment->company_id);

        return redirect()->back()->with('success', 'File detached.');
    }

    private function authorizeUpload(mixed $attachable, ?string $permission): void
    {
        if (! AttachmentService::canUpload(request()->user(), $attachable)) {
            abort(403, 'You do not have permission to attach files to this record.');
        }
    }

    private function authorizeAccess(Attachment $attachment): void
    {
        if (! AttachmentService::canAccess(request()->user(), $attachment)) {
            abort(403, 'You cannot access this file.');
        }
    }
}