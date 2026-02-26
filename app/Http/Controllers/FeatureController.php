<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Feature;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeatureController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $features = Feature::orderBy('sort_order')->get();
        return ApiResponse::success($features, 'Features fetched');
    }

    public function planFeatures(Request $request): \Illuminate\Http\JsonResponse
    {
        $plan = $request->query('plan', 'basic');

        $features = Feature::orderBy('sort_order')->get()->map(function ($feature) use ($plan) {
            $planFeature = PlanFeature::where('plan', $plan)
                ->where('feature_id', $feature->id)
                ->first();

            return [
                'feature' => $feature,
                'is_active' => $planFeature ? $planFeature->is_active : false,
            ];
        });

        return ApiResponse::success($features, 'Plan features fetched');
    }

    public function updatePlanFeature(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,custom',
            'feature_id' => 'required|exists:features,id',
            'is_active' => 'required|boolean',
        ]);

        $feature = Feature::find($request->feature_id);

        // Prevent disabling core features
        $coreSlugs = ['dashboard', 'pos'];
        if (in_array($feature->slug, $coreSlugs) && !$request->is_active) {
            return ApiResponse::error('Fitur dasar (Dashboard, POS) tidak boleh dinonaktifkan', null, 422);
        }

        PlanFeature::updateOrCreate(
            ['plan' => $request->plan, 'feature_id' => $request->feature_id],
            ['is_active' => $request->is_active]
        );

        return ApiResponse::success(null, 'Fitur berhasil diperbarui');
    }

    public function userFeatures(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        // Owner gets all features
        if ($user->role && $user->role->name === 'owner') {
            $features = Feature::orderBy('sort_order')->get();
            return ApiResponse::success($features, 'User features fetched');
        }

        $company = $user->company;
        if (!$company) {
            return ApiResponse::success([], 'No company found');
        }

        $features = $company->getActiveFeatures();
        return ApiResponse::success($features, 'User features fetched');
    }
}
