import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../core/auth/auth_state.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/gradient_header.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;
    final name  = user?['name']  as String? ?? 'User';
    final email = user?['email'] as String? ?? '';

    return Scaffold(
      body: Column(
        children: [
          GradientHeader(
            title: 'Settings',
            child: Row(
              children: [
                CircleAvatar(
                  radius: 26,
                  backgroundColor: Colors.white.withValues(alpha: 0.2),
                  child: Text(name[0].toUpperCase(),
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w700,
                        color: Colors.white)),
                ),
                const SizedBox(width: 14),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, style: const TextStyle(fontSize: 16,
                        fontWeight: FontWeight.w700, color: Colors.white)),
                    Text(email, style: TextStyle(fontSize: 12,
                        color: Colors.white.withValues(alpha: 0.72))),
                  ],
                ),
              ],
            ),
          ),

          Expanded(
            child: ListView(
              children: [
                const _Section('Account'),
                _Tile(icon: Icons.person_outline,   label: 'Edit Profile',    onTap: () {}),
                _Tile(icon: Icons.lock_outline,     label: 'Change Password', onTap: () {}),
                _Tile(icon: Icons.business_outlined, label: 'Business Info',  onTap: () {}),

                const _Section('Preferences'),
                _Tile(icon: Icons.notifications_outlined, label: 'Notifications', onTap: () {}),
                _Tile(icon: Icons.language_outlined,      label: 'Language',      onTap: () {}),

                const _Section('Support'),
                _Tile(icon: Icons.help_outline,    label: 'Help & Support',  onTap: () {}),
                _Tile(icon: Icons.info_outline,    label: 'About',           onTap: () {}),

                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.error,
                      side: const BorderSide(color: AppColors.error),
                      minimumSize: const Size.fromHeight(48),
                    ),
                    icon: const Icon(Icons.logout_rounded),
                    label: const Text('Sign Out'),
                    onPressed: () => showDialog(
                      context: context,
                      builder: (_) => AlertDialog(
                        title: const Text('Sign out?'),
                        content: const Text('You will be returned to the login screen.'),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context),
                            child: const Text('Cancel'),
                          ),
                          TextButton(
                            onPressed: () {
                              Navigator.pop(context);
                              context.read<AuthState>().logout();
                            },
                            child: const Text('Sign Out',
                                style: TextStyle(color: AppColors.error)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 24),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section(this.label);
  final String label;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 4),
    child: Text(label.toUpperCase(),
      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700,
          color: AppColors.textHint, letterSpacing: 1.1)),
  );
}

class _Tile extends StatelessWidget {
  const _Tile({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ListTile(
    leading: Container(
      width: 36, height: 36,
      decoration: BoxDecoration(
        color: AppColors.purpleLight,
        borderRadius: BorderRadius.circular(9),
      ),
      child: Icon(icon, size: 18, color: AppColors.primary),
    ),
    title: Text(label, style: const TextStyle(fontSize: 14,
        fontWeight: FontWeight.w500, color: AppColors.textDark)),
    trailing: const Icon(Icons.chevron_right, size: 18, color: AppColors.textHint),
    onTap: onTap,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
  );
}
