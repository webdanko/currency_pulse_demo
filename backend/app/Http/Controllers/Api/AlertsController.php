<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlertRuleRequest;
use App\Models\AlertRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AlertRule::query()->latest()->get(),
        ]);
    }

    public function store(StoreAlertRuleRequest $request): JsonResponse
    {
        $rule = AlertRule::create($request->validated());

        return response()->json(['data' => $rule], 201);
    }

    public function update(Request $request, AlertRule $alert): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'operator' => ['sometimes', Rule::in(['above', 'below'])],
            'threshold' => ['sometimes', 'numeric', 'gt:0'],
        ]);

        $alert->update($validated);

        return response()->json(['data' => $alert]);
    }

    public function destroy(AlertRule $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(status: 204);
    }
}
