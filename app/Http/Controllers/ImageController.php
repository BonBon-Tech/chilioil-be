<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreImageRequest;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    public function store(StoreImageRequest $request): \Illuminate\Http\JsonResponse
    {
        $category = $request->input('category');
        $image = $request->file('image');

        if ($category === 'company') {
            $companyId = Auth::user()->company_id;
            $ext = $image->getClientOriginalExtension() ?: 'png';
            $path = $image->storeAs("images/company-{$companyId}", "icon.{$ext}", 'public');
        } else {
            $path = $image->store("images/{$category}", 'public');
        }

        return ApiResponse::success(['path' => $path], 'Image uploaded successfully');
    }
}
