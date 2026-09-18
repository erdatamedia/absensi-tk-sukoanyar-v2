<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppSettingService;
use App\Support\Branding;
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

    /**
     * Public, unauthenticated: just the non-sensitive bits needed to brand
     * the login page and browser tab (favicon) before anyone is signed in.
     */
    public function branding()
    {
        return response()->json([
            'status' => 'ok',
            'school_name' => Branding::schoolName(),
            'school_logo_url' => Branding::logoUrl(),
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
