import 'dart:async';

import 'package:audioplayers/audioplayers.dart';
import 'package:dio/dio.dart' show DioException;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_endpoints.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money.dart';
import '../data/scanner_cart_controller.dart';
import '../data/pos_receipt_repository.dart';
import '../data/receipt_pdf_service.dart';
import '../models/pos_cart_item.dart';
import '../models/sale_receipt.dart';
import '../widgets/checkout_code_entry.dart';
import '../widgets/customer_picker_sheet.dart';
import 'barcode_scanner_screen.dart';
import 'sale_receipt_screen.dart';

// ── Models ───────────────────────────────────────────────────────────────────

typedef _CartItem = PosCartItem;

class _ParkedSale {
  _ParkedSale({required this.label, required this.cart, this.customer});
  final String label;
  final List<_CartItem> cart;
  final Map<String, dynamic>? customer;
}

class _AppliedCoupon {
  const _AppliedCoupon({
    required this.code,
    required this.name,
    required this.type,
    required this.value,
  });

  final String code;
  final String name;
  final String type;
  final double value;

  double discountFor(double amount) {
    final discount = type == 'percent' ? amount * value / 100 : value;
    return discount.clamp(0, amount).toDouble();
  }
}

class _AppliedGiftCard {
  const _AppliedGiftCard({
    required this.code,
    required this.name,
    required this.balance,
  });

  final String code;
  final String name;
  final double balance;
}

// ── Main Screen ──────────────────────────────────────────────────────────────

class PosScreen extends StatefulWidget {
  const PosScreen({
    super.key,
    this.initialCart = const [],
    this.receiptRepository,
    this.receiptPdfService,
  });

