<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundEmailController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
