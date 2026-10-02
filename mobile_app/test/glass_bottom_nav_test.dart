import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:zeebroo_mobile/features/home/widgets/glass_bottom_nav.dart';

void main() {
  testWidgets('Home is left and POS is centered', (tester) async {
    int? tappedIndex;
    var posTapped = false;

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
          ),
        ),
      ),
    );

    await tester.tap(find.text('Home'));
    await tester.pump();

    expect(tappedIndex, 0);

    final screenCenter = tester.getCenter(find.byType(Scaffold)).dx;
    expect(tester.getCenter(find.text('Home')).dx, lessThan(80));
    expect(tester.getCenter(find.text('POS')).dx, closeTo(screenCenter, 0.5));

    await tester.tap(find.text('POS'));
    await tester.pump();
    expect(posTapped, isTrue);
  });
}
