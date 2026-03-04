<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post("/api/test-json", function (Request \$request) {
    return response()->json([
        "all" => \$request->all(),
        "json" => \$request->json()->all(),
        "input" => \$request->input(),
        "content" => \$request->getContent(),
        "content_type" => \$request->header("Content-Type"),
    ]);
});
