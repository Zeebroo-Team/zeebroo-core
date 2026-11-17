import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';

import '../theme/app_theme.dart';

/// Contacts icon for a customer/supplier form's name field: opens the phone's
/// contact picker and fills name, mobile, email and address from the chosen
/// contact. Anything the contact doesn't have is cleared (left empty).
///
/// With [contactPerson] (supplier forms), [name] gets the contact's company
/// when one is saved, and [contactPerson] gets the person's name.
///
/// Use as `suffixIcon: ContactAutofillButton.maybe(...)` — it returns null on
/// web/desktop, where there is no phone book to read.
class ContactAutofillButton extends StatelessWidget {
  const ContactAutofillButton._({
    required this.name,
    required this.phone,
    required this.email,
    required this.address,
    required this.contactPerson,
    required this.enabled,
  });

  static Widget? maybe({
    required TextEditingController name,
    required TextEditingController phone,
    required TextEditingController email,
    required TextEditingController address,
    TextEditingController? contactPerson,
    bool enabled = true,
  }) => supported
      ? ContactAutofillButton._(
          name: name,
          phone: phone,
          email: email,
          address: address,
          contactPerson: contactPerson,
          enabled: enabled,
        )
      : null;

  /// The phone-book picker exists only in the Android/iOS builds.
  static bool get supported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  final TextEditingController name;
  final TextEditingController phone;
  final TextEditingController email;
  final TextEditingController address;
  final TextEditingController? contactPerson;
  final bool enabled;

  @override
  Widget build(BuildContext context) => IconButton(
    key: const ValueKey('pick-from-contacts'),
    tooltip: 'Choose from contacts',
    onPressed: enabled ? () => _pick(context) : null,
    icon: const Icon(Icons.contacts_rounded, color: AppColors.primaryDk),
  );

  Future<void> _pick(BuildContext context) async {
    FocusScope.of(context).unfocus();
    final messenger = ScaffoldMessenger.of(context);
    try {
      // Android only returns phone/email/address with READ_CONTACTS; without
      // it the picker still works but gives just the name.
      final status = await FlutterContacts.permissions.request(
        PermissionType.read,
      );
      final canReadDetails =
          status == PermissionStatus.granted ||
          status == PermissionStatus.limited;
      final contact = await FlutterContacts.native.showPicker(
        properties: canReadDetails
            ? {
                ContactProperty.name,
                ContactProperty.phone,
                ContactProperty.email,
                ContactProperty.address,
                if (contactPerson != null) ContactProperty.organization,
              }
            : null,
      );
      if (contact == null) return; // user cancelled

      final person = contact.displayName?.trim() ?? '';
      if (contactPerson case final contactPerson?) {
        final company = contact.organizations
            .map((o) => o.name?.trim() ?? '')
            .where((n) => n.isNotEmpty)
            .firstOrNull;
        name.text = company ?? person;
        contactPerson.text = person;
      } else {
        name.text = person;
      }
      phone.text = _phoneOf(contact.phones);
      email.text = contact.emails.isEmpty
          ? ''
          : contact.emails.first.address.trim();
      address.text = contact.addresses.isEmpty
          ? ''
          : _addressOf(contact.addresses.first);

      if (!canReadDetails) {
        messenger.showSnackBar(
          const SnackBar(
            content: Text(
              'Only the name was filled. Allow contacts access to also fill mobile, email and address.',
            ),
            action: SnackBarAction(
              label: 'Settings',
              onPressed: _openSettings,
            ),
          ),
        );
      }
    } catch (_) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Couldn’t open your contacts.')),
      );
    }
  }

  static void _openSettings() => FlutterContacts.permissions.openSettings();

  /// Prefers a number labelled "mobile", then the primary one, then the first.
  static String _phoneOf(List<Phone> phones) {
    if (phones.isEmpty) return '';
    final phone =
        phones.where((p) => p.label.label == PhoneLabel.mobile).firstOrNull ??
        phones.where((p) => p.isPrimary == true).firstOrNull ??
        phones.first;
    return phone.number.replaceAll(RegExp(r'[\s\-().]'), '');
  }

  static String _addressOf(Address address) {
    final formatted = address.formatted?.trim() ?? '';
    if (formatted.isNotEmpty) return formatted;
    return [
      address.street,
      address.city,
      address.state,
      address.postalCode,
      address.country,
    ].whereType<String>().map((s) => s.trim()).where((s) => s.isNotEmpty).join(', ');
  }
}
