@extends('admin.layout')
@section('title', 'Import conversations')
@section('content')
<div class="split">
  <div class="stack">
    <form class="card stack" method="post" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data">
      @csrf
      <h2>Add conversation history</h2>
      <div class="filters" data-mode-switch role="radiogroup" aria-label="Import type">
        <label class="check"><input type="radio" name="mode" value="file" @checked(old('mode', 'file') === 'file')> Upload a file</label>
        <label class="check"><input type="radio" name="mode" value="text" @checked(old('mode') === 'text')> Paste text</label>
      </div>
      <div data-mode="file" class="field">
        <label for="file">Export file</label>
        <input type="file" id="file" name="file" accept=".zip,.json,.jsonl,.txt,.md">
        <span class="help">claude.ai data export (.zip or conversations.json), a Claude Code session (.jsonl), or a .txt/.md transcript · max {{ number_format(config('portfolio.import.max_kb') / 1024) }} MB.</span>
      </div>
      <div data-mode="text" class="stack" hidden>
        <div class="field"><label for="title">Title</label><input type="text" id="title" name="title" value="{{ old('title') }}" placeholder="e.g. Payroll tax engine discussion"></div>
        <div class="field"><label for="text">Conversation</label><textarea id="text" name="text" rows="10" placeholder="Human: …&#10;Assistant: …">{{ old('text') }}</textarea>
          <span class="help">Prefix turns with “Human:” / “Assistant:” so only your own statements are scanned.</span></div>
      </div>
      <button class="btn primary" type="submit" data-loading="Importing…">Import and scan</button>
    </form>

    <div class="card" style="padding:0">
      @if ($imports->isEmpty())
        <div class="empty">No imports yet.</div>
      @else
        <div class="table-wrap"><table>
          <thead><tr><th>Import</th><th>Conversations</th><th>Status</th><th>Pending</th><th></th></tr></thead>
          <tbody>
          @foreach ($imports as $import)
            <tr>
              <td><a href="{{ route('admin.imports.show', $import) }}"><b>{{ $import->original_name ?: 'Pasted text' }}</b></a><br><span class="small muted">{{ \App\Models\ConversationImport::SOURCES[$import->source] ?? $import->source }} · {{ $import->created_at->diffForHumans() }}</span></td>
              <td>{{ $import->conversation_count }} <span class="small muted">({{ $import->message_count }} msgs)</span></td>
              <td><span class="badge {{ $import->status }}">{{ str_replace('_', ' ', $import->status) }}</span></td>
              <td>@if ($import->pending_count)<a href="{{ route('admin.proposals.index', ['import' => $import->id]) }}">{{ $import->pending_count }} to review</a>@else 0 @endif</td>
              <td class="actions"><form class="inline" method="post" action="{{ route('admin.imports.destroy', $import) }}" data-confirm="Delete this import and its unreviewed proposals? Changes you already applied stay.">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form></td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
    {{ $imports->links('admin.partials.pagination') }}
  </div>

  <div class="stack">
    <div class="card stack small">
      <h2>How it works</h2>
      <ol style="padding-left:18px; display:grid; gap:6px">
        <li>Upload or paste a conversation. It is stored privately in your database.</li>
        <li>A <b>keyword scan</b> runs on your own messages and proposes technologies and GitHub/LinkedIn links it finds.</li>
        <li>@if ($aiConfigured) Optionally run <b>AI extraction</b> on chosen conversations for projects, roles and achievements. @else Read a conversation and <b>add proposals manually</b> from any excerpt. @endif</li>
        <li>Every proposal is compared with your current records and waits in <a href="{{ route('admin.proposals.index') }}">Review changes</a>. Nothing is applied until you approve it.</li>
      </ol>
    </div>
    <div class="card stack small">
      <h2>Getting your history</h2>
      <p><b>claude.ai:</b> Settings → Privacy → Export data. You'll get an email with a .zip — upload it as-is.</p>
      <p><b>Claude Code:</b> session transcripts live in <code>~/.claude/projects/&lt;project&gt;/*.jsonl</code>. Upload one session at a time.</p>
    </div>
    <div class="alert {{ $aiConfigured ? 'ok' : 'info' }} small" style="margin:0">
      @if ($aiConfigured)
        AI extraction is <b>on</b> ({{ config('portfolio.ai.anthropic.model') }}). Conversations you select are sent to the Anthropic API. Requires a running queue worker.
      @else
        AI extraction is <b>off</b>. Set <code>AI_PROVIDER=anthropic</code> and <code>ANTHROPIC_API_KEY</code> in <code>.env</code> to enable it. The keyword scan and manual proposals work without it.
      @endif
    </div>
  </div>
</div>
@endsection
