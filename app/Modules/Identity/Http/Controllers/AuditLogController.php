<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->query('date'), fn ($q, $date) => $q->whereDate('created_at', $date))
            ->latest('id')
            ->paginate(30)->withQueryString();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('audit.index', compact('logs', 'actions'));
    }
}
