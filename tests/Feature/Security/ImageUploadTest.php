<?php

namespace Tests\Feature\Security;

use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit finding F-21: uploaded images were stored byte-for-byte and served from
 * the application's own origin, so a file crafted to satisfy getimagesize() while
 * also parsing as markup relied entirely on the response Content-Type.
 */
class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create()->assignRole('themepark_staff');
    }

    public function test_uploaded_image_is_re_encoded(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->staff())->postJson('/api/themepark/events', [
            'name' => 'Dolphin Show',
            'type' => 'show',
            'location' => 'Lagoon',
            'duration_minutes' => 45,
            'capacity_per_slot' => 50,
            'price_per_ticket' => 25,
            'image' => UploadedFile::fake()->image('poster.jpg', 2400, 1800),
        ]);

        $response->assertCreated();

        $event = ThemeParkEvent::firstWhere('name', 'Dolphin Show');
        $media = $event->getFirstMedia('image');

        $this->assertNotNull($media);
        $this->assertTrue($media->hasGeneratedConversion('display'),
            'F-21: the sanitising conversion was not generated, so the raw upload is what gets served.');

        // The served URL must be the re-encoded rendition, not the original bytes.
        $this->assertStringContainsString('display', $event->image_url);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())->postJson('/api/themepark/events', [
            'name' => 'Bad Upload',
            'type' => 'show',
            'location' => 'Lagoon',
            'duration_minutes' => 45,
            'capacity_per_slot' => 50,
            'price_per_ticket' => 25,
            'image' => UploadedFile::fake()->create('payload.svg', 8, 'image/svg+xml'),
        ])->assertUnprocessable();
    }

    public function test_an_oversized_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff())->postJson('/api/themepark/events', [
            'name' => 'Huge Upload',
            'type' => 'show',
            'location' => 'Lagoon',
            'duration_minutes' => 45,
            'capacity_per_slot' => 50,
            'price_per_ticket' => 25,
            // The rule caps uploads at 5 MB.
            'image' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ])->assertUnprocessable();
    }
}
