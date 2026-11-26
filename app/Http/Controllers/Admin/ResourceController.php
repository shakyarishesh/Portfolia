<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource;
use App\Admin\Resources;
use App\Http\Controllers\Controller;
use App\Support\ContentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Generic CRUD for every Resource (experience, projects, skills…). */
class ResourceController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $resource = Resources::bySlug($slug);
        $query = $resource::query();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($resource, $q) {
                foreach ($resource::SEARCH as $column) {
                    $w->orWhere($column, 'like', '%'.addcslashes($q, '%_\\').'%');
                }
            });
        }
        if (array_key_exists($status = $request->query('status'), ContentStatus::LABELS)) {
            $query->where('status', $status);
        }
        foreach ($resource::filters() as $column => [$label, $options]) {
            if (($value = $request->query($column)) !== null && array_key_exists($value, $options)) {
                $query->where($column, $value);
            }
        }
        if ($scope = $resource::ORDER_SCOPE) {
            $query->orderBy($scope);
        }

        return view('admin.resources.index', [
            'resource' => $resource,
            'records' => $query->ordered()->paginate(25)->withQueryString(),
            'canReorder' => ! $request->hasAny(['q', 'status']),
        ]);
    }

    public function create(string $slug)
    {
        $resource = Resources::bySlug($slug);

        return view('admin.resources.form', ['resource' => $resource, 'record' => null, 'values' => $resource::formValues(null)]);
    }

    public function store(Request $request, string $slug)
    {
        $resource = Resources::bySlug($slug);
        $record = DB::transaction(fn () => $resource::save($resource::newModel(), $request->validate($resource::rules())));

        return redirect()->route('admin.resources.edit', [$slug, $record->getKey()])->with('success', $resource::singular().' created.');
    }

    public function edit(string $slug, int $id)
    {
        $resource = Resources::bySlug($slug);
        $record = $resource::query()->findOrFail($id);

        return view('admin.resources.form', [
            'resource' => $resource,
            'record' => $record,
            'values' => $resource::formValues($record),
            'history' => $record->auditLogs()->with('user')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, string $slug, int $id)
    {
        $resource = Resources::bySlug($slug);
        $record = $resource::query()->findOrFail($id);
        DB::transaction(fn () => $resource::save($record, $request->validate($resource::rules())));

        return redirect()->route('admin.resources.edit', [$slug, $id])->with('success', 'Changes saved.');
    }

    public function destroy(string $slug, int $id)
    {
        $resource = Resources::bySlug($slug);
        $record = $resource::query()->findOrFail($id);
        $title = $resource::title($record);
        $record->delete();

        return redirect()->route('admin.resources.index', $slug)->with('success', "Deleted “{$title}”. You can restore it from the audit log.");
    }

    public function status(Request $request, string $slug, int $id)
    {
        $resource = Resources::bySlug($slug);
        $record = $resource::query()->findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContentStatus::LABELS))]]);
        $record->update($data);

        return back()->with('success', '“'.$resource::title($record).'” is now '.strtolower(ContentStatus::LABELS[$data['status']]).'.');
    }

    /** Swap sort_order with the neighbour above/below (within ORDER_SCOPE). */
    public function move(Request $request, string $slug, int $id)
    {
        $resource = Resources::bySlug($slug);
        $record = $resource::query()->findOrFail($id);
        $up = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'] === 'up';
        $this->swap($resource, $record, $up);

        return back();
    }

    /** @param class-string<Resource> $resource */
    private function swap(string $resource, $record, bool $up): void
    {
        DB::transaction(function () use ($resource, $record, $up) {
            $scope = $resource::ORDER_SCOPE;
            $siblings = $resource::newModel()->newQuery()
                ->when($scope, fn ($q) => $q->where($scope, $record->{$scope}))
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();

            // Normalise first so equal sort_orders still move.
            $ids = $siblings->pluck('id')->all();
            $pos = array_search($record->id, $ids, true);
            $swapWith = $up ? $pos - 1 : $pos + 1;
            if ($pos === false || ! isset($ids[$swapWith])) {
                return;
            }
            [$ids[$pos], $ids[$swapWith]] = [$ids[$swapWith], $ids[$pos]];
            foreach ($siblings as $sibling) {
                $order = array_search($sibling->id, $ids, true) + 1;
                if ($sibling->sort_order !== $order) {
                    $sibling->update(['sort_order' => $order]);
                }
            }
        });
    }
}
