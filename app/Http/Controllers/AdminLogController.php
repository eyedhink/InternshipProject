<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Http\Resources\AdminLogsResource;
use App\Models\AdminLogs;
use Illuminate\Http\JsonResponse;

class AdminLogController extends Controller
{
    public function get(): JsonResponse
    {
        $validated = request()->validate([
            "from_date" => ["sometimes", "integer"],
            "to_date" => ["sometimes", "integer"],
            "action" => ["sometimes", "integer"],
            "type" => ["sometimes", "integer"],
            'page' => ['required', 'integer', 'min:1'],
            'limit' => ["sometimes", "integer", "min:1"],
        ]);
        $query = AdminLogs::query();
        if (isset($validated["from_date"])) {
            $query->whereRaw("JSON_EXTRACT(data, '$.timestamp') >= ?", [$validated["from_date"]]);
        }
        if (isset($validated["to_date"])) {
            $query->whereRaw("JSON_EXTRACT(data, '$.timestamp') <= ?", [$validated["to_date"]]);
        }
        if (isset($validated["action"])) {
            $query->where("action", $validated["action"]);
        }
        if (isset($validated["type"])) {
            $query->where("type", $validated["type"]);
        }

        $limit = $validated['limit'] ?? 10;
        $logs = $query->paginate($limit, page: $validated['page']);
        $total_pages = ceil($logs->total() / $limit);
        $pagination_info = [
            "total_items" => $logs->total(),
            "total_pages" => $total_pages,
            "current_page" => $validated["page"],
            "per_page" => $limit,
            "has_next_page" => ($total_pages - $validated['page']) > 0,
            "has_previous_page" => ($validated['page'] - 1) > 0,
            "next_page" => $validated['page'] + 1,
            "previous_page" => $validated['page'] - 1
        ];
        return response()->json(["data" => AdminLogsResource::collection($logs), "pagination_info" => $pagination_info]);
    }

    public function get_by_id(string $id): JsonResponse
    {
        return response()->json(AdminLogsResource::make(AdminLogs::query()->find($id)));
    }
}
