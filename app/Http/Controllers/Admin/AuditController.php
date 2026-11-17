<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Import\AuditRestorer;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()->with('user')
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('source'), fn ($q, $s) => $q->where('source', $s))
            ->when($request->query('record'), fn ($q, $id) => $q->where('auditable_id', (int) $id))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'types' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }

    public function restore(AuditLog $log, AuditRestorer $restorer)
    {
        $restorer->restore($log);

        return back()->with('success', 'Change #'.$log->id.' was reverted. The revert is itself in the log.');
    }
}
