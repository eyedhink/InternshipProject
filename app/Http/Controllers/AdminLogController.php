<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminLogsResource;
use App\Models\AdminLogs;

class AdminLogController extends Controller
{
    public function get()
    {
        $validated = request()->validate([
            "from_date" => "sometimes|integer",
            "to_date" => "sometimes|integer",
            "action" => "sometimes|string",
            "type" => "sometimes|string",
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

        $logs = $query->get();
        return response()->json(AdminLogsResource::collection($logs));
    }

    public function get_by_id(string $id)
    {
        return response()->json(AdminLogsResource::make(AdminLogs::query()->find($id)));
    }
}
