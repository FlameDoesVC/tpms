<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MapLocationController extends Controller
{
    /** Public: all active locations for the homepage map. */
    public function index(): JsonResponse
    {
        return response()->json(
            MapLocation::where('is_active', true)->orderBy('name')->get()
        );
    }

    /** Admin: all locations including inactive. */
    public function manage(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403);
        }

        return response()->json(MapLocation::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['required', 'in:hotel,ferry,themepark,beach,general'],
            'latitude'    => ['required', 'numeric', 'between:2.165568,2.177568'],
            'longitude'   => ['required', 'numeric', 'between:73.072713,73.086713'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        return response()->json(MapLocation::create($validated), 201);
    }

    public function update(Request $request, MapLocation $mapLocation): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type'        => ['sometimes', 'in:hotel,ferry,themepark,beach,general'],
            'latitude'    => ['sometimes', 'numeric', 'between:2.165568,2.177568'],
            'longitude'   => ['sometimes', 'numeric', 'between:73.072713,73.086713'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $mapLocation->update($validated);

        return response()->json($mapLocation);
    }

    public function destroy(Request $request, MapLocation $mapLocation): Response
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403);
        }

        $mapLocation->delete();

        return response()->noContent();
    }
}
