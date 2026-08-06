<?php

namespace Tests\Feature\Security;

use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\ThemeParkEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Detail pages carry several photos each, so uploads arrive in batches. Every
 * guarantee the single cover image already had - re-encoded before it is ever
 * served, no non-images, role-gated - has to hold for the gallery too.
 */
class GalleryUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function manager(): User
    {
        return User::factory()->create()->assignRole('hotel_manager');
    }

    public function test_manager_can_upload_several_images_to_a_hotel(): void
    {
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($this->manager())->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 200, 200),
                UploadedFile::fake()->image('two.jpg', 200, 200),
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json('gallery'));
        $this->assertSame(2, $hotel->fresh()->getMedia('gallery')->count());
    }

    /**
     * The sanitising conversion is registered unscoped precisely so gallery
     * images get it too; scoping it to the cover collection would silently
     * start serving unprocessed uploads.
     */
    public function test_gallery_images_are_served_from_the_sanitised_rendition(): void
    {
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($this->manager())->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => [UploadedFile::fake()->image('one.jpg', 200, 200)],
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('display', $response->json('gallery.0.url'));
        $this->assertTrue($hotel->fresh()->getMedia('gallery')->first()->hasGeneratedConversion('display'));
    }

    public function test_non_images_are_rejected(): void
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs($this->manager())->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => [UploadedFile::fake()->create('payload.php', 8, 'application/x-php')],
        ])->assertUnprocessable();

        $this->assertSame(0, $hotel->fresh()->getMedia('gallery')->count());
    }

    public function test_an_oversized_batch_is_rejected(): void
    {
        $hotel = Hotel::factory()->create();

        $images = [];
        for ($i = 0; $i < 7; $i++) {
            $images[] = UploadedFile::fake()->image("photo-{$i}.jpg", 50, 50);
        }

        $this->actingAs($this->manager())->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => $images,
        ])->assertUnprocessable();
    }

    public function test_manager_can_delete_a_single_gallery_image(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = $this->manager();

        $gallery = $this->actingAs($manager)->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 50, 50),
                UploadedFile::fake()->image('two.jpg', 50, 50),
            ],
        ])->json('gallery');

        $response = $this->actingAs($manager)
            ->deleteJson("/api/hotels/{$hotel->id}/gallery/{$gallery[0]['id']}");

        $response->assertOk();
        $this->assertCount(1, $response->json('gallery'));
        $this->assertSame($gallery[1]['id'], $response->json('gallery.0.id'));
    }

    /**
     * Media ids are global. Resolving one by id alone would let the owner of any
     * hotel delete photographs belonging to another.
     */
    public function test_a_media_id_from_another_record_cannot_be_deleted(): void
    {
        $manager = $this->manager();
        $mine = Hotel::factory()->create();
        $theirs = Hotel::factory()->create();

        $theirGallery = $this->actingAs($manager)->postJson("/api/hotels/{$theirs->id}/gallery", [
            'images' => [UploadedFile::fake()->image('theirs.jpg', 50, 50)],
        ])->json('gallery');

        $this->actingAs($manager)
            ->deleteJson("/api/hotels/{$mine->id}/gallery/{$theirGallery[0]['id']}")
            ->assertNotFound();

        $this->assertSame(1, $theirs->fresh()->getMedia('gallery')->count());
    }

    public function test_visitor_cannot_upload_to_a_gallery(): void
    {
        $hotel = Hotel::factory()->create();
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->postJson("/api/hotels/{$hotel->id}/gallery", [
            'images' => [UploadedFile::fake()->image('one.jpg', 50, 50)],
        ])->assertForbidden();
    }

    public function test_room_type_gallery_round_trips(): void
    {
        $roomType = RoomType::factory()->create();

        $gallery = $this->actingAs($this->manager())
            ->postJson("/api/room-types/{$roomType->id}/gallery", [
                'images' => [UploadedFile::fake()->image('room.jpg', 200, 200)],
            ])->assertCreated()->json('gallery');

        $this->assertStringContainsString('display', $gallery[0]['url']);
    }

    public function test_event_gallery_round_trips(): void
    {
        $event = ThemeParkEvent::factory()->create();
        $staff = User::factory()->create()->assignRole('themepark_staff');

        $gallery = $this->actingAs($staff)
            ->postJson("/api/themepark/events/{$event->id}/gallery", [
                'images' => [UploadedFile::fake()->image('ride.jpg', 200, 200)],
            ])->assertCreated()->json('gallery');

        $this->assertStringContainsString('display', $gallery[0]['url']);
    }

    public function test_a_hotel_manager_cannot_touch_an_event_gallery(): void
    {
        $event = ThemeParkEvent::factory()->create();

        $this->actingAs($this->manager())->postJson("/api/themepark/events/{$event->id}/gallery", [
            'images' => [UploadedFile::fake()->image('ride.jpg', 50, 50)],
        ])->assertForbidden();
    }
}