  final List<PosCartItem> initialCart;
  final PosReceiptRepository? receiptRepository;
  final ReceiptPdfService? receiptPdfService;

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen>
    with SingleTickerProviderStateMixin {
  // Search
  final _searchCtrl = TextEditingController();
  Timer? _debounce;
  Future<AudioPool?>? _addSoundPoolFuture;

  // Products & categories
  bool _loadingProducts = false;
  String? _productError;
  List<Map<String, dynamic>> _products = [];
  List<Map<String, dynamic>> _categories = [];
  int _selectedCategoryId = 0;
  int _productRequestId = 0;

  // Cart
  final List<_CartItem> _cart = [];
  Map<String, dynamic>? _customer;

  // Parked sales
  final List<_ParkedSale> _parked = [];

  // Checkout state
  String _paymentMethod = 'cash';
  String _discountType = 'pct'; // 'pct' | 'flat'
  double _discountValue = 0;
  String _notes = '';
  String? _creditDueDate;
  final _paidCtrl = TextEditingController();
  bool _checkingOut = false;
  late final _receiptRepository =
      widget.receiptRepository ?? PosReceiptRepository();

  // Animation
  late final AnimationController _cartPulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 200),
    lowerBound: 1.0,
    upperBound: 1.22,
  );

  @override
  void initState() {
    super.initState();
    _cart.addAll(widget.initialCart.map((item) => item.copy()));
    _addSoundPoolFuture ??= _prepareAddSound();
    _loadCategories();
    _loadProducts();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _debounce?.cancel();
    _paidCtrl.dispose();
    _cartPulse.dispose();
    unawaited(_disposeAddSound());
    super.dispose();
  }

  Future<AudioPool?> _prepareAddSound() async {
    try {
      return await AudioPool.createFromAsset(
        path: 'sounds/beep.wav',
        minPlayers: 1,
        maxPlayers: 4,
      );
    } catch (error) {
      debugPrint('Could not prepare POS beep: $error');
      return null;
    }
  }

  Future<void> _playAddSound() async {
    try {
      if (!mounted) return;
      // Existing screen state can survive hot reload without running initState.
      final pool = await (_addSoundPoolFuture ??= _prepareAddSound());
      if (mounted && pool != null) await pool.start();
    } catch (error) {
      debugPrint('Could not play POS beep: $error');
    }
  }

  Future<void> _disposeAddSound() async {
    try {
      final future = _addSoundPoolFuture;
      if (future == null) return;
      final pool = await future;
      await pool?.dispose();
    } catch (error) {
      debugPrint('Could not dispose POS beep: $error');
    }
  }

  // ── Data ──────────────────────────────────────────────────────────────────

  Future<void> _loadCategories() async {
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.categories,
        params: {'per_page': 200},
      );
      final body = res.data;
      final list = (body is Map ? (body['data'] ?? []) : body) as List? ?? [];
      if (mounted) {
        setState(() {
          _categories = list
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        });
      }
    } catch (_) {}
  }

  Future<void> _loadProducts({String? query, bool forceRefresh = false}) async {
    final search = query ?? _searchCtrl.text.trim();
    final category = _selectedCategoryId;
    // Only the newest request may update the list — a slow, older response
    // (e.g. the first unfiltered load) must not overwrite a search or filter.
    final requestId = ++_productRequestId;
    setState(() {
      _loadingProducts = true;
      _productError = null;
    });
    try {
      final params = <String, dynamic>{'per_page': 60};
      if (search.isNotEmpty) params['q'] = search;
      if (category > 0) params['category'] = category;
      final res = await ApiClient.instance.get(
        ApiEndpoints.products,
        params: params,
        bypassCache: search.isNotEmpty || forceRefresh,
      );
      if (requestId != _productRequestId) return;
      final body = res.data;
      final list = (body is Map ? (body['data'] ?? []) : body) as List? ?? [];
      _products = list
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } catch (e) {
      if (requestId == _productRequestId) _productError = apiErrorMessage(e);
    } finally {
      if (mounted && requestId == _productRequestId)
        setState(() => _loadingProducts = false);
    }
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 350),
      () => _loadProducts(query: value.trim()),
    );
  }

  /// Enter in the search box. A barcode scanner that types like a keyboard sends
  /// the code then Enter, so a code that matches a SKU goes straight to the cart;
  /// anything else is a normal search.
  Future<void> _onSearchSubmitted(String value) async {
    _debounce?.cancel();
    final text = value.trim();
    if (text.isNotEmpty && !text.contains(RegExp(r'\s'))) {
      final outcome = await _addByCode(text);
      if (!mounted) return;
      if (outcome.ok) {
        _searchCtrl.clear();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(outcome.message),
            backgroundColor: AppColors.success,
            duration: const Duration(seconds: 2),
          ),
        );
        _loadProducts(query: '');
        return;
      }
    }
    _loadProducts(query: text);
  }

  void _selectCategory(int id) {
    setState(() => _selectedCategoryId = id);
    _loadProducts();
  }

  // ── Barcode ───────────────────────────────────────────────────────────────

  /// Looks a scanned/typed code up as a product or batch SKU and adds it to the
  /// cart. Never throws — the outcome says what to tell the cashier.
  Future<ScanOutcome> _addByCode(String code) async {
    try {
      final res = await ApiClient.instance.get(
        ApiEndpoints.productBySku(code),
        bypassCache: true,
      );
      final body = res.data;
      final data = body is Map ? body['data'] : null;
      if (data is! Map)
        return ScanOutcome.failed('No product found for "$code"');

      final product = Map<String, dynamic>.from(data);
      final name = (product['name'] as String?) ?? code;
      if (!_addToCart(product, quiet: true))
        return ScanOutcome.failed('$name is out of stock');

      final inCart = _cart
          .firstWhere((c) => c.id == (product['id'] as num).toInt())
          .qty;
      return ScanOutcome.added(
        inCart > 1 ? '$name · $inCart in cart' : '$name added',
      );
    } on DioException catch (e) {
      if (e.response?.statusCode == 404)
        return ScanOutcome.failed('No product found for "$code"');
      return ScanOutcome.failed(apiErrorMessage(e));
    } catch (e) {
      return ScanOutcome.failed(apiErrorMessage(e));
    }
  }

  Future<void> _openScanner() async {
    final scanned = await Navigator.of(context).push<List<PosCartItem>>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => BarcodeScannerScreen(
          initialCart: _cart,
          onProductAdded: () => unawaited(_playAddSound()),
        ),
      ),
    );
    if (!mounted || scanned == null) return;
    setState(() {
      _cart
        ..clear()
        ..addAll(scanned);
    });
  }

  // ── Cart ──────────────────────────────────────────────────────────────────

  /// Returns false (and, unless [quiet], says why) when the product can't be added.
  bool _addToCart(Map<String, dynamic> product, {bool quiet = false}) {
    if (posProductIsOutOfStock(product)) {
      if (!quiet) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('${product['name']} is out of stock'),
            backgroundColor: AppColors.error,
          ),
        );
      }
      return false;
    }
    setState(() {
      final idx = _cart.indexWhere(
        (c) => c.id == (product['id'] as num).toInt(),
      );
      if (idx >= 0) {
        _cart[idx].qty++;
      } else {
        _cart.add(_CartItem(product: product));
      }
    });
    _cartPulse.forward().then((_) => _cartPulse.reverse());
    HapticFeedback.lightImpact();
    unawaited(_playAddSound());
    return true;
  }

  void _removeFromCart(int id) =>
      setState(() => _cart.removeWhere((c) => c.id == id));

  void _setQty(int id, int qty) {
    if (qty < 1) {
      _removeFromCart(id);
      return;
    }
    setState(() {
      final idx = _cart.indexWhere((c) => c.id == id);
      if (idx >= 0) _cart[idx].qty = qty;
    });
  }

  int get _cartCount => _cart.fold(0, (s, c) => s + c.qty);

  double get _subtotalAfterItemDisc =>
      _cart.fold(0.0, (s, c) => s + c.lineTotal);

  double get _orderDiscount {
    if (_discountType == 'flat')
      return _discountValue.clamp(0, _subtotalAfterItemDisc);
    return _subtotalAfterItemDisc * _discountValue / 100;
  }

  double get _total =>
      (_subtotalAfterItemDisc - _orderDiscount).clamp(0, double.infinity);

  double get _change {
    final paid = double.tryParse(_paidCtrl.text) ?? 0;
    return paid - _total;
  }

  // ── Hold / Recall ─────────────────────────────────────────────────────────

  void _parkCurrentSale() {
    if (_cart.isEmpty) return;
    final label = 'Hold ${_parked.length + 1}';
    setState(() {
      _parked.add(
        _ParkedSale(
          label: label,
          cart: List<_CartItem>.from(
            _cart.map(
              (c) => _CartItem(
                product: c.product,
                qty: c.qty,
                itemDiscountPct: c.itemDiscountPct,
              ),
            ),
          ),
          customer: _customer,
        ),
      );
      _cart.clear();
      _customer = null;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Sale parked as $label'),
        backgroundColor: AppColors.success,
      ),
    );
  }

  void _recallSale(_ParkedSale sale) {
    setState(() {
      if (_cart.isNotEmpty) {
        _parked.add(
          _ParkedSale(
            label: 'Hold ${_parked.length + 1}',
            cart: List<_CartItem>.from(
              _cart.map(
                (c) => _CartItem(
                  product: c.product,
                  qty: c.qty,
                  itemDiscountPct: c.itemDiscountPct,
                ),
              ),
            ),
            customer: _customer,
          ),
        );
      }
      _cart.clear();
      _cart.addAll(sale.cart);
      _customer = sale.customer;
      _parked.remove(sale);
    });
  }

  void _openParkedSalesModal() {
    if (_parked.isEmpty) return;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => _ParkedSalesSheet(
        parked: _parked,
        onRecall: (sale) {
          Navigator.pop(context);
          _recallSale(sale);
        },
        onDelete: (sale) {
          setState(() => _parked.remove(sale));
        },
      ),
    );
  }

  // ── Checkout ──────────────────────────────────────────────────────────────

  Future<SaleReceipt> _completeSale({
    required String paymentMethod,
    required double amountPaid,
    required Map<String, dynamic>? customer,
    required double discountPercent,
    required double discountFlat,
    required String notes,
    required String? creditDueDate,
    required String? couponCode,
    required String? giftCardCode,
    required double giftCardAmount,
  }) async {
    if (_checkingOut) throw StateError('A sale is already being submitted.');
    setState(() => _checkingOut = true);
    try {
      final body = <String, dynamic>{
        'payment_method': paymentMethod,
        'amount_paid': amountPaid,
        if (paymentMethod == 'cash') 'amount_tendered': amountPaid,
        if (customer != null) 'customer_id': customer['id'],
        if (discountPercent > 0) 'discount_percent': discountPercent,
        if (discountFlat > 0) 'discount_flat': discountFlat,
        if (couponCode != null) 'coupon_code': couponCode,
        if (giftCardCode != null && giftCardAmount > 0) ...{
          'gift_card_code': giftCardCode,
          'gift_card_amount': giftCardAmount,
        },
        if (notes.isNotEmpty) 'notes': notes,
        if (paymentMethod == 'credit' && creditDueDate != null)
          'credit_due_date': creditDueDate,
        'items': [
          for (final c in _cart)
            {
              'product_id': c.id,
              'qty': c.qty,
              if (c.itemDiscountPct > 0)
                'item_discount_percent': c.itemDiscountPct,
            },
        ],
      };

      return await _receiptRepository.completeSale(body);
    } finally {
      if (mounted) setState(() => _checkingOut = false);
    }
  }

  Future<void> _openCheckout() async {
    if (_cart.isEmpty) return;
    _paidCtrl.text = _total.toStringAsFixed(2);
    final receipt = await showModalBottomSheet<SaleReceipt>(
      context: context,
      isScrollControlled: true,
      isDismissible: false,
      enableDrag: false,
      backgroundColor: Colors.transparent,
      builder: (_) => _CheckoutSheet(
        cart: _cart,
        total: _total,
        subtotalAfterItemDisc: _subtotalAfterItemDisc,
        orderDiscount: _orderDiscount,
        customer: _customer,
        paymentMethod: _paymentMethod,
        discountType: _discountType,
        discountValue: _discountValue,
        notes: _notes,
        creditDueDate: _creditDueDate,
        paidCtrl: _paidCtrl,
        change: _change,
        onCustomerChanged: (c) => setState(() => _customer = c),
        onPaymentMethodChanged: (m) => setState(() => _paymentMethod = m),
        onDiscountTypeChanged: (t) => setState(() {
          _discountType = t;
          _discountValue = 0;
        }),
        onDiscountValueChanged: (v) => setState(() => _discountValue = v),
        onNotesChanged: (n) => setState(() => _notes = n),
        onCreditDueDateChanged: (d) => setState(() => _creditDueDate = d),
        onPaidChanged: (_) => setState(() {}),
        onItemDiscountChanged: (id, pct) => setState(() {
          final idx = _cart.indexWhere((c) => c.id == id);
          if (idx >= 0) _cart[idx].itemDiscountPct = pct;
        }),
        onQtyChanged: _setQty,
        onRemove: _removeFromCart,
        onComplete:
            ({
              couponCode,
              giftCardCode,
              required giftCardAmount,
              required amountPaid,
            }) async {
              final pm = _paymentMethod;
              final dp = _discountType == 'pct' ? _discountValue : 0.0;
              final df = _discountType == 'flat' ? _discountValue : 0.0;
              return _completeSale(
                paymentMethod: pm,
                amountPaid: amountPaid,
                customer: _customer,
                discountPercent: dp,
                discountFlat: df,
                notes: _notes,
                creditDueDate: _creditDueDate,
                couponCode: couponCode,
                giftCardCode: giftCardCode,
                giftCardAmount: giftCardAmount,
              );
            },
      ),
    );
    if (!mounted || receipt == null) return;
    setState(() {
      _cart.clear();
      _customer = null;
      _discountValue = 0;
      _notes = '';
      _creditDueDate = null;
    });
    _paidCtrl.clear();
    final settings = _receiptRepository.loadSettings().catchError(
      (_) => const ReceiptSettings(isFallback: true),
    );
    // The checkout route is gone before we push the receipt. Close/New Sale
    // pops just this preview and returns to the now-empty POS.
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => SaleReceiptScreen(
          receipt: receipt,
          settings: settings,
          pdfService: widget.receiptPdfService,
        ),
      ),
    );
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: _PosAppBar(
        cartCount: _cartCount,
        parkedCount: _parked.length,
        onCartTap: _openCheckout,
        onParkTap: _cart.isNotEmpty ? _parkCurrentSale : null,
        onRecallTap: _parked.isNotEmpty ? _openParkedSalesModal : null,
        cartPulse: _cartPulse,
      ),
      body: Column(
        children: [
          _SearchBar(
            controller: _searchCtrl,
            onChanged: _onSearchChanged,
            onSubmitted: _onSearchSubmitted,
            onScan: _openScanner,
          ),
          if (_productError != null && _products.isNotEmpty)
            Container(
              width: double.infinity,
              color: AppColors.error.withValues(alpha: 0.08),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              child: Text(
                _productError!,
                style: const TextStyle(color: AppColors.error, fontSize: 12),
              ),
            ),
          if (_categories.isNotEmpty)
            _CategoryChips(
              categories: _categories,
              selected: _selectedCategoryId,
              onSelect: _selectCategory,
            ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _loadProducts(
                query: _searchCtrl.text.trim(),
                forceRefresh: true,
              ),
              child: _buildGrid(),
            ),
          ),
        ],
      ),
      bottomNavigationBar: _cartCount > 0
          ? _CartBar(
              count: _cartCount,
              total: _total,
              onCheckout: _openCheckout,
            )
          : null,
    );
  }

  Widget _buildGrid() {
    if (_loadingProducts && _products.isEmpty) {
      return const SizedBox.expand();
    }
    if (_productError != null && _products.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.wifi_off_rounded,
              size: 40,
              color: AppColors.textHint,
            ),
            const SizedBox(height: 8),
            Text(
              _productError!,
              style: const TextStyle(color: AppColors.textMuted),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => _loadProducts(),
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }
    if (_products.isEmpty) {
      return const Center(
        child: Text(
          'No products found.',
          style: TextStyle(color: AppColors.textMuted),
        ),
      );
    }
    return GridView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.75,
      ),
      itemCount: _products.length,
      itemBuilder: (context, i) {
        final p = _products[i];
        final inCart = _cart
            .where((c) => c.id == (p['id'] as num).toInt())
            .firstOrNull;
        return _ProductCard(
          product: p,
          cartQty: inCart?.qty ?? 0,
          onAdd: () => _addToCart(p),
        );
      },
    );
  }
}

