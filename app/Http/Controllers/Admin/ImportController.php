<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversationImport;
use App\Models\ImportedConversation;
use App\Services\Import\ImportException;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function index()
    {
        return view('admin.imports.index', [
            'imports' => ConversationImport::query()->withCount(['proposals as pending_count' => fn ($q) => $q->where('status', 'pending')])->latest('id')->paginate(15),
            'aiConfigured' => ImportService::aiConfigured(),
        ]);
    }

    public function store(Request $request, ImportService $service)
    {
        $max = config('portfolio.import.max_kb');
        $request->validate([
            'mode' => ['required', Rule::in(['file', 'text'])],
            'file' => ['required_if:mode,file', 'nullable', 'file', "max:{$max}", 'extensions:zip,json,jsonl,txt,md',
                'mimetypes:application/zip,application/x-zip-compressed,application/json,text/plain,text/markdown,application/x-ndjson,application/octet-stream'],
            'text' => ['required_if:mode,text', 'nullable', 'string', 'max:500000'],
            'title' => ['nullable', 'string', 'max:200'],
        ], ['file.max' => "The file may not be larger than {$max} KB."]);

        try {
            $import = $service->import(
                $request->input('mode') === 'file' ? $request->file('file') : null,
                $request->input('text'),
                $request->input('title'),
                $request->user(),
            );
        } catch (ImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()])->withInput();
        }

        $pending = $import->proposals()->where('status', 'pending')->count();

        return redirect()->route('admin.imports.show', $import)->with('success',
            "Imported {$import->conversation_count} conversation(s). The keyword scan proposed {$pending} change(s) for review.");
    }

    public function show(Request $request, ConversationImport $import)
    {
        $conversations = $import->conversations()
            ->withCount(['proposals as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->when($q = trim((string) $request->query('q')), fn ($query) => $query->where('title', 'like', "%{$q}%"))
            ->orderByDesc('started_at')->paginate(30, ['id', 'title', 'started_at', 'message_count', 'conversation_import_id'])->withQueryString();

        return view('admin.imports.show', [
            'import' => $import,
            'conversations' => $conversations,
            'aiConfigured' => ImportService::aiConfigured(),
            'aiLimit' => config('portfolio.import.ai_max_conversations_per_run'),
            'proposalCounts' => $import->proposals()->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function conversation(ConversationImport $import, ImportedConversation $conversation)
    {
        abort_unless($conversation->conversation_import_id === $import->id, 404);

        return view('admin.imports.conversation', [
            'import' => $import,
            'conversation' => $conversation,
            'proposals' => $conversation->proposals()->latest('id')->get(),
        ]);
    }

    public function runAi(Request $request, ConversationImport $import, ImportService $service)
    {
        abort_unless(ImportService::aiConfigured(), 403, 'AI extraction is not configured.');
        $limit = config('portfolio.import.ai_max_conversations_per_run');
        $ids = $request->validate([
            'conversations' => ['required', 'array', 'min:1', "max:{$limit}"],
            'conversations.*' => ['integer', Rule::exists('imported_conversations', 'id')->where('conversation_import_id', $import->id)],
        ], ['conversations.max' => "Select at most {$limit} conversations per AI run."])['conversations'];

        $service->queueAi($import, array_map('intval', $ids));

        return back()->with('success', count($ids).' conversation(s) queued for AI extraction. Proposals appear here when the queue worker finishes.');
    }

    public function destroy(ConversationImport $import)
    {
        $import->delete(); // conversations and their proposals cascade; applied changes stay in the audit log

        return redirect()->route('admin.imports.index')->with('success', 'Import deleted.');
    }
}
