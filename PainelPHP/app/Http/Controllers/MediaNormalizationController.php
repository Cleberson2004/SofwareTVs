<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentMode;
use App\Http\Requests\NormalizeMediaRequest;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Services\DisplayProfileResolver;
use App\Services\MediaProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MediaNormalizationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(NormalizeMediaRequest $request, MediaAsset $mediaAsset, MediaProcessor $processor, DisplayProfileResolver $profileResolver): RedirectResponse
    {
        $profile = $profileResolver->resolve($request->integer('display_profile_id'), $request->validated());

        try {
            $variant = $processor->normalize(
                $mediaAsset,
                $profile,
                AdjustmentMode::from($request->string('adjustment_mode')->toString()),
                $request->string('target_scope', 'wall')->toString(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['adjustment_mode' => $exception->getMessage()]);
        }

        if ($request->boolean('approve') && $variant->approved_at === null) {
            $variant->update([
                'approved_at' => now(),
                'approved_by' => $request->user()?->id,
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'media.normalized',
            'auditable_type' => $variant::class,
            'auditable_id' => (string) $variant->id,
            'context' => ['mode' => $variant->adjustment_mode, 'target_scope' => $request->string('target_scope', 'wall')->toString()],
            'ip_address' => $request->ip(),
        ]);

        return back()->with([
            'success' => $request->boolean('approve') ? 'Prévia gerada e aprovada.' : 'Ajuste aplicado na prévia.',
            'variant_id' => $variant->id,
        ]);
    }
}