// ── Receipt row ───────────────────────────────────────────────────────────────

// ── App bar ───────────────────────────────────────────────────────────────────

class _PosAppBar extends StatelessWidget implements PreferredSizeWidget {
  const _PosAppBar({
    required this.cartCount,
    required this.parkedCount,
    required this.onCartTap,
    required this.cartPulse,
    this.onParkTap,
    this.onRecallTap,
  });
  final int cartCount;
  final int parkedCount;
  final VoidCallback onCartTap;
  final VoidCallback? onParkTap;
  final VoidCallback? onRecallTap;
  final AnimationController cartPulse;

  @override
  Size get preferredSize => const Size.fromHeight(56);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: AppColors.surface,
      foregroundColor: AppColors.textDark,
      elevation: 0,
      centerTitle: true,
      title: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.primaryDk, AppColors.primary],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Icon(
              Icons.point_of_sale_rounded,
              color: Colors.white,
              size: 17,
            ),
          ),
          const SizedBox(width: 8),
          const Text(
            'Point of Sale',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
          ),
        ],
      ),
      actions: [
        if (onRecallTap != null)
          Stack(
            clipBehavior: Clip.none,
            children: [
              IconButton(
                icon: const Icon(Icons.pause_circle_outline_rounded, size: 22),
                tooltip: 'Recall parked sale',
                onPressed: onRecallTap,
              ),
              if (parkedCount > 0)
                Positioned(
                  top: 6,
                  right: 6,
                  child: Container(
                    width: 16,
                    height: 16,
                    decoration: const BoxDecoration(
                      color: AppColors.warning,
                      shape: BoxShape.circle,
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      '$parkedCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        if (onParkTap != null)
          IconButton(
            icon: const Icon(Icons.save_alt_rounded, size: 22),
            tooltip: 'Hold sale',
            onPressed: onParkTap,
          ),
        Padding(
          padding: const EdgeInsets.only(right: 12),
          child: GestureDetector(
            onTap: onCartTap,
            child: ScaleTransition(
              scale: cartPulse,
              child: Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: AppColors.primaryLt,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.shopping_cart_rounded,
                      color: AppColors.primary,
                      size: 20,
                    ),
                  ),
                  if (cartCount > 0)
                    Positioned(
                      top: -4,
                      right: -4,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          '$cartCount',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
      bottom: const PreferredSize(
        preferredSize: Size.fromHeight(1),
        child: Divider(height: 1, color: AppColors.border),
      ),
    );
  }
}

// ── Search bar ────────────────────────────────────────────────────────────────

class _SearchBar extends StatelessWidget {
  const _SearchBar({
    required this.controller,
    required this.onChanged,
    required this.onSubmitted,
    required this.onScan,
  });
  final TextEditingController controller;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) => Container(
    color: AppColors.surface,
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
    child: ValueListenableBuilder<TextEditingValue>(
      valueListenable: controller,
      builder: (context, value, _) => TextField(
        controller: controller,
        onChanged: onChanged,
        onSubmitted: onSubmitted,
        textInputAction: TextInputAction.search,
        decoration: InputDecoration(
          hintText: 'Search name or SKU, or scan',
          prefixIcon: const Icon(
            Icons.search_rounded,
            color: AppColors.textHint,
            size: 20,
          ),
          suffixIcon: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (value.text.isNotEmpty)
                IconButton(
                  tooltip: 'Clear',
                  icon: const Icon(Icons.close_rounded, size: 18),
                  onPressed: () {
                    controller.clear();
                    onChanged('');
                  },
                ),
              IconButton(
                tooltip: 'Scan barcode',
                icon: const Icon(
                  Icons.qr_code_scanner_rounded,
                  size: 22,
                  color: AppColors.primary,
                ),
                onPressed: onScan,
              ),
            ],
          ),
          contentPadding: const EdgeInsets.symmetric(
            vertical: 10,
            horizontal: 16,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(12),
            borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
          ),
          filled: true,
          fillColor: const Color(0xFFF8FAFC),
        ),
      ),
    ),
  );
}

// ── Category chips ─────────────────────────────────────────────────────────────

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({
    required this.categories,
    required this.selected,
    required this.onSelect,
  });
  final List<Map<String, dynamic>> categories;
  final int selected;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => Container(
    color: AppColors.surface,
    height: 38,
    child: ListView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      children: [
        _chip('All', 0),
        for (final c in categories)
          _chip(c['name'] as String? ?? '', (c['id'] as num).toInt()),
      ],
    ),
  );

  Widget _chip(String label, int id) {
    final active = selected == id;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: GestureDetector(
        onTap: () => onSelect(id),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: active ? AppColors.primary : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: active ? AppColors.primary : AppColors.border,
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: active ? Colors.white : AppColors.textMuted,
            ),
          ),
        ),
      ),
    );
  }
}

