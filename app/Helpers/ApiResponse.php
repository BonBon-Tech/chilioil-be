<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, $message = '')
    {
        return response()->json([
            'message' => $message,
            'data' => $data
        ]);
    }

    public static function error($message = '', $data = null, $status = 400)
    {
        return response()->json([
            'message' => $message,
            'errors' => $data
        ], $status);
    }
}

