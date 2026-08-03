<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_a_promotion_with_an_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('hotel_manager');

        $response = $this->actingAs($staff)->postJson('/api/promotions', [
            'title' => 'Off-season discount',
            'category' => 'hotel',
            'image' => UploadedFile::fake()->image('promo.jpg'),
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('image_url'));
    }

    public function test_visitor_cannot_create_a_promotion(): void
    {
        Storage::fake('public');
        $visitor = User::factory()->create()->assignRole('visitor');

        $this->actingAs($visitor)->postJson('/api/promotions', [
            'title' => 'Off-season discount',
            'category' => 'hotel',
            'image' => UploadedFile::fake()->image('promo.jpg'),
        ])->assertForbidden();
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('admin');

        $this->actingAs($staff)->postJson('/api/promotions', [
            'title' => 'Off-season discount',
            'category' => 'hotel',
            'image' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])->assertInvalid(['image']);
    }

    public function test_owner_can_replace_and_remove_the_promotion_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create()->assignRole('themepark_staff');
        $promotion = Promotion::create([
            'title' => 'Season pass sale',
            'category' => 'themepark',
            'created_by' => $staff->id,
        ]);
        $promotion->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection('image');

        $this->actingAs($staff)->post("/api/promotions/{$promotion->id}", [
            '_method' => 'PATCH',
            'remove_image' => true,
        ])->assertOk()->assertJsonPath('image_url', null);

        $this->assertCount(0, $promotion->fresh()->getMedia('image'));
    }

    public function test_a_user_who_does_not_own_the_promotion_cannot_edit_its_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create()->assignRole('hotel_manager');
        $other = User::factory()->create()->assignRole('ferry_operator');
        $promotion = Promotion::create([
            'title' => 'Season pass sale',
            'category' => 'hotel',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)->post("/api/promotions/{$promotion->id}", [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])->assertForbidden();

        $this->assertCount(0, $promotion->fresh()->getMedia('image'));
    }
}