// ── Product card ──────────────────────────────────────────────────────────────

class _ProductCard extends StatelessWidget {
  const _ProductCard({
    required this.product,
    required this.cartQty,
    required this.onAdd,
  });
  final Map<String, dynamic> product;
  final int cartQty;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    final name = (product['name'] as String?) ?? '';
    final price =
        (product['discounted_sell_price'] as num?)?.toDouble() ??
        (product['unit_sell_price'] as num?)?.toDouble() ??
        0;
    final originalPrice = (product['unit_sell_price'] as num?)?.toDouble() ?? 0;
    final hasDiscount =
        product['discounted_sell_price'] != null && price < originalPrice;
    final stock = (product['stock_quantity'] as num?)?.toDouble();
    final outOfStock = stock != null && stock <= 0;
    final inCart = cartQty > 0;

    return GestureDetector(
      onTap: outOfStock ? null : onAdd,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: inCart
                ? AppColors.primary
                : (outOfStock
                      ? AppColors.border.withValues(alpha: 0.5)
                      : AppColors.border),
            width: inCart ? 1.5 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: inCart
                  ? AppColors.primary.withValues(alpha: 0.12)
                  : Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Opacity(
          opacity: outOfStock ? 0.55 : 1,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(13),
                  ),
                  child: _productImage(product),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(8, 6, 8, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textDark,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                formatMoney(price),
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primary,
                                ),
                              ),
                              if (hasDiscount)
                                Text(
                                  formatMoney(originalPrice),
                                  style: const TextStyle(
                                    fontSize: 10,
                                    color: AppColors.textHint,
                                    decoration: TextDecoration.lineThrough,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        if (inCart)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 5,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primary,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              '$cartQty',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                    if (outOfStock)
                      const Text(
                        'Out of stock',
                        style: TextStyle(fontSize: 9.5, color: AppColors.error),
                      )
                    else if (stock != null && stock <= 5)
                      Text(
                        '${stock.toInt()} left',
                        style: const TextStyle(
                          fontSize: 9.5,
                          color: AppColors.warning,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _productImage(Map<String, dynamic> p) {
    final url = p['image_url'] as String?;
    if (url != null && url.isNotEmpty) {
      return Image.network(
        url,
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => _placeholder(),
      );
    }
    return _placeholder();
  }

  Widget _placeholder() => Container(
    color: const Color(0xFFF1F5F9),
    child: const Center(
      child: Icon(
        Icons.inventory_2_outlined,
        color: AppColors.textHint,
        size: 28,
      ),
    ),
  );
}

// ── Cart bottom bar ───────────────────────────────────────────────────────────

class _CartBar extends StatelessWidget {
  const _CartBar({
    required this.count,
    required this.total,
    required this.onCheckout,
  });
  final int count;
  final double total;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
      child: GestureDetector(
        onTap: onCheckout,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppColors.primaryDk, AppColors.primary],
              begin: Alignment.centerLeft,
              end: Alignment.centerRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.4),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  '$count item${count == 1 ? '' : 's'}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const Spacer(),
              Text(
                formatMoney(total),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 10),
              const Icon(
                Icons.arrow_forward_ios_rounded,
                color: Colors.white,
                size: 14,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

// ── Parked sales sheet ────────────────────────────────────────────────────────

class _ParkedSalesSheet extends StatelessWidget {
  const _ParkedSalesSheet({
    required this.parked,
    required this.onRecall,
    required this.onDelete,
  });
  final List<_ParkedSale> parked;
  final ValueChanged<_ParkedSale> onRecall;
  final ValueChanged<_ParkedSale> onDelete;

  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Center(
          child: Container(
            width: 36,
            height: 4,
            margin: const EdgeInsets.only(top: 10, bottom: 14),
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
        ),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 20),
          child: Text(
            'Parked Sales',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: 8),
        ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 280),
          child: ListView.separated(
            shrinkWrap: true,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            itemCount: parked.length,
            separatorBuilder: (_, _) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final s = parked[i];
              final total = s.cart.fold(0.0, (sum, c) => sum + c.lineTotal);
              return ListTile(
                title: Text(
                  s.label,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  '${s.cart.fold(0, (sum, c) => sum + c.qty)} items · ${formatMoney(total)}',
                ),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextButton(
                      onPressed: () => onRecall(s),
                      child: const Text('Recall'),
                    ),
                    IconButton(
                      icon: const Icon(
                        Icons.delete_outline_rounded,
                        size: 18,
                        color: AppColors.error,
                      ),
                      onPressed: () => onDelete(s),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
        const SizedBox(height: 16),
      ],
    ),
  );
}

// ── Checkout sheet ────────────────────────────────────────────────────────────

class _CheckoutSheet extends StatefulWidget {
  const _CheckoutSheet({
    required this.cart,
    required this.total,
    required this.subtotalAfterItemDisc,
    required this.orderDiscount,
    required this.customer,
    required this.paymentMethod,
    required this.discountType,
    required this.discountValue,
    required this.notes,
    required this.creditDueDate,
    required this.paidCtrl,
    required this.change,
    required this.onCustomerChanged,
    required this.onPaymentMethodChanged,
    required this.onDiscountTypeChanged,
    required this.onDiscountValueChanged,
    required this.onNotesChanged,
    required this.onCreditDueDateChanged,
    required this.onPaidChanged,
    required this.onItemDiscountChanged,
    required this.onQtyChanged,
    required this.onRemove,
    required this.onComplete,
  });

  final List<_CartItem> cart;
  final double total;
  final double subtotalAfterItemDisc;
  final double orderDiscount;
  final Map<String, dynamic>? customer;
  final String paymentMethod;
  final String discountType;
  final double discountValue;
  final String notes;
  final String? creditDueDate;
  final TextEditingController paidCtrl;
  final double change;
  final ValueChanged<Map<String, dynamic>?> onCustomerChanged;
  final ValueChanged<String> onPaymentMethodChanged;
  final ValueChanged<String> onDiscountTypeChanged;
  final ValueChanged<double> onDiscountValueChanged;
  final ValueChanged<String> onNotesChanged;
  final ValueChanged<String?> onCreditDueDateChanged;
  final ValueChanged<String> onPaidChanged;
  final void Function(int id, double pct) onItemDiscountChanged;
  final void Function(int id, int qty) onQtyChanged;
  final ValueChanged<int> onRemove;
  final Future<SaleReceipt> Function({
    String? couponCode,
    String? giftCardCode,
    required double giftCardAmount,
    required double amountPaid,
  })
  onComplete;

  @override
  State<_CheckoutSheet> createState() => _CheckoutSheetState();
}

class _CheckoutSheetState extends State<_CheckoutSheet> {
  bool _submitting = false;
  String? _submitError;
  late Map<String, dynamic>? _customer;
  late String _paymentMethod;
  late String _discountType;
  late double _discountValue;
  late String _notes;
  late String? _creditDueDate;
  bool _showNotes = false;
  final _discCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _couponCtrl = TextEditingController();
  final _giftCardCtrl = TextEditingController();
  bool _showCouponEntry = false;
  bool _showGiftCardEntry = false;
  bool _lookingUpCoupon = false;
  bool _lookingUpGiftCard = false;
  String? _couponError;
  String? _giftCardError;
  _AppliedCoupon? _coupon;
  _AppliedGiftCard? _giftCard;

  @override
  void initState() {
    super.initState();
    _customer = widget.customer;
    _paymentMethod = widget.paymentMethod;
    _discountType = widget.discountType;
    _discountValue = widget.discountValue;
    _notes = widget.notes;
    _creditDueDate = widget.creditDueDate;
    _discCtrl.text = widget.discountValue > 0
        ? widget.discountValue.toString()
        : '';
    _notesCtrl.text = widget.notes;
  }

  @override
  void dispose() {
    _discCtrl.dispose();
    _notesCtrl.dispose();
    _couponCtrl.dispose();
    _giftCardCtrl.dispose();
    super.dispose();
  }

  double get _localSubtotalAfterItemDisc =>
      widget.cart.fold(0.0, (sum, item) => sum + item.lineTotal);

  double get _localOrderDiscount {
    if (_discountType == 'flat') {
      return _discountValue.clamp(0, _localSubtotalAfterItemDisc);
    }
    return _localSubtotalAfterItemDisc * _discountValue / 100;
  }

  double get _beforeCoupon =>
      (_localSubtotalAfterItemDisc - _localOrderDiscount).clamp(
        0,
        double.infinity,
      );

  double get _couponDiscount => _coupon?.discountFor(_beforeCoupon) ?? 0;

  double get _localTotal =>
      (_beforeCoupon - _couponDiscount).clamp(0, double.infinity);

  double get _giftCardApplied =>
      (_giftCard?.balance ?? 0).clamp(0, _localTotal).toDouble();

  double get _amountDue =>
      (_localTotal - _giftCardApplied).clamp(0, double.infinity);

  double get _localChange {
    final paid = double.tryParse(widget.paidCtrl.text) ?? 0;
    return paid - _amountDue;
  }

  static const _kPayMethods = [
    ('cash', 'Cash', Icons.payments_rounded),
    ('card', 'Card', Icons.credit_card_rounded),
    ('transfer', 'Transfer', Icons.account_balance_rounded),
    ('credit', 'Credit', Icons.schedule_send_rounded),
  ];

  void _syncPaidToAmountDue() {
    widget.paidCtrl.text = _amountDue.toStringAsFixed(2);
    widget.onPaidChanged(widget.paidCtrl.text);
  }

  Future<void> _applyCoupon() async {
    final code = _couponCtrl.text.trim();
    if (code.isEmpty) {
      setState(() => _couponError = 'Enter a coupon code.');
      return;
    }
    setState(() {
      _lookingUpCoupon = true;
      _couponError = null;
    });
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.couponLookup,
        params: {'code': code},
        bypassCache: true,
      );
      final body = response.data;
      final raw = body is Map ? body['data'] : null;
      if (raw is! Map) throw Exception('Coupon not found.');
      final data = Map<String, dynamic>.from(raw);
      if (data['is_redeemable'] != true) {
        throw Exception('This coupon is ${data['status'] ?? 'not available'}.');
      }
      final coupon = _AppliedCoupon(
        code: data['code']?.toString() ?? code,
        name: data['name']?.toString() ?? 'Coupon',
        type: data['discount_type']?.toString() ?? 'flat',
        value: (data['discount_value'] as num?)?.toDouble() ?? 0,
      );
      if (!mounted) return;
      setState(() {
        _coupon = coupon;
        _showCouponEntry = false;
        _couponError = null;
      });
      _syncPaidToAmountDue();
    } catch (error) {
      if (mounted) setState(() => _couponError = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _lookingUpCoupon = false);
    }
  }

  Future<void> _applyGiftCard() async {
    final code = _giftCardCtrl.text.trim();
    if (code.isEmpty) {
      setState(() => _giftCardError = 'Enter a gift card code.');
      return;
    }
    setState(() {
      _lookingUpGiftCard = true;
      _giftCardError = null;
    });
    try {
      final response = await ApiClient.instance.get(
        ApiEndpoints.giftCardLookup,
        params: {'code': code},
        bypassCache: true,
      );
      final body = response.data;
      final raw = body is Map ? body['data'] : null;
      if (raw is! Map) throw Exception('Gift card not found.');
      final data = Map<String, dynamic>.from(raw);
      if (data['is_redeemable'] != true) {
        throw Exception(
          'This gift card is ${data['status'] ?? 'not available'}.',
        );
      }
      final giftCard = _AppliedGiftCard(
        code: data['code']?.toString() ?? code,
        name: data['name']?.toString() ?? 'Gift card',
        balance: (data['balance'] as num?)?.toDouble() ?? 0,
      );
      if (!mounted) return;
      setState(() {
        _giftCard = giftCard;
        _showGiftCardEntry = false;
        _giftCardError = null;
      });
      _syncPaidToAmountDue();
    } catch (error) {
      if (mounted) setState(() => _giftCardError = apiErrorMessage(error));
    } finally {
      if (mounted) setState(() => _lookingUpGiftCard = false);
    }
  }

  Future<void> _submitSale() async {
    if (_submitting || _lookingUpCoupon || _lookingUpGiftCard) return;
    final amountPaid = _paymentMethod == 'cash'
        ? double.tryParse(widget.paidCtrl.text.trim())
        : _paymentMethod == 'credit'
        ? 0.0
        : _amountDue;
    if (amountPaid == null ||
        !amountPaid.isFinite ||
        amountPaid < 0 ||
        (_paymentMethod == 'cash' && amountPaid + 0.005 < _amountDue)) {
      setState(
        () => _submitError = 'Enter an amount paid that covers the amount due.',
      );
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _submitError = null;
    });
    try {
      final receipt = await widget.onComplete(
        couponCode: _coupon?.code,
        giftCardCode: _giftCard?.code,
        giftCardAmount: _giftCardApplied,
        amountPaid: amountPaid,
      );
      if (mounted) Navigator.pop(context, receipt);
    } catch (error) {
      if (mounted) {
        setState(() {
          _submitError = apiErrorMessage(error);
          _submitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_submitting,
      child: DraggableScrollableSheet(
        initialChildSize: 0.92,
        minChildSize: 0.5,
        maxChildSize: 0.95,
        builder: (_, scrollCtrl) => AbsorbPointer(
          absorbing: _submitting,
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
            ),
            child: Column(
              children: [
                // Handle
                Center(
                  child: Container(
                    width: 36,
                    height: 4,
                    margin: const EdgeInsets.only(top: 10, bottom: 4),
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                // Header
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 8,
                  ),
                  child: Row(
                    children: [
                      const Text(
                        'Checkout',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textDark,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${widget.cart.fold(0, (s, c) => s + c.qty)} items',
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      IconButton(
                        tooltip: 'Close checkout',
                        onPressed: _submitting
                            ? null
                            : () => Navigator.pop(context),
                        icon: const Icon(Icons.close_rounded, size: 20),
                      ),
                    ],
                  ),
                ),
                Expanded(
                  child: ListView(
                    controller: scrollCtrl,
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    children: [
                      // Cart items
                      _buildCartItems(),
                      const SizedBox(height: 12),

                      // Customer
                      _buildCustomerSection(),
                      const SizedBox(height: 12),

                      // Discount
                      _buildDiscountSection(),
                      const SizedBox(height: 12),

                      // Coupon and gift-card redemption
                      _buildCouponAndGiftCardSection(),
                      const SizedBox(height: 12),

                      // Payment method
                      _buildPaymentMethods(),
                      const SizedBox(height: 12),

                      // Cash / Credit sections
                      if (_paymentMethod == 'cash') _buildCashSection(),
                      if (_paymentMethod == 'credit') _buildCreditSection(),
                      if (_paymentMethod == 'cash' ||
                          _paymentMethod == 'credit')
                        const SizedBox(height: 12),

                      // Notes toggle
                      _buildNotesSection(),
                      const SizedBox(height: 16),

                      // Total summary
                      _buildTotalSummary(),
                      const SizedBox(height: 16),

                      // Error
                      if (_submitError != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF2F2),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              _submitError!,
                              style: const TextStyle(
                                color: AppColors.error,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ),

                      // Complete button
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: ElevatedButton.icon(
                          onPressed:
                              _submitting ||
                                  _lookingUpCoupon ||
                                  _lookingUpGiftCard ||
                                  widget.cart.isEmpty
                              ? null
                              : _submitSale,
                          icon: _submitting
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(Icons.check_circle_rounded),
                          label: Text(
                            _submitting ? 'Processing...' : 'Complete Sale',
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.success,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                            textStyle: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(height: MediaQuery.of(context).viewInsets.bottom),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCartItems() => Container(
    decoration: BoxDecoration(
      color: const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(
      children: [
        for (var i = 0; i < widget.cart.length; i++) ...[
          if (i > 0) const Divider(height: 1, indent: 16, endIndent: 16),
          _CartItemRow(
            item: widget.cart[i],
            onInc: () {
              widget.onQtyChanged(widget.cart[i].id, widget.cart[i].qty + 1);
              setState(() {});
              _syncPaidToAmountDue();
            },
            onDec: () {
              widget.onQtyChanged(widget.cart[i].id, widget.cart[i].qty - 1);
              setState(() {});
              _syncPaidToAmountDue();
            },
            onRemove: () {
              widget.onRemove(widget.cart[i].id);
              setState(() {});
              _syncPaidToAmountDue();
            },
            onDiscountChanged: (pct) {
              widget.onItemDiscountChanged(widget.cart[i].id, pct);
              setState(() {});
              _syncPaidToAmountDue();
            },
          ),
        ],
      ],
    ),
  );

  Widget _buildCustomerSection() {
    final customer = _customer;
    final phone = customer?['phone']?.toString().trim() ?? '';
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () async {
          final selected = await CustomerPickerSheet.show(context);
          if (!mounted || selected == null) return;
          setState(() => _customer = selected);
          widget.onCustomerChanged(selected);
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: customer != null ? AppColors.primary : AppColors.border,
            ),
          ),
          child: Row(
            children: [
              Icon(
                Icons.person_outline_rounded,
                size: 19,
                color: customer != null
                    ? AppColors.primary
                    : AppColors.textMuted,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      customer?['name']?.toString() ??
                          'Add customer (optional)',
                      style: TextStyle(
                        fontSize: 13,
                        color: customer != null
                            ? AppColors.textDark
                            : AppColors.textMuted,
                        fontWeight: customer != null
                            ? FontWeight.w600
                            : FontWeight.w400,
                      ),
                    ),
                    if (customer != null && phone.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        phone,
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (customer != null)
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: 'Remove customer',
                  onPressed: () {
                    setState(() => _customer = null);
                    widget.onCustomerChanged(null);
                  },
                  icon: const Icon(
                    Icons.close_rounded,
                    size: 18,
                    color: AppColors.textMuted,
                  ),
                )
              else
                const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.textMuted,
                ),
            ],
          ),
        ),
      ),
    );
  }

  void _changeDiscountType(String type) {
    if (_discountType == type) return;
    setState(() {
      _discountType = type;
      _discountValue = 0;
      _discCtrl.clear();
    });
    widget.onDiscountTypeChanged(type);
    _syncPaidToAmountDue();
  }

  void _setDiscountValue(double value, {bool updateField = false}) {
    final normalized = _discountType == 'pct'
        ? value.clamp(0, 100).toDouble()
        : value.clamp(0, double.infinity).toDouble();
    setState(() => _discountValue = normalized);
    if (updateField) {
      final text = normalized == normalized.roundToDouble()
          ? normalized.toStringAsFixed(0)
          : normalized.toStringAsFixed(2);
      _discCtrl.value = TextEditingValue(
        text: text,
        selection: TextSelection.collapsed(offset: text.length),
      );
    }
    widget.onDiscountValueChanged(normalized);
    _syncPaidToAmountDue();
  }

  void _stepDiscount(double direction) {
    final step = _discountType == 'pct' ? 1.0 : 10.0;
    _setDiscountValue(_discountValue + direction * step, updateField: true);
  }

  Widget _buildDiscountSection() => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(
      color: AppColors.primaryLt.withValues(alpha: 0.55),
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: AppColors.primary.withValues(alpha: 0.22)),
    ),
    child: Row(
      children: [
        const Icon(
          Icons.local_offer_rounded,
          size: 17,
          color: AppColors.primary,
        ),
        const SizedBox(width: 6),
        const Text(
          'DISCOUNT',
          style: TextStyle(
            fontSize: 11.5,
            letterSpacing: 0.5,
            fontWeight: FontWeight.w800,
            color: AppColors.textMuted,
          ),
        ),
        const Spacer(),
        Container(
          height: 42,
          padding: const EdgeInsets.all(3),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              _discountTypeButton(label: '%', type: 'pct'),
              _discountTypeButton(label: r'$', type: 'flat'),
            ],
          ),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 94,
          height: 42,
          child: TextField(
            controller: _discCtrl,
            textAlign: TextAlign.center,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,2}')),
            ],
            decoration: InputDecoration(
              hintText: _discountType == 'pct' ? '0' : '0.00',
              isDense: true,
              contentPadding: const EdgeInsets.only(
                left: 8,
                top: 11,
                bottom: 11,
              ),
              suffixIconConstraints: const BoxConstraints.tightFor(width: 28),
              suffixIcon: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _discountStepButton(
                    icon: Icons.keyboard_arrow_up_rounded,
                    onTap: () => _stepDiscount(1),
                  ),
                  _discountStepButton(
                    icon: Icons.keyboard_arrow_down_rounded,
                    onTap: () => _stepDiscount(-1),
                  ),
                ],
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(9),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(9),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(9),
                borderSide: const BorderSide(
                  color: AppColors.primary,
                  width: 1.5,
                ),
              ),
            ),
            onChanged: (value) {
              _setDiscountValue(double.tryParse(value) ?? 0);
            },
          ),
        ),
      ],
    ),
  );

  Widget _discountTypeButton({required String label, required String type}) {
    final selected = _discountType == type;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => _changeDiscountType(type),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 32,
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(7),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: selected ? Colors.white : AppColors.textMuted,
          ),
        ),
      ),
    );
  }

  Widget _discountStepButton({
    required IconData icon,
    required VoidCallback onTap,
  }) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTap: onTap,
    child: SizedBox(
      width: 28,
      height: 18,
      child: Icon(icon, size: 16, color: AppColors.textMuted),
    ),
  );

  Widget _buildCouponAndGiftCardSection() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (_coupon != null)
        _buildAppliedBenefit(
          icon: Icons.local_offer_rounded,
          color: const Color(0xFF0D9488),
          title: _coupon!.code,
          subtitle:
              '${_coupon!.name} · ${_coupon!.type == 'percent' ? '${_coupon!.value.toStringAsFixed(0)}%' : formatMoney(_coupon!.value)} off',
          amount: '−${formatMoney(_couponDiscount)}',
          onRemove: () {
            setState(() {
              _coupon = null;
              _couponCtrl.clear();
              _syncPaidToAmountDue();
            });
          },
        )
      else if (_showCouponEntry)
        _buildCodeEntry(
          controller: _couponCtrl,
          hint: 'Coupon code',
          color: const Color(0xFF0D9488),
          loading: _lookingUpCoupon,
          error: _couponError,
          onApply: _applyCoupon,
          onCancel: () => setState(() {
            _showCouponEntry = false;
            _couponError = null;
            _couponCtrl.clear();
          }),
        )
      else
        _buildBenefitButton(
          icon: Icons.local_offer_outlined,
          label: 'Apply coupon',
          onTap: () => setState(() => _showCouponEntry = true),
        ),
      const SizedBox(height: 8),
      if (_giftCard != null)
        _buildAppliedBenefit(
          icon: Icons.card_giftcard_rounded,
          color: const Color(0xFFDB2777),
          title: _giftCard!.code,
          subtitle:
              '${_giftCard!.name} · Balance ${formatMoney(_giftCard!.balance)}',
          amount: '−${formatMoney(_giftCardApplied)}',
          onRemove: () {
            setState(() {
              _giftCard = null;
              _giftCardCtrl.clear();
              _syncPaidToAmountDue();
            });
          },
        )
      else if (_showGiftCardEntry)
        _buildCodeEntry(
          controller: _giftCardCtrl,
          hint: 'Gift card code',
          color: const Color(0xFFDB2777),
          loading: _lookingUpGiftCard,
          error: _giftCardError,
          onApply: _applyGiftCard,
          onCancel: () => setState(() {
            _showGiftCardEntry = false;
            _giftCardError = null;
            _giftCardCtrl.clear();
          }),
        )
      else
        _buildBenefitButton(
          icon: Icons.card_giftcard_rounded,
          label: 'Pay with gift card',
          onTap: () => setState(() => _showGiftCardEntry = true),
        ),
    ],
  );

  Widget _buildBenefitButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) => GestureDetector(
    behavior: HitTestBehavior.opaque,
    onTap: onTap,
    child: SizedBox(
      width: double.infinity,
      height: 44,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 17, color: AppColors.textMuted),
            const SizedBox(width: 7),
            Text(
              label,
              style: const TextStyle(
                color: AppColors.textMuted,
                fontSize: 13,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    ),
  );

  Widget _buildCodeEntry({
    required TextEditingController controller,
    required String hint,
    required Color color,
    required bool loading,
    required String? error,
    required Future<void> Function() onApply,
    required VoidCallback onCancel,
  }) => CheckoutCodeEntry(
    controller: controller,
    hint: hint,
    color: color,
    loading: loading,
    error: error,
    onApply: onApply,
    onCancel: onCancel,
  );

  Widget _buildAppliedBenefit({
    required IconData icon,
    required Color color,
    required String title,
    required String subtitle,
    required String amount,
    required VoidCallback onRemove,
  }) => Container(
    padding: const EdgeInsets.fromLTRB(12, 9, 6, 9),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.07),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: color.withValues(alpha: 0.35)),
    ),
    child: Row(
      children: [
        Icon(icon, size: 19, color: color),
        const SizedBox(width: 9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textDark,
                ),
              ),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  color: AppColors.textMuted,
                ),
              ),
            ],
          ),
        ),
        Text(
          amount,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
        IconButton(
          onPressed: onRemove,
          icon: const Icon(Icons.close_rounded, size: 18),
          color: AppColors.textMuted,
          tooltip: 'Remove',
        ),
      ],
    ),
  );

  Widget _buildPaymentMethods() => Row(
    children: [
      for (final m in _kPayMethods) ...[
        Expanded(
          child: GestureDetector(
            onTap: () {
              setState(() => _paymentMethod = m.$1);
              widget.onPaymentMethodChanged(m.$1);
            },
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 150),
              padding: const EdgeInsets.symmetric(vertical: 9),
              margin: const EdgeInsets.only(right: 6),
              decoration: BoxDecoration(
                color: _paymentMethod == m.$1
                    ? AppColors.primaryLt
                    : const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: _paymentMethod == m.$1
                      ? AppColors.primary
                      : AppColors.border,
                  width: _paymentMethod == m.$1 ? 1.5 : 1,
                ),
              ),
              child: Column(
                children: [
                  Icon(
                    m.$3,
                    size: 18,
                    color: _paymentMethod == m.$1
                        ? AppColors.primary
                        : AppColors.textMuted,
                  ),
                  const SizedBox(height: 3),
                  Text(
                    m.$2,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      color: _paymentMethod == m.$1
                          ? AppColors.primary
                          : AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ],
  );

  Widget _buildCashSection() => ValueListenableBuilder<TextEditingValue>(
    valueListenable: widget.paidCtrl,
    // Checkout is a separate modal route; rebuilding the POS behind it does
    // not refresh Change. Listen here for typed and programmatic amount edits.
    builder: (_, value, child) => Row(
      children: [
        Expanded(
          child: TextField(
            controller: widget.paidCtrl,
            onChanged: widget.onPaidChanged,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
            ],
            decoration: const InputDecoration(
              labelText: 'Amount paid',
              prefixIcon: Icon(Icons.payments_outlined, size: 18),
              contentPadding: EdgeInsets.symmetric(
                vertical: 10,
                horizontal: 12,
              ),
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _localChange >= 0
                  ? const Color(0xFFF0FDF4)
                  : const Color(0xFFFEF2F2),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: _localChange >= 0 ? AppColors.success : AppColors.error,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Change',
                  style: TextStyle(
                    fontSize: 11,
                    color: _localChange >= 0
                        ? AppColors.success
                        : AppColors.error,
                  ),
                ),
                Text(
                  formatMoney(_localChange.abs()),
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: _localChange >= 0
                        ? AppColors.success
                        : AppColors.error,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );

  Widget _buildCreditSection() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text(
        'Credit due date (optional)',
        style: TextStyle(fontSize: 12, color: AppColors.textMuted),
      ),
      const SizedBox(height: 6),
      GestureDetector(
        onTap: () async {
          final now = DateTime.now();
          final picked = await showDatePicker(
            context: context,
            initialDate: now.add(const Duration(days: 30)),
            firstDate: now,
            lastDate: now.add(const Duration(days: 365)),
          );
          if (picked != null) {
            final d =
                '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
            setState(() => _creditDueDate = d);
            widget.onCreditDueDateChanged(d);
          }
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.calendar_today_outlined,
                size: 16,
                color: AppColors.textMuted,
              ),
              const SizedBox(width: 10),
              Text(
                _creditDueDate ?? 'Select due date',
                style: TextStyle(
                  fontSize: 13,
                  color: _creditDueDate != null
                      ? AppColors.textDark
                      : AppColors.textMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );

  Widget _buildNotesSection() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      GestureDetector(
        onTap: () => setState(() => _showNotes = !_showNotes),
        child: Row(
          children: [
            const Icon(
              Icons.note_outlined,
              size: 16,
              color: AppColors.textMuted,
            ),
            const SizedBox(width: 6),
            Text(
              _notes.isNotEmpty ? 'Note: $_notes' : 'Add sale note',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                color: _notes.isNotEmpty
                    ? AppColors.textDark
                    : AppColors.textMuted,
              ),
            ),
            const Spacer(),
            Icon(
              _showNotes
                  ? Icons.keyboard_arrow_up_rounded
                  : Icons.keyboard_arrow_down_rounded,
              color: AppColors.textMuted,
              size: 18,
            ),
          ],
        ),
      ),
      if (_showNotes)
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: TextField(
            controller: _notesCtrl,
            maxLines: 2,
            decoration: InputDecoration(
              hintText: 'Note...',
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 10,
              ),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: AppColors.border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: const BorderSide(color: AppColors.primary),
              ),
            ),
            onChanged: (v) {
              setState(() => _notes = v);
              widget.onNotesChanged(v);
            },
          ),
        ),
    ],
  );

  Widget _buildTotalSummary() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: const Color(0xFFF8FAFC),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(
      children: [
        if (widget.cart.any((c) => c.itemDiscountPct > 0))
          _SummaryRow(
            'Item discounts',
            '−${formatMoney(widget.cart.fold(0.0, (s, c) => s + (c.basePrice - c.effectivePrice) * c.qty))}',
            color: AppColors.success,
          ),
        if (_discountValue > 0)
          _SummaryRow(
            'Order discount',
            '−${formatMoney(_localOrderDiscount)}',
            color: AppColors.success,
          ),
        if (_coupon != null)
          _SummaryRow(
            'Coupon ${_coupon!.code}',
            '-${formatMoney(_couponDiscount)}',
            color: AppColors.success,
          ),
        _SummaryRow(
          'Total',
          formatMoney(_localTotal),
          bold: _giftCard == null,
          large: _giftCard == null,
          color: _giftCard == null ? AppColors.primary : AppColors.textDark,
        ),
        if (_giftCard != null) ...[
          _SummaryRow(
            'Gift card ${_giftCard!.code}',
            '-${formatMoney(_giftCardApplied)}',
            color: const Color(0xFFDB2777),
          ),
          _SummaryRow(
            'Amount due',
            formatMoney(_amountDue),
            bold: true,
            large: true,
            color: AppColors.primary,
          ),
        ],
      ],
    ),
  );
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow(
    this.label,
    this.value, {
    this.bold = false,
    this.large = false,
    this.color,
  });
  final String label;
  final String value;
  final bool bold;
  final bool large;
  final Color? color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: large ? 15 : 13,
            fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
            color: AppColors.textMuted,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: large ? 17 : 13,
            fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
            color: color ?? AppColors.textDark,
          ),
        ),
      ],
    ),
  );
}

