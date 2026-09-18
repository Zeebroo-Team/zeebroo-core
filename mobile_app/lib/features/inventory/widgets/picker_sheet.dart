import 'package:flutter/material.dart';

import '../../../core/api/api_client.dart';
import '../../../core/theme/app_theme.dart';

/// Reusable searchable "pick from list" bottom sheet, used for category/
/// brand multi-select, product/supplier/branch single-select, etc. across
/// the Inventory tabs. Always pops a `List<Map<String, dynamic>>` — empty
/// if dismissed, one item for single-select, N items for multi-select.
class PickerSheet extends StatefulWidget {
  const PickerSheet({
    super.key,
    required this.title,
    required this.fetch,
    required this.idOf,
    required this.labelOf,
    this.subtitleOf,
    this.multi = false,
    this.initialSelectedIds = const [],
    this.searchable = true,
  });

  final String title;
  final Future<List<Map<String, dynamic>>> Function(String query) fetch;
  final Object? Function(Map<String, dynamic> item) idOf;
  final String Function(Map<String, dynamic> item) labelOf;
  final String? Function(Map<String, dynamic> item)? subtitleOf;
  final bool multi;
  final List<Object?> initialSelectedIds;
  final bool searchable;

  @override
  State<PickerSheet> createState() => _PickerSheetState();
}

class _PickerSheetState extends State<PickerSheet> {
  final _searchController = TextEditingController();
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  late final Set<Object?> _selectedIds = {...widget.initialSelectedIds};

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _items = await widget.fetch(_searchController.text.trim());
    } catch (e) {
      _error = apiErrorMessage(e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _toggle(Object? id) {
    setState(() {
      if (widget.multi) {
        if (_selectedIds.contains(id)) {
          _selectedIds.remove(id);
        } else {
          _selectedIds.add(id);
        }
      } else {
        Navigator.pop(
          context,
          _items.where((i) => widget.idOf(i) == id).toList(),
        );
      }
    });
  }

  void _confirm() {
    Navigator.pop(
      context,
      _items.where((i) => _selectedIds.contains(widget.idOf(i))).toList(),
    );
  }

  @override
  Widget build(BuildContext context) => Container(
    height: MediaQuery.of(context).size.height * 0.75,
    decoration: const BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    child: SafeArea(
      top: false,
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    widget.title,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textDark,
                    ),
                  ),
                ),
                if (widget.multi)
                  TextButton(onPressed: _confirm, child: const Text('Done')),
              ],
            ),
          ),
          if (widget.searchable)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
              child: TextField(
                controller: _searchController,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _load(),
                decoration: InputDecoration(
                  hintText: 'Search',
                  prefixIcon: const Icon(Icons.search_rounded, size: 20),
                  suffixIcon: IconButton(
                    icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                    onPressed: _load,
                  ),
                ),
              ),
            ),
          Expanded(child: _buildBody()),
        ],
      ),
    ),
  );

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 10),
              TextButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return const Center(
        child: Text(
          'No results.',
          style: TextStyle(color: AppColors.textMuted, fontSize: 13),
        ),
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 20),
      itemCount: _items.length,
      separatorBuilder: (_, _) => const Divider(height: 1, indent: 12),
      itemBuilder: (context, i) {
        final item = _items[i];
        final id = widget.idOf(item);
        final selected = _selectedIds.contains(id);
        final subtitle = widget.subtitleOf?.call(item);
        return ListTile(
          onTap: () => _toggle(id),
          title: Text(
            widget.labelOf(item),
            style: const TextStyle(
              fontSize: 14.5,
              fontWeight: FontWeight.w600,
              color: AppColors.textDark,
            ),
          ),
          subtitle: (subtitle != null && subtitle.isNotEmpty)
              ? Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textMuted,
                  ),
                )
              : null,
          trailing: widget.multi
              ? Icon(
                  selected
                      ? Icons.check_circle_rounded
                      : Icons.circle_outlined,
                  color: selected ? AppColors.primary : AppColors.border,
                )
              : (selected
                    ? const Icon(
                        Icons.check_circle_rounded,
                        color: AppColors.primary,
                      )
                    : null),
        );
      },
    );
  }
}

/// Helper to parse a `{data: [...]}` or bare-array API response body into a
/// `List<Map<String, dynamic>>`, matching the shape every list endpoint used
/// by the Inventory tabs returns.
List<Map<String, dynamic>> parseListData(dynamic body) {
  final list = (body is Map ? body['data'] : body) as List? ?? [];
  return list.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
}
