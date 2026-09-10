<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;

class GenealogyController extends Controller
{
    protected const MAX_DEPTH = 3;

    public function index(Request $request)
    {
        $user = auth()->user();
        $farm = $user->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $farmIds = $user->farms()->pluck('farms.id')->all();
        $animals = Animal::where('farm_id', $farm->id)->orderBy('name')->get();
        $allAnimals = Animal::whereIn('farm_id', $farmIds)->orderBy('name')->get();
        $byId = $allAnimals->keyBy('id');

        $childrenOf = [];
        foreach ($allAnimals as $a) {
            if ($a->dam_id) { $childrenOf[$a->dam_id][] = $a; }
            if ($a->sire_id) { $childrenOf[$a->sire_id][] = $a; }
        }

        $selectedId = (int) $request->get('animal');
        $selected = $selectedId ? $byId->get($selectedId) : null;

        $ancestors = null;
        $descendants = null;

        if ($selected) {
            $ancestors = $this->buildAncestors($selected, self::MAX_DEPTH, $byId);
            $descendants = $this->buildDescendants($selected, self::MAX_DEPTH, $childrenOf);
        }

        return view('genealogy.index', compact('farm', 'animals', 'selected', 'ancestors', 'descendants'));
    }

    protected function nodeName(Animal $a): string
    {
        $tag = $a->ear_tag ?: $a->internal_code;
        $name = $a->name ?: ('Animal ' . $a->id);
        $label = $tag ? ($name . ' (' . $tag . ')') : $name;

        if ($a->isSold() || $a->isDeceased()) {
            $label .= ' - ' . $a->statusLabel();
        }

        return $label;
    }

    protected function buildAncestors(Animal $a, int $depth, $byId): array
    {
        $node = [
            'name' => $this->nodeName($a),
            'url' => route('animals.show', $a->id),
            'role' => null,
            'sex' => $a->sex,
            'children' => [],
        ];

        if ($depth <= 0) {
            return $node;
        }

        $madre = $this->parentNode($a->dam_id, $a->dam_name_manual, 'Madre', $depth, $byId);
        $padre = $this->parentNode($a->sire_id, $a->sire_name_manual, 'Padre', $depth, $byId);

        if ($madre) { $node['children'][] = $madre; }
        if ($padre) { $node['children'][] = $padre; }

        return $node;
    }

    protected function parentNode(?int $pid, ?string $manual, string $role, int $depth, $byId): ?array
    {
        if ($pid && ($p = $byId->get($pid))) {
            $sub = $this->buildAncestors($p, $depth - 1, $byId);
            $sub['role'] = $role;
            return $sub;
        }

        if ($manual) {
            return ['name' => $manual, 'url' => null, 'role' => $role, 'sex' => null, 'children' => []];
        }

        return null;
    }

    protected function buildDescendants(Animal $a, int $depth, array $childrenOf): array
    {
        $node = [
            'name' => $this->nodeName($a),
            'url' => route('animals.show', $a->id),
            'role' => null,
            'sex' => $a->sex,
            'children' => [],
        ];

        if ($depth <= 0) {
            return $node;
        }

        foreach (($childrenOf[$a->id] ?? []) as $kid) {
            $sub = $this->buildDescendants($kid, $depth - 1, $childrenOf);
            $sub['role'] = 'Cria';
            $node['children'][] = $sub;
        }

        return $node;
    }
}