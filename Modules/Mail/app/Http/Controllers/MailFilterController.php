<?php

namespace Modules\Mail\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Business\Models\Business;
use Modules\Mail\Http\Controllers\Concerns\ResolvesMailBusiness;
use Modules\Mail\Models\MailFilter;
use Modules\Mail\Services\MailFilterService;

class MailFilterController extends Controller
{
    use ResolvesMailBusiness;

    public function __construct(
        private readonly MailFilterService $filters,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        return view('mail::filters.index', [
            'business' => $business,
            'filters'  => $this->filters->listForBusiness($business),
            'fields'   => MailFilter::fields(),
            'actions'  => MailFilter::actions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->filters->create($business, $this->validated($request));

        return redirect()->route('mail.filters.index')->with('status', 'Filter added.');
    }

    public function update(Request $request, MailFilter $filter): RedirectResponse
    {
        $business = $this->requireFilter($request, $filter);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->filters->update($filter, $this->validated($request));

        return redirect()->route('mail.filters.index')->with('status', 'Filter updated.');
    }

    public function destroy(Request $request, MailFilter $filter): RedirectResponse
    {
        $business = $this->requireFilter($request, $filter);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        $this->filters->delete($filter);

        return redirect()->route('mail.filters.index')->with('status', 'Filter removed.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            abort(403);
        }

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $this->filters->reorder($business, $ids);

        return response()->json(['success' => true]);
    }

    private function requireFilter(Request $request, MailFilter $filter): Business|RedirectResponse
    {
        $business = $this->requireBusiness($request);
        if ($business instanceof RedirectResponse) {
            return $business;
        }

        abort_unless($this->filters->filterForBusiness($business, $filter) instanceof MailFilter, 404);

        return $business;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'field'     => ['required', 'string', Rule::in(array_keys(MailFilter::fields()))],
            'value'     => ['required', 'string', 'max:190'],
            'action'    => ['required', 'string', Rule::in(array_keys(MailFilter::actions()))],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
