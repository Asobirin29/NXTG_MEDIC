<?php

namespace App\Modules\Cds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Enums\Severity;
use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Cds\Services\InteractionTemplateSync;
use App\Modules\Inventory\Models\Substance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tabel aturan interaksi zat aktif per faskes (PRD §6.3). */
class InteractionRuleController extends Controller
{
    public function index(Request $request): View
    {
        $term = $request->string('q')->trim()->value();

        $rules = InteractionRule::with(['substanceA', 'substanceB'])
            ->when($term, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('substanceA', fn ($s) => $s->where('name', 'like', "%{$term}%"))
                ->orWhereHas('substanceB', fn ($s) => $s->where('name', 'like', "%{$term}%"))))
            ->when($request->query('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->orderByRaw("FIELD(severity, 'major', 'moderate', 'minor')")
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->paginate(25)->withQueryString();

        return view('cds.interactions.index', [
            'rules' => $rules,
            'severities' => Severity::options(),
            'counts' => InteractionRule::selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity'),
        ]);
    }

    public function create(): View
    {
        return view('cds.interactions.form', ['rule' => new InteractionRule(['is_active' => true, 'severity' => Severity::Moderate]), 'severities' => Severity::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        InteractionRule::create($this->validated($request));

        return redirect()->route('interactions.index')->with('success', 'Aturan interaksi ditambahkan.');
    }

    public function edit(InteractionRule $interaction): View
    {
        return view('cds.interactions.form', ['rule' => $interaction->load(['substanceA', 'substanceB']), 'severities' => Severity::options()]);
    }

    public function update(Request $request, InteractionRule $interaction): RedirectResponse
    {
        $interaction->update($this->validated($request, $interaction));

        return redirect()->route('interactions.index')->with('success', 'Aturan interaksi diperbarui.');
    }

    public function destroy(InteractionRule $interaction): RedirectResponse
    {
        $interaction->delete();

        return redirect()->route('interactions.index')->with('success', 'Aturan interaksi dihapus.');
    }

    public function syncTemplates(Request $request, InteractionTemplateSync $sync): RedirectResponse
    {
        $added = $sync->syncTenant($request->user()->tenant_id);

        return back()->with('success', $added
            ? "{$added} aturan baru dari template ditambahkan."
            : 'Semua aturan template sudah ada di faskes ini.');
    }

    private function validated(Request $request, ?InteractionRule $rule = null): array
    {
        $exists = fn ($attr, $value, $fail) => Substance::whereKey($value)->exists() ?: $fail('Zat aktif tidak ditemukan.');

        $data = $request->validate([
            'substance_a_id' => ['required', 'integer', $exists],
            'substance_b_id' => ['required', 'integer', 'different:substance_a_id', $exists],
            'severity' => ['required', new Enum(Severity::class)],
            'effect' => ['required', 'string', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
        ], ['substance_b_id.different' => 'Zat aktif A dan B harus berbeda.']);

        $a = min((int) $data['substance_a_id'], (int) $data['substance_b_id']);
        $b = max((int) $data['substance_a_id'], (int) $data['substance_b_id']);

        $duplicate = InteractionRule::forPair($a, $b)->when($rule, fn ($q) => $q->whereKeyNot($rule->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['substance_b_id' => 'Aturan untuk pasangan zat aktif ini sudah ada.']);
        }

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
