<?php

namespace App\Services;

use App\Enums\AdjustmentMode;
use App\Models\DisplayProfile;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class MediaProcessor
{
    public function validateUpload(UploadedFile $file): void
    {
        $this->imageInfo($file->get());
    }

    public function storeOriginal(UploadedFile $file, string $name, ?int $userId): MediaAsset
    {
        $contents = $file->get();
        $imageInfo = $this->imageInfo($contents);
        $sha256 = hash('sha256', $contents);
        $existingAsset = MediaAsset::query()
            ->where('sha256', $sha256)
            ->whereDoesntHave('variants', fn ($query) => $query->where('variant_type', 'screen_composition'))
            ->first();

        if ($existingAsset !== null) {
            return $existingAsset;
        }

        $extension = match ($imageInfo['mime']) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
        };
        $path = 'media/originals/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Não foi possível armazenar o arquivo original.');
        }

        return MediaAsset::create([
            'name' => $name,
            'original_path' => $path,
            'original_width' => $imageInfo[0],
            'original_height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'sha256' => $sha256,
            'processing_status' => 'inspected',
            'created_by' => $userId,
        ]);
    }

    /** @return array{classification: string, suggested_mode: string|null, source_ratio: float, target_ratio: float, target_width: int, target_height: int, target_scope: string} */
    public function classify(MediaAsset $asset, DisplayProfile $profile, string $targetScope = 'wall'): array
    {
        [$targetWidth, $targetHeight] = $this->targetDimensions($profile, $targetScope);
        $differenceWidth = abs($asset->original_width - $targetWidth);
        $differenceHeight = abs($asset->original_height - $targetHeight);
        $tolerance = config('tv_agent.dimension_tolerance');
        $sourceRatio = $asset->original_width / $asset->original_height;
        $targetRatio = $targetWidth / $targetHeight;

        if ($differenceWidth === 0 && $differenceHeight === 0) {
            $classification = 'exact';
            $suggestedMode = AdjustmentMode::EdgeAdjust->value;
        } elseif ($differenceWidth <= $tolerance && $differenceHeight <= $tolerance) {
            $classification = 'minor_difference';
            $suggestedMode = AdjustmentMode::EdgeAdjust->value;
        } elseif (abs($sourceRatio - $targetRatio) < 0.0001) {
            $classification = 'same_ratio';
            $suggestedMode = AdjustmentMode::Fit->value;
        } else {
            $classification = 'different_ratio';
            $suggestedMode = null;
        }

        return [
            'classification' => $classification,
            'suggested_mode' => $suggestedMode,
            'source_ratio' => $sourceRatio,
            'target_ratio' => $targetRatio,
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
            'target_scope' => $targetScope,
        ];
    }

    public function normalize(MediaAsset $asset, DisplayProfile $profile, AdjustmentMode $mode, string $targetScope = 'wall'): MediaVariant
    {
        if ($mode === AdjustmentMode::Reject) {
            throw new InvalidArgumentException('Selecione um modo de adaptação para gerar a variante.');
        }

        [$targetWidth, $targetHeight] = $this->targetDimensions($profile, $targetScope);
        $variantType = $targetScope === 'screen' ? 'screen' : 'normalized';
        $existingVariant = $asset->variants()
            ->where('display_profile_id', $profile->id)
            ->where('variant_type', $variantType)
            ->where('width', $targetWidth)
            ->where('height', $targetHeight)
            ->where('adjustment_mode', $mode->value)
            ->latest()
            ->first();

        if ($existingVariant !== null) {
            return $existingVariant;
        }

        $sourceBytes = Storage::disk('local')->get($asset->original_path);
        $source = @imagecreatefromstring($sourceBytes);

        if ($source === false) {
            throw new InvalidArgumentException('O arquivo original não pôde ser decodificado.');
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Não foi possível reservar memória para processar a imagem.');
        }

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));
        [$sourceX, $sourceY, $sourceWidth, $sourceHeight, $destinationX, $destinationY, $destinationWidth, $destinationHeight] = $this->geometry($asset, $mode, $targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, $destinationX, $destinationY, $sourceX, $sourceY, $destinationWidth, $destinationHeight, $sourceWidth, $sourceHeight);

        $normalizedBytes = $this->pngBytes($canvas);
        $variantId = (string) Str::uuid();
        $path = 'media/normalized/'.$variantId.'.png';
        $previewPath = 'media/previews/'.$variantId.'.jpg';
        Storage::disk('local')->put($path, $normalizedBytes);
        Storage::disk('local')->put($previewPath, $this->previewBytes($canvas));
        imagedestroy($source);
        imagedestroy($canvas);

        $asset->update(['processing_status' => 'normalized']);

        return MediaVariant::create([
            'media_asset_id' => $asset->id,
            'display_profile_id' => $profile->id,
            'variant_type' => $variantType,
            'path' => $path,
            'preview_path' => $previewPath,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'adjustment_mode' => $mode->value,
            'processing_details' => [
                'source_width' => $asset->original_width,
                'source_height' => $asset->original_height,
                'target_width' => $targetWidth,
                'target_height' => $targetHeight,
                'target_scope' => $targetScope,
                'geometry' => compact('sourceX', 'sourceY', 'sourceWidth', 'sourceHeight', 'destinationX', 'destinationY', 'destinationWidth', 'destinationHeight'),
            ],
            'sha256' => hash('sha256', $normalizedBytes),
        ]);
    }

    /**
     * @param  array<int, MediaAsset>  $assetByScreen
     * @param  array<int, MediaVariant>  $variantByScreen
     */
    public function composeForScreens(DisplayProfile $profile, array $assetByScreen, string $name, ?int $userId, array $variantByScreen = []): MediaVariant
    {
        $canvas = imagecreatetruecolor($profile->total_width, $profile->total_height);

        if ($canvas === false) {
            throw new RuntimeException('Não foi possível reservar memória para criar a composição.');
        }

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 0, 0, 0));
        $placements = [];

        foreach ($assetByScreen as $screen => $asset) {
            $screenVariant = $variantByScreen[$screen] ?? null;
            $sourcePath = $screenVariant?->path ?? $asset->original_path;
            $source = @imagecreatefromstring(Storage::disk('local')->get($sourcePath));

            if ($source === false) {
                imagedestroy($canvas);

                throw new InvalidArgumentException("A imagem da TV {$screen} não pôde ser decodificada.");
            }

            $column = ($screen - 1) % $profile->columns;
            $row = intdiv($screen - 1, $profile->columns);
            $destinationX = $column * ($profile->display_width - $profile->overlap_x);
            $destinationY = $row * ($profile->display_height - $profile->overlap_y);

            if ($screenVariant !== null) {
                imagecopyresampled(
                    $canvas,
                    $source,
                    $destinationX,
                    $destinationY,
                    0,
                    0,
                    $profile->display_width,
                    $profile->display_height,
                    imagesx($source),
                    imagesy($source),
                );
            } else {
                $copyWidth = min(imagesx($source), $profile->display_width);
                $copyHeight = min(imagesy($source), $profile->display_height);
                $sourceX = max(0, intdiv(imagesx($source) - $copyWidth, 2));
                $sourceY = max(0, intdiv(imagesy($source) - $copyHeight, 2));
                $centeredX = $destinationX + max(0, intdiv($profile->display_width - $copyWidth, 2));
                $centeredY = $destinationY + max(0, intdiv($profile->display_height - $copyHeight, 2));

                imagecopy($canvas, $source, $centeredX, $centeredY, $sourceX, $sourceY, $copyWidth, $copyHeight);
            }

            imagedestroy($source);
            $placements[] = [
                'screen' => $screen,
                'asset_id' => $asset->id,
                'variant_id' => $screenVariant?->id,
                'adjustment_mode' => $screenVariant?->adjustment_mode,
            ];
        }

        $bytes = $this->pngBytes($canvas);
        $id = (string) Str::uuid();
        $originalPath = 'media/originals/'.$id.'.png';
        $path = 'media/normalized/'.$id.'.png';
        $previewPath = 'media/previews/'.$id.'.jpg';
        Storage::disk('local')->put($originalPath, $bytes);
        Storage::disk('local')->put($path, $bytes);
        Storage::disk('local')->put($previewPath, $this->previewBytes($canvas));
        imagedestroy($canvas);

        $asset = MediaAsset::create([
            'name' => $name.' — composição',
            'original_path' => $originalPath,
            'original_width' => $profile->total_width,
            'original_height' => $profile->total_height,
            'mime_type' => 'image/png',
            'sha256' => hash('sha256', $bytes),
            'processing_status' => 'approved',
            'created_by' => $userId,
        ]);

        return MediaVariant::create([
            'media_asset_id' => $asset->id,
            'display_profile_id' => $profile->id,
            'variant_type' => 'screen_composition',
            'path' => $path,
            'preview_path' => $previewPath,
            'width' => $profile->total_width,
            'height' => $profile->total_height,
            'adjustment_mode' => 'COMPOSITE_FILL',
            'processing_details' => ['placements' => $placements],
            'sha256' => hash('sha256', $bytes),
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    /** @return array{0: int, 1: int, mime: string} */
    private function imageInfo(string $contents): array
    {
        $imageInfo = @getimagesizefromstring($contents);

        if ($imageInfo === false) {
            $detectedType = $this->detectContainerType($contents);

            throw new InvalidArgumentException(
                $detectedType === 'HEIC/HEIF'
                    ? 'A imagem está em HEIC/HEIF, formato não suportado pelo servidor. Exporte-a como JPEG, PNG, WebP ou AVIF.'
                    : 'O conteúdo do arquivo não pôde ser decodificado como imagem. Tente abri-lo e exportá-lo novamente como JPEG, PNG, WebP ou AVIF.',
            );
        }

        $supportedMimeTypes = ['image/png', 'image/jpeg', 'image/webp', 'image/avif', 'image/gif', 'image/bmp', 'image/x-ms-bmp'];

        if (! in_array($imageInfo['mime'], $supportedMimeTypes, true)) {
            throw new InvalidArgumentException("O formato real {$imageInfo['mime']} não é suportado. Use JPEG, PNG, WebP, AVIF, GIF ou BMP.");
        }

        return $imageInfo;
    }

    private function detectContainerType(string $contents): ?string
    {
        $brand = substr($contents, 4, 12);

        return str_contains($brand, 'ftypheic')
            || str_contains($brand, 'ftypheif')
            || str_contains($brand, 'ftyphevc')
            || str_contains($brand, 'ftypmif1')
            ? 'HEIC/HEIF'
            : null;
    }

    /** @return array{int, int, int, int, int, int, int, int} */
    private function geometry(MediaAsset $asset, AdjustmentMode $mode, int $targetWidth, int $targetHeight): array
    {
        $sourceWidth = $asset->original_width;
        $sourceHeight = $asset->original_height;

        if ($mode === AdjustmentMode::Stretch) {
            return [0, 0, $sourceWidth, $sourceHeight, 0, 0, $targetWidth, $targetHeight];
        }

        if ($mode === AdjustmentMode::EdgeAdjust) {
            $tolerance = config('tv_agent.dimension_tolerance');

            if (abs($sourceWidth - $targetWidth) > $tolerance || abs($sourceHeight - $targetHeight) > $tolerance) {
                throw new InvalidArgumentException("EDGE_ADJUST aceita diferença máxima de {$tolerance} pixels por dimensão.");
            }

            $copyWidth = min($sourceWidth, $targetWidth);
            $copyHeight = min($sourceHeight, $targetHeight);

            return [
                max(0, intdiv($sourceWidth - $copyWidth, 2)),
                max(0, intdiv($sourceHeight - $copyHeight, 2)),
                $copyWidth,
                $copyHeight,
                max(0, intdiv($targetWidth - $copyWidth, 2)),
                max(0, intdiv($targetHeight - $copyHeight, 2)),
                $copyWidth,
                $copyHeight,
            ];
        }

        $scale = $mode === AdjustmentMode::Fit
            ? min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight)
            : max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);

        if ($mode === AdjustmentMode::Fit) {
            $width = max(1, (int) round($sourceWidth * $scale));
            $height = max(1, (int) round($sourceHeight * $scale));

            return [0, 0, $sourceWidth, $sourceHeight, intdiv($targetWidth - $width, 2), intdiv($targetHeight - $height, 2), $width, $height];
        }

        $cropWidth = max(1, (int) round($targetWidth / $scale));
        $cropHeight = max(1, (int) round($targetHeight / $scale));

        return [intdiv($sourceWidth - $cropWidth, 2), intdiv($sourceHeight - $cropHeight, 2), $cropWidth, $cropHeight, 0, 0, $targetWidth, $targetHeight];
    }

    /** @return array{int, int} */
    private function targetDimensions(DisplayProfile $profile, string $targetScope): array
    {
        return $targetScope === 'screen'
            ? [$profile->display_width, $profile->display_height]
            : [$profile->total_width, $profile->total_height];
    }

    private function pngBytes(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    private function previewBytes(\GdImage $source): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1600 / $sourceWidth, 900 / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $preview = imagecreatetruecolor($width, $height);
        imagecopyresampled($preview, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        ob_start();
        imagejpeg($preview, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($preview);

        return $bytes;
    }
}
