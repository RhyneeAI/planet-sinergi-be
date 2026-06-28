<?php

namespace App\Http\Controllers\Api\Absence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\AbsCustomConfigurationRequest;
use App\Http\Resources\Absence\AbsCustomConfigurationResource;
use App\Models\CustomConfiguration;
use Illuminate\Http\Request;

class AbsCustomConfigurationController extends Controller
{
    public function index()
    {
        $configs = CustomConfiguration::orderBy('key')->get();

        return response()->json([
            'success' => true,
            'message' => __('absence.custom_config.list'),
            'data' => AbsCustomConfigurationResource::collection($configs),
        ]);
    }

    public function show($key)
    {
        $config = CustomConfiguration::where('key', $key)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => __('absence.custom_config.not_found'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => __('absence.custom_config.detail'),
            'data' => new AbsCustomConfigurationResource($config),
        ]);
    }

    public function store(AbsCustomConfigurationRequest $request)
    {
        $config = CustomConfiguration::setValue(
            $request->key,
            $request->value,
            $request->description
        );

        return response()->json([
            'success' => true,
            'message' => __('absence.custom_config.stored'),
            'data' => new AbsCustomConfigurationResource($config),
        ], 201);
    }

    public function update(AbsCustomConfigurationRequest $request, $key)
    {
        $config = CustomConfiguration::where('key', $key)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => __('absence.custom_config.not_found'),
            ], 404);
        }

        $config->update([
            'value' => $request->value,
            'description' => $request->description ?? $config->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('absence.custom_config.updated'),
            'data' => new AbsCustomConfigurationResource($config->fresh()),
        ]);
    }
}
