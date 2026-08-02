<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    private function canManage(Request $request): bool
    {
        return $request->user()?->hasAnyRole(['hotel_manager', 'themepark_staff', 'ferry_operator', 'admin']);
    }

    /** Public: active promotions shown on the homepage. */
    public function index(): JsonResponse
    {
        $promotions = Promotion::active()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($promotions);
    }

    /** Staff/admin: all promotions (own only, unless admin). */
    public function manage(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            abort(403);
        }

        $query = Promotion::with('creator:id,name')->orderByDesc('created_at');

        if (! $request->user()->hasRole('admin')) {
            $query->where('created_by', $request->user()->id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->canManage($request)) {
            abort(403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url'   => ['nullable', 'string', 'max:500'],
            'category'    => ['required', 'in:hotel,themepark,ferry,general'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $promotion = Promotion::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($promotion->load('creator:id,name'), 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin') && $promotion->created_by !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image_url'   => ['nullable', 'string', 'max:500'],
            'category'    => ['sometimes', 'in:hotel,themepark,ferry,general'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $promotion->update($validated);

        return response()->json($promotion->load('creator:id,name'));
    }

    public function destroy(Request $request, Promotion $promotion): Response
    {
        $user = $request->user();

        if (! $user->hasRole('admin') && $promotion->created_by !== $user->id) {
            abort(403);
        }

        $promotion->delete();

        return response()->noContent();
    }
}
