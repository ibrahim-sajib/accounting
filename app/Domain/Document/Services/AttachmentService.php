<?php

namespace App\Domain\Document\Services;

use App\Domain\Document\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class AttachmentService
{
    public static function store(mixed $attachable, \Illuminate\Http\UploadedFile $file, int $companyId, int $uploadedBy): Attachment
    {
        $dir = implode('/', [$companyId, (new \ReflectionClass($attachable))->getShortName(), $attachable->id]);

        $path = Storage::disk('uploads')->putFile($dir, $file);

        return Attachment::query()->create([
            'company_id' => $companyId,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public static function canUpload(User $user, mixed $attachable): bool
    {
        $permission = Attachment::ALLOWED_TYPES[$attachable::class] ?? null;
        if (! $permission) {
            return false;
        }

        if ((int) $attachable->company_id !== (int) session('active_company_id', 0)) {
            return false;
        }

        return $user->is_super_admin || $user->hasPermission($permission);
    }

    /**
     * Read/gate check for a single attachment (download/preview/destroy).
     * Super admins bypass; otherwise the attachment must belong to the active
     * company and the user must hold the owning module's view permission.
     */
    public static function canAccess(User $user, Attachment $attachment): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ((int) $attachment->company_id !== (int) session('active_company_id', 0)) {
            return false;
        }

        $permission = Attachment::ALLOWED_TYPES[$attachment->attachable_type] ?? null;

        return $permission !== null && $user->hasPermission($permission);
    }

    public static function destroy(Attachment $attachment): void
    {
        if ($attachment->file_path) {
            Storage::disk('uploads')->delete($attachment->file_path);
        }
        $attachment->delete();
    }

    /**
     * Inertia-friendly rows for list rendering.
     */
    public static function serialize(Collection $attachments): array
    {
        return $attachments->map(fn (Attachment $a) => [
            'id' => $a->id,
            'original_name' => $a->original_name,
            'mime_type' => $a->mime_type,
            'file_size' => (int) $a->file_size,
            'created_at' => $a->created_at->toDateTimeString(),
            'uploaded_by' => $a->uploader?->name ?? '—',
        ])->values()->all();
    }
}