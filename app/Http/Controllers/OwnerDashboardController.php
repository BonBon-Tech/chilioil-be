<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        // Default: last 30 days
        if (!$startDate || !$endDate) {
            $endDate   = Carbon::now()->toDateString();
            $startDate = Carbon::now()->subDays(29)->toDateString();
        }

        // Clamp to max 90 days
        $diffDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($diffDays > 90) {
            $startDate = Carbon::parse($endDate)->subDays(89)->toDateString();
        }

        // Global stats (all companies, all time)
        $stats = [
            'total_companies'   => Company::count(),
            'total_users'       => User::whereHas('role', fn($q) => $q->where('name', '!=', 'owner'))->count(),
            'total_transactions'=> Transaction::count(),
            'total_revenue'     => (float) Transaction::where('status', 'PAID')->sum('total'),
        ];

        // Daily trends (filtered by date range + optional company)
        $query = Transaction::selectRaw('DATE(`date`) as date, COUNT(*) as count, SUM(total) as total')
            ->where('status', 'PAID')
            ->whereRaw('DATE(`date`) BETWEEN ? AND ?', [$startDate, $endDate]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $trendsData = $query->groupByRaw('DATE(`date`)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill every date in range (including zeros)
        $dailyTrends = [];
        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);
        while ($current->lte($end)) {
            $date = $current->toDateString();
            $row  = $trendsData->get($date);
            $dailyTrends[] = [
                'date'  => $date,
                'count' => $row ? (int) $row->count : 0,
                'total' => $row ? (float) $row->total : 0.0,
            ];
            $current->addDay();
        }

        // All companies (lightweight, for dropdown filter)
        $companies = Company::withCount(['users', 'transactions'])
            ->orderByDesc('transactions_count')
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'plan'             => $c->plan,
                'is_demo'          => $c->is_demo,
                'transaction_count'=> $c->transactions_count,
                'user_count'       => $c->users_count,
            ]);

        return ApiResponse::success([
            'stats'        => $stats,
            'companies'    => $companies,
            'daily_trends' => $dailyTrends,
            'date_range'   => ['start_date' => $startDate, 'end_date' => $endDate],
        ], 'Owner dashboard fetched successfully');
    }
}
