<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\DisplayProfile;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploading_the_same_file_reuses_the_library_asset_and_variant(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 1, 'display_width' => 100, 'display_height' => 100,
            'total_width' => 100, 'total_height' => 100,
        ]);
        $image = imagecreatetruecolor(100, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 180, 220));
        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        foreach (['primeira.png', 'duplicada.png'] as $fileName) {
            $this->post('/media', [
                'name' => 'Imagem reutilizável',
                'display_profile_id' => $profile->id,
                'target_scope' => 'screen',
                'files' => [UploadedFile::fake()->createWithContent($fileName, $contents)],
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseCount('media_variants', 1);
    }

    public function test_exact_image_is_preserved_and_normalized_on_upload(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 2, 'display_width' => 100, 'display_height' => 100,
            'total_width' => 200, 'total_height' => 100,
        ]);

        $response = $this->post('/media', [
            'name' => 'Oferta semanal',
            'display_profile_id' => $profile->id,
            'file' => UploadedFile::fake()->image('oferta.png', 200, 100),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('media_assets', ['name' => 'Oferta semanal', 'original_width' => 200, 'original_height' => 100]);
        $this->assertDatabaseHas('media_variants', ['display_profile_id' => $profile->id, 'width' => 200, 'height' => 100, 'adjustment_mode' => 'EDGE_ADJUST']);

        $variant = MediaVariant::query()->firstOrFail();
        Storage::disk('local')->assertExists($variant->path);
        Storage::disk('local')->assertExists($variant->preview_path);
    }

    public function test_unapproved_variant_cannot_be_published(): void
    {
        $this->actingAs(User::factory()->create());
        $profile = DisplayProfile::factory()->create();
        $device = Device::factory()->for($profile, 'displayProfile')->create();
        $variant = MediaVariant::factory()->for($profile, 'displayProfile')->create(['approved_at' => null]);

        $response = $this->from('/')->post("/media-variants/{$variant->id}/publish", [
            'device_id' => $device->id,
            'name' => 'Campanha bloqueada',
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['campaign']);
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_multiple_images_with_generic_browser_mime_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 2, 'display_width' => 100, 'display_height' => 100,
            'total_width' => 200, 'total_height' => 100,
        ]);
        $firstImage = UploadedFile::fake()->image('primeira.jpeg', 120, 100);
        $genericMimeImage = new UploadedFile(
            $firstImage->getPathname(),
            '7CdP0bDTv9iMk1uEutEoSDwQ.jpeg',
            'application/octet-stream',
            null,
            true,
        );

        $response = $this->post('/media', [
            'name' => 'Campanha teste',
            'display_profile_id' => $profile->id,
            'files' => [
                $genericMimeImage,
                UploadedFile::fake()->image('segunda.png', 100, 100),
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_images_can_be_composed_per_screen_and_published(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 2, 'display_width' => 100, 'display_height' => 100,
            'total_width' => 200, 'total_height' => 100, 'overlap_x' => 0, 'overlap_y' => 0,
        ]);
        $device = Device::factory()->for($profile, 'displayProfile')->create([
            'display_state' => ['total_width' => 200, 'total_height' => 100],
        ]);
        $files = [];

        foreach ([[220, 30, 60], [20, 160, 220]] as $index => [$red, $green, $blue]) {
            $image = imagecreatetruecolor(100, 100);
            imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
            ob_start();
            imagepng($image);
            $contents = (string) ob_get_clean();
            imagedestroy($image);
            $files[] = UploadedFile::fake()->createWithContent('tv-'.($index + 1).'.png', $contents);
        }

        $this->post('/media', [
            'name' => 'Imagens individuais',
            'display_profile_id' => $profile->id,
            'files' => $files,
        ])->assertSessionHasNoErrors();

        $assets = MediaAsset::query()->orderBy('id')->get();
        $response = $this->post('/campaigns/compose-and-publish', [
            'device_id' => $device->id,
            'display_profile_id' => $profile->id,
            'name' => 'Composição por TV',
            'placements' => [
                ['screen' => 1, 'asset_id' => $assets[0]->id],
                ['screen' => 2, 'asset_id' => $assets[1]->id],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_variants', ['variant_type' => 'screen_composition', 'adjustment_mode' => 'COMPOSITE_FILL']);
        $this->assertDatabaseHas('campaigns', ['device_id' => $device->id, 'name' => 'Composição por TV']);
        $this->assertDatabaseHas('device_commands', ['device_id' => $device->id, 'type' => 'campaign.download']);
    }

    public function test_avif_image_with_jpeg_extension_is_detected_by_its_contents(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 1, 'display_width' => 64, 'display_height' => 64,
            'total_width' => 64, 'total_height' => 64,
        ]);
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 180, 220));
        ob_start();
        imageavif($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $response = $this->post('/media', [
            'name' => 'Imagem AVIF disfarçada',
            'display_profile_id' => $profile->id,
            'files' => [UploadedFile::fake()->createWithContent('foto.jpeg', $contents)],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_assets', ['mime_type' => 'image/avif', 'original_width' => 64, 'original_height' => 64]);
        $this->assertDatabaseHas('media_variants', ['width' => 64, 'height' => 64]);
    }

    public function test_heic_upload_returns_a_specific_conversion_message(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create();
        $fakeHeic = "\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic";

        $response = $this->from('/')->post('/media', [
            'name' => 'Foto HEIC',
            'display_profile_id' => $profile->id,
            'files' => [UploadedFile::fake()->createWithContent('foto.jpeg', $fakeHeic)],
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['files' => 'A imagem está em HEIC/HEIF, formato não suportado pelo servidor. Exporte-a como JPEG, PNG, WebP ou AVIF.']);
    }

    public function test_unused_media_can_be_deleted_with_all_stored_files(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $profile = DisplayProfile::factory()->create([
            'rows' => 1, 'columns' => 1, 'display_width' => 100, 'display_height' => 100,
            'total_width' => 100, 'total_height' => 100,
        ]);

        $this->post('/media', [
            'name' => 'Mídia descartável',
            'display_profile_id' => $profile->id,
            'files' => [UploadedFile::fake()->image('descartavel.jpg', 100, 100)],
        ])->assertSessionHasNoErrors();

        $asset = MediaAsset::query()->with('variants')->firstOrFail();
        $paths = $asset->variants
            ->flatMap(fn ($variant): array => [$variant->path, $variant->preview_path])
            ->push($asset->original_path)
            ->all();

        $response = $this->delete("/media/{$asset->id}");

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_media_used_by_a_campaign_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $variant = MediaVariant::factory()->create();
        Campaign::factory()->for($variant, 'variant')->create();

        $response = $this->delete("/media/{$variant->media_asset_id}");

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('media_assets', ['id' => $variant->media_asset_id]);
        $this->assertDatabaseHas('media_variants', ['id' => $variant->id]);
    }

    public function test_three_landscape_tvs_keep_each_image_at_full_hd_and_calculate_wall_width(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $fallbackProfile = DisplayProfile::factory()->create();

        $response = $this->post('/media', [
            'name' => 'Imagem por TV',
            'display_profile_id' => $fallbackProfile->id,
            'target_scope' => 'screen',
            'tv_count' => 3,
            'orientation' => 'landscape',
            'files' => [UploadedFile::fake()->image('full-hd.jpg', 1920, 1080)],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('display_profiles', [
            'rows' => 1,
            'columns' => 3,
            'display_width' => 1920,
            'display_height' => 1080,
            'total_width' => 5760,
            'total_height' => 1080,
        ]);
        $this->assertDatabaseHas('media_variants', [
            'variant_type' => 'screen',
            'width' => 1920,
            'height' => 1080,
            'adjustment_mode' => 'EDGE_ADJUST',
        ]);
    }

    public function test_portrait_layout_swaps_each_tv_dimensions_and_recalculates_total(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $fallbackProfile = DisplayProfile::factory()->create();

        $response = $this->post('/media', [
            'name' => 'Imagem retrato',
            'display_profile_id' => $fallbackProfile->id,
            'target_scope' => 'screen',
            'tv_count' => 3,
            'orientation' => 'portrait',
            'files' => [UploadedFile::fake()->image('retrato.jpg', 1080, 1920)],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('display_profiles', [
            'rows' => 1,
            'columns' => 3,
            'display_width' => 1080,
            'display_height' => 1920,
            'total_width' => 3240,
            'total_height' => 1920,
        ]);
        $this->assertDatabaseHas('media_variants', [
            'variant_type' => 'screen',
            'width' => 1080,
            'height' => 1920,
        ]);
    }

    public function test_two_by_two_profile_preserves_its_grid_and_total_resolution(): void
    {
        $this->actingAs(User::factory()->create());
        Storage::fake('local');
        $fallbackProfile = DisplayProfile::factory()->create();

        $response = $this->post('/media', [
            'name' => 'Grade dois por dois',
            'display_profile_id' => $fallbackProfile->id,
            'target_scope' => 'screen',
            'tv_count' => 4,
            'orientation' => 'landscape',
            'rows' => 2,
            'columns' => 2,
            'files' => [UploadedFile::fake()->image('full-hd.jpg', 1920, 1080)],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('display_profiles', [
            'rows' => 2,
            'columns' => 2,
            'display_width' => 1920,
            'display_height' => 1080,
            'total_width' => 3840,
            'total_height' => 2160,
        ]);
    }
}
