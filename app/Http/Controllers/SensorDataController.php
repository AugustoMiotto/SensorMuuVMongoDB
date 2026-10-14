<?php

namespace App\Http\Controllers;

use App\Events\RealtimeSensorData;
use App\Models\SensorData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensorDataController extends Controller
{
    public function store(Request $request)
    {
        // Token de validação simples
        $expectedToken = env('SENSOR_API_TOKEN', 'H3rsrkcZD8gzh0yWrXKYe3SBt8RwfsynuAWC2vi5');
        $bearer = $request->bearerToken();

        if ($bearer !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'device_uuid' => 'required|string',
            'timestamp_unix' => 'required|numeric',
            'sample_count' => 'required|integer',
            'window_seconds' => 'required|integer',
            'steps' => 'required|integer',
            'rumination_events' => 'required|integer',
            'temperatura_animal' => 'required|numeric',
            'samples' => 'required|array',
        ]);

        $sensorData = SensorData::create($validated);

        // Dispara evento em tempo real para o Reverb / 3D se houver amostras
        if (! empty($validated['samples']) && is_array($validated['samples'])) {
            $lastSample = end($validated['samples']);
            if (is_array($lastSample) && count($lastSample) >= 8) {
                $pitch = (float) $lastSample[6];
                $roll = (float) $lastSample[7];
                $isMoving = ($validated['steps'] ?? 0) > 0;
                $isRuminating = ($validated['rumination_events'] ?? 0) > 0;

                event(new RealtimeSensorData(
                    $validated['device_uuid'],
                    $pitch,
                    $roll,
                    $isMoving,
                    $isRuminating
                ));
            }
        }

        return response()->json([
            'message' => 'Data saved successfully!',
            'id' => $sensorData->id,
        ], 201);
    }

    public function realtime(Request $request)
    {
        $expectedToken = env('SENSOR_API_TOKEN', 'H3rsrkcZD8gzh0yWrXKYe3SBt8RwfsynuAWC2vi5');
        $bearer = $request->bearerToken();

        if ($bearer !== $expectedToken) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('Realtime Payload: ', $request->all());

        $uuid = $request->input('device_uuid', 'unknown');
        $pitch = (float) $request->input('pitch', 0);
        $roll = (float) $request->input('roll', 0);
        $is_moving = $request->boolean('is_moving', false);
        $is_ruminating = $request->boolean('is_ruminating', false);

        event(new RealtimeSensorData($uuid, $pitch, $roll, $is_moving, $is_ruminating));

        return response()->json(['message' => 'Broadcasted']);
    }
}
