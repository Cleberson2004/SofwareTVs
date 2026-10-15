<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MediaVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MediaApprovalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, MediaVariant $mediaVariant): RedirectResponse
    {
        $mediaVariant->update([
            'approved_at' => now(),
            'approved_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'media.approved',
            'auditable_type' => $mediaVariant::class,
            'auditable_id' => (string) $mediaVariant->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Prévia aprovada para publicação.');
    }
}
