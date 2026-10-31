<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.appearance', [
            'themes' => Tenant::THEMES,
            'current' => $request->user()->tenant->theme(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(Tenant::THEMES))],
        ]);

        $request->user()->tenant->putSetting('theme', $data['theme'])->save();

        return back()->with('success', 'Tema tampilan diperbarui untuk seluruh pengguna faskes.');
    }
}
