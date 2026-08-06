<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

/**
 * Multi-image 'gallery' collection management, shared by hotels, room types and
 * theme park events - all three needed the same upload/delete pair.
 */
trait ManagesGallery
{
    /**
     * @param  HasMedia&Model  $model
     */
    protected function addGalleryImages(Request $request, $model): JsonResponse
    {
        $request->validate([
            // Capped at six per request: each upload is re-encoded through GD
            // synchronously, and six 5 MB files already sit near PHP's default
            // post_max_size.
            'images' => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        foreach ($request->file('images') as $image) {
            $model->addMedia($image)->toMediaCollection('gallery');
        }

        return response()->json(['gallery' => $model->refresh()->galleryItems()], 201);
    }

    /**
     * @param  HasMedia&Model  $model
     */
    protected function removeGalleryImage($model, int $mediaId): JsonResponse
    {
        // Resolved through the model rather than by id alone: route-model binding
        // on Media would happily hand over another record's image, and the
        // ownership check has to happen before the delete, not after.
        $media = $model->getMedia('gallery')->firstWhere('id', $mediaId);

        abort_if($media === null, 404);

        $media->delete();

        return response()->json(['gallery' => $model->refresh()->galleryItems()]);
    }
}
