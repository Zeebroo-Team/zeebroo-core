import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/core/theme/app_theme.dart';
import 'package:zeebroo_mobile/features/home/widgets/glass_bottom_nav.dart';

void main() {
  testWidgets('Home is left and POS is centered', (tester) async {
    tester.view.physicalSize = const Size(320, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    int? tappedIndex;
    var posTapped = false;
    var scannerTapped = false;

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          bottomNavigationBar: GlassBottomNav(
            tabs: const [
              NavTabData(
                label: 'Home',
                icon: Icons.home_outlined,
                activeIcon: Icons.home_rounded,
              ),
            ],
            currentIndex: 0,
            onTap: (index) => tappedIndex = index,
            onPosTap: () => posTapped = true,
            onScannerTap: () => scannerTapped = true,
          ),
        ),
      ),
    );

    expect(find.text('Home'), findsOneWidget);
    expect(find.text('POS'), findsOneWidget);
    expect(find.text('Scanner'), findsOneWidget);
    expect(
      tester.widget<Icon>(find.byIcon(Icons.qr_code_scanner_rounded)).color,
      AppColors.primary,
    );
    expect(
      tester.widget<Text>(find.text('Scanner')).style!.color,
      tester.widget<Text>(find.text('Home')).style!.color,
    );
    for (final label in ['Point of Sale', 'Sales', 'Products', 'Financial']) {
      expect(find.text(label), findsNothing);
    }

    await tester.tap(find.text('Home'));
    await tester.pump();

    expect(tappedIndex, 0);

    final screenCenter = tester.getCenter(find.byType(Scaffold)).dx;
    expect(tester.getCenter(find.text('Home')).dx, lessThan(80));
    expect(tester.getCenter(find.text('POS')).dx, closeTo(screenCenter, 0.5));

    await tester.tap(find.text('POS'));
    await tester.pump();
    expect(posTapped, isTrue);
    await tester.tap(find.text('Scanner'));
    await tester.pump();
    expect(scannerTapped, isTrue);
    expect(tester.takeException(), isNull);
  });
}
