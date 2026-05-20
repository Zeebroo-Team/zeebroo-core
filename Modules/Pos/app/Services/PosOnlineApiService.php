<?php

namespace Modules\Pos\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Account\Models\Account;
use Modules\Business\Models\Business;
use Modules\Pos\Models\Sale;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductUnit;
use Modules\Product\Services\ProductCatalogOptionsService;

class PosOnlineApiService
{
    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly PosSettingsService $posSettings,
        private readonly SaleService $sales,
        private readonly ProductCatalogOptionsService $catalogOptions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(Business $business, ?User $user = null, ?string $search = null, ?int $categoryId = null): array
    {
        $currency = (string) (get_settings('business.currency', '', $business) ?: '');
        $catalogOptions = $this->catalogOptions->optionsForBusiness($business);

        return [
            'business' => $this->formatBusiness($business),
            'currency' => $currency,
            'channel' => Sale::CHANNEL_ONLINE,
            'categories' => $this->formatCategories($this->catalog->posCategories($business)),
            'products' => $this->catalog->productCardsForPos(
                $business,
                filled($search) ? $search : null,
                $categoryId,
            ),
            'accounts' => $this->formatAccounts(
                $this->paymentAccounts($business, $user),
            ),
            'today' => $this->sales->todaySummaryForBusiness($business),
            'settings' => $this->posSettings->forBusiness($business),
            'product_units' => $this->formatProductUnits($catalogOptions['units']),
        ];
    }

    /**
     * @return Collection<int, Account>
     */
    public function paymentAccounts(Business $business, ?User $user): Collection
    {
        $query = Account::query()
            ->with(['bankType', 'bank', 'warehouse'])
            ->where('business_id', $business->id)
            ->orderBy('account_name');

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatBusiness(Business $business): array
    {
        return [
            'id' => (int) $business->id,
            'name' => $business->name,
        ];
    }

    /**
     * @param  Collection<int, \Modules\Product\Models\ProductCategory>  $categories
     * @return list<array<string, mixed>>
     */
    public function formatCategories(Collection $categories): array
    {
        return $categories->map(fn ($cat) => [
            'id' => (int) $cat->id,
            'name' => $cat->name,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return list<array<string, mixed>>
     */
    public function formatAccounts(Collection $accounts): array
    {
        return $accounts->map(fn (Account $account) => [
            'id' => (int) $account->id,
            'account_name' => $account->account_name,
            'label' => $account->deductOptionLabel(),
            'bank_type' => $account->bankType?->name,
            'bank' => $account->bank?->name,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, ProductUnit>  $units
     * @return list<array<string, mixed>>
     */
    public function formatProductUnits(Collection $units): array
    {
        return $units->map(fn (ProductUnit $unit) => [
            'id' => (int) $unit->id,
            'name' => $unit->name,
            'abbreviation' => $unit->abbreviation,
            'label' => $unit->displayLabel(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatProductCard(?Product $product): ?array
    {
        if ($product === null) {
            return null;
        }

        return $this->catalog->productCardForProduct($product);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatSale(Sale $sale): array
    {
        $sale->loadMissing(['items.product', 'creditAccount', 'user']);

        return [
            'id' => (int) $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_method' => $sale->payment_method,
            'payment_method_label' => $sale->paymentMethodLabel(),
            'channel' => $sale->channel,
            'channel_label' => $sale->channelLabel(),
            'subtotal' => round((float) $sale->subtotal, 2),
            'discount_percent' => $sale->discount_percent !== null ? round((float) $sale->discount_percent, 2) : null,
            'discount_amount' => round((float) $sale->discount_amount, 2),
            'total' => round((float) $sale->total, 2),
            'amount_paid' => round((float) $sale->amount_paid, 2),
            'amount_tendered' => $sale->amount_tendered !== null ? round((float) $sale->amount_tendered, 2) : null,
            'change_amount' => $sale->change_amount !== null ? round((float) $sale->change_amount, 2) : null,
            'notes' => $sale->notes,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'credit_account' => $sale->creditAccount ? [
                'id' => (int) $sale->creditAccount->id,
                'label' => $sale->creditAccount->deductOptionLabel(),
            ] : null,
            'cashier' => $sale->user ? [
                'id' => (int) $sale->user->id,
                'name' => $sale->user->name,
            ] : null,
            'items' => $sale->items->map(fn ($item) => [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_stock_layer_id' => $item->product_stock_layer_id !== null
                    ? (int) $item->product_stock_layer_id
                    : null,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'quantity' => round((float) $item->quantity, 3),
                'unit_cost' => $item->unit_cost !== null ? round((float) $item->unit_cost, 2) : null,
                'unit_sell_price' => round((float) $item->unit_sell_price, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formatSaleList(Collection $sales): array
    {
        return $sales->map(fn (Sale $sale) => [
            'id' => (int) $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_method' => $sale->payment_method,
            'channel' => $sale->channel,
            'total' => round((float) $sale->total, 2),
            'items_count' => (int) ($sale->items_count ?? $sale->items()->count()),
            'sold_at' => $sale->sold_at?->toIso8601String(),
        ])->values()->all();
    }
}
