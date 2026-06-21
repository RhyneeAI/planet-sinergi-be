<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FileHelper;
use App\Http\Controllers\Controller;
use App\Models\ExportToken;

class ExportController extends Controller
{
    public function status(string $token)
    {
        $exportToken = ExportToken::where('token', $token)->first();

        if (! $exportToken) {
            return response()->json([
                'success' => false,
                'message' => __('exports.token_not_found'),
                'code' => 404,
            ], 404);
        }

        $response = [
            'token' => $exportToken->token,
            'status' => $exportToken->status,
        ];

        if ($exportToken->status === 'completed' && $exportToken->disk_path) {
            $response['download_url'] = FileHelper::downloadUrl($exportToken->disk_path);
            $response['filename'] = $exportToken->filename;
        }

        if ($exportToken->status === 'failed') {
            $response['error_message'] = $exportToken->error_message;
        }

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
}
