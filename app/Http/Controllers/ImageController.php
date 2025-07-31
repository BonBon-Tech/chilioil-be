<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreImageRequest;

class ImageController extends Controller
{
    public function store(StoreImageRequest $request): \Illuminate\Http\JsonResponse
    {
        $category = $request->input('category');
        $image = $request->file('image');

        $folder = $category;
        $path = $image->store("images/{$folder}", 'public');

        return ApiResponse::success(['path' => $path], 'Image uploaded successfully');
    }
}
