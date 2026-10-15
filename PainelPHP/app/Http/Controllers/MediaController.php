<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentMode;
use App\Http\Requests\StoreMediaRequest;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Services\DisplayProfileResolver;
use App\Services\MediaProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request, MediaProcessor $processor, DisplayProfileResolver $profileResolver): RedirectResponse
    {
        $profile = $profileResolver->resolve($request->integer('display_profile_id'), $request->validated());
        $targetScope = $request->string('target_scope', 'wall')->toString();
        $files = $request->file('files', []);

        if ($files === []) {
            $files = [$request->file('file')];
        }

        try {
            foreach ($files as $file) {
                $processor->validateUpload($file);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['files' => $exception->getMessage()]);
        }

        foreach ($files as $index => $file) {
            $baseName = $request->string('name')->toString();
            $name = count($files) === 1
                ? $baseName
                : $baseName.' — '.pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $asset = $processor->storeOriginal($file, $name, $request->user()?->id);
            $classification = $processor->classify($asset, $profile, $targetScope);

            if ($classification['suggested_mode'] !== null) {
                $processor->normalize($asset, $profile, AdjustmentMode::from($classification['suggested_mode']), $targetScope);
            }

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'media.uploaded',
                'auditable_type' => MediaAsset::class,
                'auditable_id' => (string) $asset->id,
                'context' => ['classification' => $classification, 'display_profile_id' => $profile->id, 'target_scope' => $targetScope, 'batch_index' => $index],
                'ip_address' => $request->ip(),
            ]);
        }

        return back()
            ->with('success', count($files).' imagem(ns) recebida(s) e inspecionada(s).')
            ->with('profile_id', $profile->id);
    }

    public function preview(Request $request, MediaAsset $mediaAsset): StreamedResponse
    {
        $variantId = $request->integer('variant');
        $variant = $variantId > 0
            ? $mediaAsset->variants()->whereKey($variantId)->firstOrFail()
            : null;
        $path = $variant?->preview_path ?? $mediaAsset->original_path;
        $mimeType = $variant === null ? $mediaAsset->mime_type : 'image/jpeg';

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function destroy(Request $request, MediaAsset $mediaAsset): RedirectResponse
    {
        $mediaAsset->load('variants');

        if ($mediaAsset->variants()->whereHas('campaigns')->exists()) {
            return back()->with('error', 'Esta mídia não pode ser apagada porque já está vinculada a uma campanha.');
        }

        $paths = $mediaAsset->variants
            ->flatMap(fn ($variant): array => [$variant->path, $variant->preview_path])
            ->push($mediaAsset->original_path)
            ->filter()
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($request, $mediaAsset): void {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'media.deleted',
                'auditable_type' => MediaAsset::class,
                'auditable_id' => (string) $mediaAsset->id,
                'context' => ['name' => $mediaAsset->name],
                'ip_address' => $request->ip(),
            ]);

            $mediaAsset->delete();
        });

        Storage::disk('local')->delete($paths);

        return back()->with('success', 'Mídia apagada da biblioteca.');
    }
}
