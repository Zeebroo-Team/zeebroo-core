<?php

namespace Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AdvertisingAgency\Models\Brand;
use Modules\AdvertisingAgency\Services\BrandService;
use Modules\Pos\Http\Controllers\Concerns\ImportsCsvRows;
use Modules\Pos\Http\Controllers\Concerns\ResolvesPosBusiness;

class BrandMgmtBrandController extends Controller
{
    use ResolvesPosBusiness, ImportsCsvRows;

    private const IMPORT_HEADERS = ['name', 'short_code', 'email', 'phone', 'company_name', 'contact_person', 'address'];

    public function __construct(private readonly BrandService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $search = trim((string) $request->query('q', ''));
        $brands = $this->service->list($business, $search ?: null);

        return view('pos::brand-mgmt.brands.index', [
            'business' => $business,
            'brands'   => $brands,
            'search'   => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $data = $this->validateBrand($request);

        if ($this->shortCodeTaken($business->id, $data['short_code'])) {
            return back()->withErrors(['short_code' => 'A brand with this short code already exists.'])->withInput();
        }

        $this->service->create($business, $data);

        return redirect()->route('pos.brand-mgmt.brands.index')->with('status', 'Brand added.');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $brand->business_id === (int) $business->id, 403);

        $data = $this->validateBrand($request);

        if ($this->shortCodeTaken($business->id, $data['short_code'], $brand->id)) {
            return back()->withErrors(['short_code' => 'Another brand is already using this short code.'])->withInput();
        }

        $this->service->update($brand, $data);

        return redirect()->route('pos.brand-mgmt.brands.index')->with('status', 'Brand updated.');
    }

    public function destroy(Request $request, Brand $brand): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;
        abort_unless((int) $brand->business_id === (int) $business->id, 403);

        $this->service->delete($brand);

        return redirect()->route('pos.brand-mgmt.brands.index')->with('status', 'Brand deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) return $business;

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        [$rows, $error] = $this->parseCsv($request->file('file'), self::IMPORT_HEADERS);
        if ($error) {
            return back()->withErrors(['file' => $error]);
        }

        [$validRows, $rowNumbers, $invalidResults] = $this->splitValidRows($rows, [
            'name'           => ['required', 'string', 'max:150'],
            'short_code'     => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:100'],
            'company_name'   => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);

        $serviceResult = $this->service->import($business, $validRows);
        $results = $this->mergeImportResults($serviceResult, $rowNumbers, $invalidResults);

        return redirect()->route('pos.brand-mgmt.brands.index')->with('import_results', $results);
    }

    private function validateBrand(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'short_code'     => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'email'          => ['nullable', 'email', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:100'],
            'company_name'   => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'address'        => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function shortCodeTaken(int $businessId, string $shortCode, ?int $exceptId = null): bool
    {
        return Brand::where('business_id', $businessId)
            ->where('short_code', strtoupper($shortCode))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
