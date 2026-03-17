<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\TrialRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrialRequestController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'      => 'required|string|max:100',
                'email'     => 'required|email|max:150|unique:trial_requests,email',
                'whatsapp'  => 'required|string|min:9|max:20',
            ], [
                'email.unique' => 'Email ini sudah pernah mendaftar.',
            ]);

            TrialRequest::create($validated);

            return ApiResponse::success(
                null,
                'Terima kasih! Kode invitation akan dikirim ke WhatsApp Anda dalam 1x24 jam.',
                201
            );
        } catch (ValidationException $e) {
            return ApiResponse::error('Data tidak valid.', $e->errors(), 422);
        }
    }

    public function index()
    {
        $requests = TrialRequest::orderBy('created_at', 'desc')->get();
        return ApiResponse::success($requests);
    }

    public function update(Request $request, string $id)
    {
        $trialRequest = TrialRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,sent,rejected',
        ]);

        $trialRequest->update($validated);

        return ApiResponse::success($trialRequest, 'Status diperbarui.');
    }
}
