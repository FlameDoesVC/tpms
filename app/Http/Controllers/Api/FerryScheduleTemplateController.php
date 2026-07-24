<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FerryScheduleTemplate;
use App\Services\ScheduleTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class FerryScheduleTemplateController extends Controller
{
    public function __construct(private ScheduleTemplateGenerator $generator) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FerryScheduleTemplate::class);

        $validated = $request->validate([
            'ferry_id' => ['nullable', 'exists:ferries,id'],
        ]);

        $query = FerryScheduleTemplate::query()->with('ferry');

        if (! empty($validated['ferry_id'])) {
            $query->where('ferry_id', $validated['ferry_id']);
        }

        return response()->json($query->orderBy('starts_on')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', FerryScheduleTemplate::class);

        $validated = $this->validated($request);

        $template = FerryScheduleTemplate::create($validated);

        $this->generator->generate();

        return response()->json($template->fresh(), 201);
    }

    public function update(Request $request, FerryScheduleTemplate $scheduleTemplate): JsonResponse
    {
        Gate::authorize('update', $scheduleTemplate);

        $validated = $this->validated($request, sometimes: true);

        $scheduleTemplate->update($validated);

        $this->generator->generate();
        $this->generator->reconcile();

        return response()->json($scheduleTemplate->fresh());
    }

    public function destroy(FerryScheduleTemplate $scheduleTemplate): Response
    {
        Gate::authorize('delete', $scheduleTemplate);

        $scheduleTemplate->update(['is_active' => false]);

        return response()->noContent();
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $rule = fn (array $rules) => $sometimes ? ['sometimes', ...$rules] : $rules;

        return $request->validate([
            'ferry_id' => $rule(['required', 'exists:ferries,id']),
            'frequency' => $rule(['required', 'in:daily,weekly,monthly']),
            'weekdays' => ['nullable', 'required_if:frequency,weekly', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'day_of_month' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,31'],
            'departure_time' => $rule(['required', 'date_format:H:i']),
            'arrival_time' => $rule(['required', 'date_format:H:i', 'after:departure_time']),
            'available_seats' => $rule(['required', 'integer', 'min:1']),
            'starts_on' => $rule(['required', 'date']),
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
