<nav class="pcat-nav" aria-label="Product catalog navigation">
    <a href="{{ route('product.index') }}" @class(['is-active' => request()->routeIs('product.index', 'product.store', 'product.show', 'product.edit', 'product.update', 'product.destroy')])><i class="fa fa-boxes-stacked" style="margin-right:4px;"></i>Products</a>
    <a href="{{ route('product.categories.index') }}" @class(['is-active' => request()->routeIs('product.categories.*')])><i class="fa fa-folder-tree" style="margin-right:4px;"></i>Categories</a>
    <a href="{{ route('product.brands.index') }}" @class(['is-active' => request()->routeIs('product.brands.*')])><i class="fa fa-tag" style="margin-right:4px;"></i>Brands</a>
    <a href="{{ route('product.units.index') }}" @class(['is-active' => request()->routeIs('product.units.*')])><i class="fa fa-ruler" style="margin-right:4px;"></i>Units</a>
</nav>
