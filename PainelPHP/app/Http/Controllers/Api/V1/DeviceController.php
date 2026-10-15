<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Device::query()->with('displayProfile')->orderBy('name')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device): JsonResponse
    {
        return response()->json($device->load(['displayProfile', 'heartbeats' => fn ($query) => $query->latest()->limit(10)]));
    }
}
