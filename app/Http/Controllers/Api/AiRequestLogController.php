<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiRequestLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AiRequestLog::query()
            ->with('prescription:id,tracking_number,status,total_amount')
            ->latest();

        if ($request->filled('model')) {
            $query->where('model', $request->string('model')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->string('purpose')->toString());
        }

        return $query->paginate(50);
    }

    public function summary()
    {
        return AiRequestLog::query()
            ->select([
                'provider',
                'model',
                DB::raw('COUNT(*) as total_requests'),
                DB::raw("SUM(CASE WHEN status = 'SUCCEEDED' THEN 1 ELSE 0 END) as successful_requests"),
                DB::raw("SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_requests"),
                DB::raw('AVG(duration_ms) as average_duration_ms'),
                DB::raw('SUM(input_tokens) as input_tokens'),
                DB::raw('SUM(output_tokens) as output_tokens'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('SUM(estimated_cost_usd) as estimated_cost_usd'),
            ])
            ->groupBy('provider', 'model')
            ->orderBy('estimated_cost_usd')
            ->get();
    }
}
