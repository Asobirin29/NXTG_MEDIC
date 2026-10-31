<?php

namespace App\Modules\Identity\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Pemilik produk menambah faskes tanpa menyentuh server (PRD §8). */
class TenantController extends Controller
{
    public const PLANS = ['basic' => 'Basic', 'pro' => 'Pro', 'enterprise' => 'Enterprise'];

    public function index(Request $request): View
    {
        $tenants = Tenant::withCount('users')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('platform.tenants.index', [
            'tenants' => $tenants,
            'stats' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.form', ['tenant' => new Tenant(['type' => 'klinik', 'plan' => 'basic', 'status' => 'active']), 'plans' => self::PLANS]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'admin_password' => ['required', Password::min(8)],
        ]);

        $tenant = $provisioner->provision(
            collect($data)->only(['name', 'code', 'type', 'plan', 'address', 'phone'])->all() + ['status' => 'active'],
            ['name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password'], 'role' => Role::Admin],
        );

        return redirect()->route('platform.tenants.index')->with('success', "Faskes {$tenant->name} dibuat. Admin dapat login dengan {$data['admin_email']}.");
    }

    public function edit(Tenant $tenant): View
    {
        return view('platform.tenants.form', ['tenant' => $tenant, 'plans' => self::PLANS]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validate($this->rules($tenant) + [
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]));

        return redirect()->route('platform.tenants.index')->with('success', "Data faskes {$tenant->name} diperbarui.");
    }

    private function rules(?Tenant $tenant = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('tenants')->ignore($tenant)],
            'type' => ['required', Rule::in(['puskesmas', 'klinik'])],
            'plan' => ['required', Rule::in(array_keys(self::PLANS))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
