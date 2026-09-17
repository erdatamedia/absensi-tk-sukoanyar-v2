<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use Illuminate\Http\Request;

class AppSettingApiController extends Controller
{
    public function __construct(private AppSettingService $appSettingService)
    {
    }

    public function show()
    {
        return response()->json([
            'status' => 'ok',
            'settings' => $this->appSettingService->currentSettings(),
        ]);
    }

    public function update(Request $request)
    {
        $result = $this->appSettingService->update($request);

        if (! $result['ok']) {
            return response()->json(['status' => 'error', 'msg' => $result['message']], 500);
        }

        return response()->json([
            'status' => 'ok',
            'settings' => $this->appSettingService->currentSettings(),
        ]);
    }
}
