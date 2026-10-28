<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only view of the audit trail; there is deliberately no edit or delete action (INV-02). */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:40'],
        ]);

        $entries = AuditLog::query()
            ->when($filters['module'] ?? null, fn ($q, $m) => $q->where('module', $m))
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', ['entries' => $entries, 'filters' => $filters]);
    }
}
