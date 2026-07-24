<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventSlotTemplate;
use App\Services\EventSlotTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EventSlotTemplateController extends Controller
{
    public function __construct(private EventSlotTemplateGenerator $generator) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EventSlotTemplate::class);

        $validated = $request->validate([
            'event_id' => ['nullable', 'exists:theme_park_events,id'],
        ]);

        $query = EventSlotTemplate::query()->with('event');

        if (! empty($validated['event_id'])) {
            $query->where('event_id', $validated['event_id']);
        }

        return response()->json($query->orderBy('starts_on')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', EventSlotTemplate::class);

        $validated = $this->validated($request);

        $template = EventSlotTemplate::create($validated);

        $this->generator->generate();

        return response()->json($template->fresh(), 201);
    }

    public function update(Request $request, EventSlotTemplate $slotTemplate): JsonResponse
    {
        Gate::authorize('update', $slotTemplate);

        $validated = $this->validated($request, sometimes: true);

        $slotTemplate->update($validated);

        $this->generator->generate();
        $this->generator->reconcile();

        return response()->json($slotTemplate->fresh());
    }

    public function destroy(EventSlotTemplate $slotTemplate): Response
    {
        Gate::authorize('delete', $slotTemplate);

        $slotTemplate->update(['is_active' => false]);

        return response()->noContent();
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return $request->validate([
            'event_id' => $rule(['required', 'exists:theme_park_events,id']),
            'frequency' => $rule(['required', 'in:daily,weekly,monthly']),
            'weekdays' => ['nullable', 'required_if:frequency,weekly', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,31'],
            'slot_time' => $rule(['required', 'date_format:H:i']),
            'available_capacity' => $rule(['required', 'integer', 'min:1']),
            'starts_on' => $rule(['required', 'date']),
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
