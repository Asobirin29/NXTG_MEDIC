<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Kelola pengguna faskes. User tidak dihapus, hanya dinonaktifkan (jejak audit). */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::sameTenantAs($request->user())
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('settings.users.index', ['users' => $users, 'roles' => Role::options()]);
    }

    public function create(): View
    {
        return view('settings.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['tenant_id'] = $request->user()->tenant_id;

        User::create($data);

        return redirect()->route('settings.users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureSameTenant($request, $user);

        return view('settings.users.form', ['user' => $user, 'roles' => Role::options()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureSameTenant($request, $user);
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== Role::Admin->value)) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa menonaktifkan atau menurunkan role akun sendiri.']);
        }

        $user->update($data);

        return redirect()->route('settings.users.index')->with('success', 'Data pengguna diperbarui.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user)],
            'role' => ['required', new Enum(Role::class)],
            'license_number' => [
                Rule::requiredIf(fn () => Role::tryFrom((string) $request->input('role'))?->requiresLicense()),
                'nullable', 'string', 'max:50',
            ],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'pin' => ['nullable', 'digits_between:4,6'],
            'is_active' => ['boolean'],
        ], [
            'license_number.required' => 'Nomor SIP/STR wajib diisi untuk tenaga medis.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // Kosong = tidak diganti.
        foreach (['password', 'pin'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        return $data;
    }

    private function ensureSameTenant(Request $request, User $user): void
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
    }
}
