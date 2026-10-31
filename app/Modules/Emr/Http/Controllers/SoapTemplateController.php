<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\SoapTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SoapTemplateController extends Controller
{
    public function index(): View
    {
        return view('emr.templates', [
            'templates' => SoapTemplate::orderByRaw("FIELD(section, 'subjective', 'objective', 'assessment', 'plan')")->orderBy('title')->get(),
            'sections' => ClinicalNote::SECTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section' => ['required', Rule::in(array_keys(ClinicalNote::SECTIONS))],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['section' => 'bagian', 'title' => 'judul', 'body' => 'isi']);

        SoapTemplate::create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Template ditambahkan.');
    }

    public function destroy(SoapTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template dihapus.');
    }
}