// ── Cart item row (in checkout) ───────────────────────────────────────────────

class _CartItemRow extends StatefulWidget {
  const _CartItemRow({
    required this.item,
    required this.onInc,
    required this.onDec,
    required this.onRemove,
    required this.onDiscountChanged,
  });
  final _CartItem item;
  final VoidCallback onInc;
  final VoidCallback onDec;
  final VoidCallback onRemove;
  final ValueChanged<double> onDiscountChanged;

  @override
  State<_CartItemRow> createState() => _CartItemRowState();
}

class _CartItemRowState extends State<_CartItemRow> {
  bool _expanded = false;
  late final TextEditingController _discCtrl;

  @override
  void initState() {
    super.initState();
    _discCtrl = TextEditingController(
      text: widget.item.itemDiscountPct > 0
          ? widget.item.itemDiscountPct.toStringAsFixed(0)
          : '',
    );
  }

  @override
  void dispose() {
    _discCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            children: [
              // Name + discount badge
              Expanded(
                child: GestureDetector(
                  onTap: () => setState(() => _expanded = !_expanded),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.item.name,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textDark,
                        ),
                      ),
                      if (widget.item.itemDiscountPct > 0)
                        Text(
                          '${widget.item.itemDiscountPct.toStringAsFixed(0)}% off',
                          style: const TextStyle(
                            fontSize: 10.5,
                            color: AppColors.success,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              // Qty controls
              Row(
                children: [
                  _QtyBtn(icon: Icons.remove_rounded, onTap: widget.onDec),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    child: Text(
                      '${widget.item.qty}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  _QtyBtn(icon: Icons.add_rounded, onTap: widget.onInc),
                ],
              ),
              const SizedBox(width: 10),
              // Line total
              Text(
                formatMoney(widget.item.lineTotal),
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 6),
              // Remove
              GestureDetector(
                onTap: widget.onRemove,
                child: const Icon(
                  Icons.close_rounded,
                  size: 16,
                  color: AppColors.textHint,
                ),
              ),
            ],
          ),
        ),
        if (_expanded)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
            child: Row(
              children: [
                const Text(
                  'Item discount %:',
                  style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                ),
                const SizedBox(width: 8),
                SizedBox(
                  width: 70,
                  child: TextField(
                    controller: _discCtrl,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                    ],
                    decoration: InputDecoration(
                      suffixText: '%',
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 6,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(6),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(6),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(6),
                        borderSide: const BorderSide(color: AppColors.primary),
                      ),
                    ),
                    onChanged: (v) {
                      final val = (double.tryParse(v) ?? 0)
                          .clamp(0, 100)
                          .toDouble();
                      widget.onDiscountChanged(val);
                    },
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _QtyBtn extends StatelessWidget {
  const _QtyBtn({required this.icon, required this.onTap});
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: 28,
      height: 28,
      decoration: BoxDecoration(
        color: AppColors.primaryLt,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Icon(icon, size: 14, color: AppColors.primary),
    ),
  );
}

// ── Helpers ───────────────────────────────────────────────────────────────────
