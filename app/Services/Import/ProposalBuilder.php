<?php

namespace App\Services\Import;

use App\Admin\ProfileForm;
use App\Admin\Resource;
use App\Admin\Resources;
use App\Models\ConversationImport;
use App\Models\Profile;
use App\Models\ProposedChange;
use App\Services\Import\Extractors\Candidate;
use Illuminate\Database\Eloquent\Model;

/**
 * Compares a candidate with existing records and stores a reviewable proposal.
 *  new           – nothing matching exists
 *  changed       – matches a record; only fills empty fields or adds list items
 *  contradiction – matches a record and disagrees with a value already set
 *  duplicate     – matches a record and adds nothing
 */
class ProposalBuilder
{
    public function build(Candidate $candidate, string $extractor, ?ConversationImport $import = null): ?ProposedChange
    {
        $isProfile = $candidate->entityType === 'profile';
        $resource = $isProfile ? null : Resources::byKey($candidate->entityType);
        if (! $isProfile && ! $resource) {
            return null;
        }

        $fieldDefs = $isProfile ? ProfileForm::contentFields() : $resource::contentFields();
        $proposed = $this->clean($candidate->fields, $fieldDefs, $candidate->entityType);
        if (! $proposed) {
            return null;
        }

        $target = $isProfile ? Profile::query()->first() : $this->findMatch($resource, $proposed, $candidate->matchName);
        [$classification, $proposed, $current] = $target
            ? $this->compare($proposed, $isProfile ? ProfileForm::comparableValues($target) : $resource::comparableValues($target), $fieldDefs)
            : ['new', $proposed, null];

        // The same open proposal from an earlier conversation is not repeated.
        $exists = ProposedChange::query()->pending()
            ->where('entity_type', $candidate->entityType)
            ->where('target_id', $target?->getKey())
            ->get()
            ->contains(fn (ProposedChange $p) => $this->normalise($p->proposed) == $this->normalise($proposed));
        if ($exists) {
            return null;
        }

        return ProposedChange::create([
            'conversation_import_id' => $import?->id,
            'imported_conversation_id' => $candidate->conversationId,
            'entity_type' => $candidate->entityType,
            'target_id' => $target?->getKey(),
            'action' => $target ? 'update' : 'create',
            'proposed' => $proposed,
            'current' => $current,
            'classification' => $classification,
            'extractor' => $extractor,
            'confidence' => $candidate->confidence,
            'source_excerpt' => $candidate->excerpt,
            'rationale' => $candidate->rationale,
        ]);
    }

    /** Re-run the comparison after the admin edits a proposal. */
    public function reclassify(ProposedChange $change): ProposedChange
    {
        $isProfile = $change->entity_type === 'profile';
        $resource = $isProfile ? null : Resources::byKey($change->entity_type);
        $target = $change->target_id ? ($isProfile ? Profile::find($change->target_id) : $resource::query()->find($change->target_id)) : null;
        if (! $target) {
            $change->classification = 'new';
            $change->current = null;

            return $change;
        }
        $fieldDefs = $isProfile ? ProfileForm::contentFields() : $resource::contentFields();
        [$classification, , $current] = $this->compare($change->proposed, $isProfile ? ProfileForm::comparableValues($target) : $resource::comparableValues($target), $fieldDefs);
        $change->classification = $classification;
        $change->current = $current;

        return $change;
    }

    private function clean(array $fields, array $defs, string $type): array
    {
        $clean = [];
        foreach ($fields as $name => $value) {
            $allowed = isset($defs[$name]) || ($type === 'skill' && $name === 'skill_category');
            if (! $allowed || $value === null || $value === '' || $value === []) {
                continue;
            }
            $clean[$name] = is_string($value) ? trim($value) : $value;
        }

        return $clean;
    }

    /** @param class-string<Resource> $resource */
    private function findMatch(string $resource, array $proposed, ?string $matchName): ?Model
    {
        $keyField = $resource::NATURAL_KEY[0];
        foreach (array_filter([$proposed[$keyField] ?? null, $matchName]) as $value) {
            $match = $resource::query()->whereRaw('LOWER(TRIM('.$keyField.')) = ?', [mb_strtolower(trim((string) $value))])->first();
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    /** @return array{0: string, 1: array, 2: array} classification, proposed (merged lists), current snapshot */
    private function compare(array $proposed, array $existing, array $defs): array
    {
        $result = [];
        $current = [];
        $conflict = false;
        $adds = false;

        foreach ($proposed as $name => $value) {
            if (! array_key_exists($name, $existing)) {
                $result[$name] = $value; // e.g. skill_category: shown, resolved on apply
                continue;
            }
            $have = $existing[$name];
            $current[$name] = $have;
            $type = $defs[$name]['type'] ?? 'text';

            if (in_array($type, ['lines', 'tags'], true)) {
                $haveList = (array) $have;
                $known = array_map(fn ($v) => mb_strtolower(trim($v)), $haveList);
                $new = array_values(array_filter((array) $value, fn ($v) => ! in_array(mb_strtolower(trim($v)), $known, true)));
                if ($new) {
                    $adds = true;
                    $result[$name] = array_merge($haveList, $new);
                }

                continue;
            }

            if ($this->same($have, $value, $type)) {
                continue;
            }
            $result[$name] = $value;
            if (blank($have)) {
                $adds = true;
            } else {
                $conflict = true;
            }
        }

        $hasChanges = (bool) array_diff_key($result, ['skill_category' => true]);
        $classification = $conflict ? 'contradiction' : ($adds || $hasChanges ? 'changed' : 'duplicate');

        return [$classification, $classification === 'duplicate' ? $proposed : $result, $current];
    }

    private function same(mixed $a, mixed $b, string $type): bool
    {
        if ($type === 'checkbox') {
            return (bool) $a === (bool) $b;
        }
        $norm = fn ($v) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $v)));

        return $norm($a) === $norm($b) || ($type === 'url' && rtrim($norm($a), '/') === rtrim($norm($b), '/'));
    }

    private function normalise(array $values): array
    {
        ksort($values);

        return array_map(fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : $v, $values);
    }
}
