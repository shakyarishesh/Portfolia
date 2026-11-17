<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ProfileForm;
use App\Admin\Resource;
use App\Admin\Resources;
use App\Http\Controllers\Controller;
use App\Support\ContentStatus;
use App\Models\ImportedConversation;
use App\Models\ProposedChange;
use App\Services\Import\Extractors\Candidate;
use App\Services\Import\ProposalApplier;
use App\Services\Import\ProposalBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $proposals = ProposedChange::query()->with(['conversation:id,title', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('import'), fn ($q, $id) => $q->where('conversation_import_id', $id))
            ->when($request->query('classification'), fn ($q, $c) => $q->where('classification', $c))
            ->when($request->query('entity'), fn ($q, $e) => $q->where('entity_type', $e))
            ->orderByRaw("FIELD(classification, 'contradiction', 'changed', 'new', 'duplicate')")
            ->orderByDesc('confidence')->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('admin.proposals.index', [
            'proposals' => $proposals,
            'status' => $status,
            'entityTypes' => $this->entityTypes(),
            'duplicateCount' => ProposedChange::pending()->where('classification', 'duplicate')
                ->when($request->query('import'), fn ($q, $id) => $q->where('conversation_import_id', $id))->count(),
        ]);
    }

    public function edit(ProposedChange $proposal)
    {
        abort_unless($proposal->isPending(), 404);
        [$fields] = $this->fieldsFor($proposal->entity_type);

        return view('admin.proposals.edit', ['proposal' => $proposal->load('conversation'), 'fields' => $fields]);
    }

    /** Save edits to a proposal (does not apply it). */
    public function update(Request $request, ProposedChange $proposal, ProposalBuilder $builder)
    {
        abort_unless($proposal->isPending(), 409);
        $proposal->proposed = $this->readValues($request, $proposal->entity_type, $proposal->proposed);
        $builder->reclassify($proposal)->save();

        return redirect()->route('admin.proposals.index', ['import' => $proposal->conversation_import_id])->with('success', 'Proposal updated — review and approve it when ready.');
    }

    public function approve(Request $request, ProposedChange $proposal, ProposalApplier $applier)
    {
        $data = $request->validate([
            'new_status' => ['nullable', Rule::in(array_keys(ContentStatus::LABELS))],
            'confirm_overwrite' => ['nullable', 'boolean'],
        ]);
        $applier->approve($proposal, $request->user(), null, $data['new_status'] ?? 'draft', $request->boolean('confirm_overwrite'));

        $where = $proposal->entity_type === 'profile' ? 'your profile' : 'the '.strtolower(Resources::byKey($proposal->entity_type)::SINGULAR);

        return back()->with('success', "Applied to {$where}. ".($proposal->action === 'create' ? 'New records start as '.strtolower(ContentStatus::LABELS[$data['new_status'] ?? 'draft']).'.' : ''));
    }

    public function reject(Request $request, ProposedChange $proposal, ProposalApplier $applier)
    {
        $applier->reject($proposal, $request->user());

        return back()->with('success', 'Proposal rejected.');
    }

    public function dismissDuplicates(Request $request)
    {
        $count = ProposedChange::pending()->where('classification', 'duplicate')
            ->when($request->input('import'), fn ($q, $id) => $q->where('conversation_import_id', $id))
            ->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', "Dismissed {$count} duplicate proposal(s).");
    }

    /** Manual proposal from a conversation excerpt — works without any AI configured. */
    public function create(Request $request)
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys($this->entityTypes()))],
            'conversation' => ['nullable', 'integer', Rule::exists('imported_conversations', 'id')],
            'excerpt' => ['nullable', 'string', 'max:2000'],
        ]);
        [$fields] = $this->fieldsFor($data['entity_type']);

        return view('admin.proposals.create', [
            'entityType' => $data['entity_type'],
            'entityLabel' => $this->entityTypes()[$data['entity_type']],
            'fields' => $fields,
            'conversation' => isset($data['conversation']) ? ImportedConversation::find($data['conversation']) : null,
            'excerpt' => $data['excerpt'] ?? null,
        ]);
    }

    public function store(Request $request, ProposalBuilder $builder)
    {
        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys($this->entityTypes()))],
            'conversation_id' => ['nullable', 'integer', Rule::exists('imported_conversations', 'id')],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'match_name' => ['nullable', 'string', 'max:200'],
        ]);
        $values = $this->readValues($request, $data['entity_type']);
        if (! $values) {
            return back()->withErrors(['values' => 'Fill in at least one field.'])->withInput();
        }

        $conversation = isset($data['conversation_id']) ? ImportedConversation::find($data['conversation_id']) : null;
        $proposal = $builder->build(new Candidate(
            entityType: $data['entity_type'],
            fields: $values,
            excerpt: $data['excerpt'] ?? null,
            rationale: 'Added manually.',
            conversationId: $conversation?->id,
            matchName: $data['match_name'] ?? null,
        ), 'manual', $conversation?->import);

        if (! $proposal) {
            return back()->withErrors(['values' => 'An identical proposal is already waiting for review.'])->withInput();
        }

        return redirect()->route('admin.proposals.index', ['import' => $proposal->conversation_import_id])
            ->with('success', 'Proposal saved as “'.ProposedChange::CLASSIFICATIONS[$proposal->classification].'”. Review it below.');
    }

    /** @return array<string, string> */
    private function entityTypes(): array
    {
        return ['profile' => 'Profile'] + collect(Resources::importable())->mapWithKeys(fn ($r) => [$r::KEY => $r::SINGULAR])->all();
    }

    /** @return array{0: array} content fields for an entity type */
    private function fieldsFor(string $type): array
    {
        if ($type === 'profile') {
            return [ProfileForm::contentFields()];
        }
        $fields = Resources::byKey($type)::contentFields();
        if ($type === 'skill') {
            unset($fields['skill_category_id']);
            $fields = ['skill_category' => ['type' => 'text', 'label' => 'Category name', 'help' => 'Existing category, or a new one (created as draft).']] + $fields;
        }

        return [$fields];
    }

    /** Request input → comparable-shape values, validated per field type. */
    private function readValues(Request $request, string $type, array $existing = []): array
    {
        [$fields] = $this->fieldsFor($type);
        $rules = [];
        foreach ($fields as $name => $f) {
            $rules["values.{$name}"] = match ($f['type']) {
                'select' => ['nullable', Rule::in(array_map('strval', array_keys($f['options'] ?? [])))],
                'date' => ['nullable', 'date'],
                'number' => ['nullable', 'integer'],
                'url' => ['nullable', 'url:http,https', 'max:255'],
                'email' => ['nullable', 'email', 'max:150'],
                'checkbox' => ['nullable', 'boolean'],
                default => ['nullable', 'string', 'max:8000'],
            };
        }
        $input = $request->validate($rules)['values'] ?? [];

        $values = [];
        foreach ($fields as $name => $f) {
            $value = $input[$name] ?? null;
            $value = match ($f['type']) {
                'lines' => Resource::splitLines($value),
                'tags' => Resource::splitTags($value),
                'checkbox' => array_key_exists($name, $input) ? (bool) $value : ($existing[$name] ?? null),
                default => is_string($value) ? trim($value) : $value,
            };
            if ($value !== null && $value !== '' && $value !== []) {
                $values[$name] = $value;
            }
        }

        return $values;
    }
}
