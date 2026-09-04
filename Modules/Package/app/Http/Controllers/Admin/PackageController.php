<?php

namespace Modules\Package\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Package\Models\Package;
use Modules\Package\Services\PackageManagementService;

class PackageController extends Controller
{
    public function __construct(private readonly PackageManagementService $packages) {}

    public function index(): View
    {
        $packages = Package::orderBy('sort_order')->orderBy('id')->get();
        $features = config('features.list', []);

        return view('package::admin.packages.index', compact('packages', 'features'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePackage($request);

        $this->packages->create($data, $request->file('image'));

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package "' . $data['name'] . '" created.');
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $data = $this->validatePackage($request);

        $this->packages->update($package, $data, $request->file('image'));

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package "' . $data['name'] . '" updated.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $name = $package->name;
        $this->packages->delete($package);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package "' . $name . '" deleted.');
    }

    private function validatePackage(Request $request): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'description'       => ['nullable', 'string'],
            'image'             => ['nullable', 'image', 'max:2048'],
            'price'             => ['required', 'numeric', 'min:0'],
            'discounted_price'  => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'is_free'           => ['boolean'],
            'is_active'         => ['boolean'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'features'          => ['array'],
            'features.*'        => ['string', 'in:' . implode(',', array_keys(config('features.list', [])))],
        ]);

        unset($data['image']);

        $data['is_free'] = $request->boolean('is_free');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['features'] = $data['features'] ?? [];

        if ($data['is_free']) {
            $data['price'] = 0;
            $data['discounted_price'] = null;
        }

        return $data;
    }
}
