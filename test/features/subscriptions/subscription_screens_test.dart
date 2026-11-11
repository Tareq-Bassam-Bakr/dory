import 'package:dory_app/app/localization/generated/app_localizations.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/storage/in_memory_key_value_storage.dart';
import 'package:dory_app/core/storage/storage_keys.dart';
import 'package:dory_app/features/subscriptions/data/api_subscription_repository.dart';
import 'package:dory_app/features/subscriptions/domain/subscription.dart';
import 'package:dory_app/features/subscriptions/presentation/pages/plans_page.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

import '../../helpers/api_fixtures.dart';
import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_app.dart';

/// Plans and billing (docs/SUBSCRIPTIONS.md): the app shows what the server
/// decided, offers the owner the changes the server allows, and explains a
/// reached limit with a way to upgrade.
void main() {
  const Map<String, Object?> billingPermissions = <String, Object?>{
    ...ownerPermissions,
    'view_subscription': true,
    'manage_billing': true,
  };

  Map<String, Object?> price(String period, int minor) => <String, Object?>{
    'billing_period': period,
    'currency': 'USD',
    'amount_minor': minor,
    'amount': '${minor ~/ 100}.${(minor % 100).toString().padLeft(2, '0')}',
  };

  Map<String, Object?> planJson(String code) => switch (code) {
    'free' => <String, Object?>{
      'code': 'free',
      'name': 'Free',
      'names': <String, Object?>{'en': 'Free', 'ar': 'مجاني'},
      'description': 'One location with the essentials.',
      'descriptions': <String, Object?>{
        'en': 'One location with the essentials.',
        'ar': 'فرع واحد مع الأساسيات.',
      },
      'is_free': true,
      'limits': <String, Object?>{'branches': 1, 'queues': 3, 'members': 3},
      'features': <Object?>[],
      'prices': <Object?>[],
      'sort_order': 1,
    },
    'pro' => <String, Object?>{
      'code': 'pro',
      'name': 'Pro',
      'names': <String, Object?>{'en': 'Pro', 'ar': 'احترافي'},
      'description': 'For growing businesses.',
      'descriptions': <String, Object?>{
        'en': 'For growing businesses.',
        'ar': 'للأعمال النامية.',
      },
      'is_free': false,
      'limits': <String, Object?>{'branches': 3, 'queues': 15, 'members': 10},
      'features': <Object?>['insights'],
      'prices': <Object?>[price('monthly', 1500), price('yearly', 15000)],
      'sort_order': 2,
    },
    _ => <String, Object?>{
      'code': 'business',
      'name': 'Business',
      'names': <String, Object?>{'en': 'Business', 'ar': 'الأعمال'},
      'description': 'For chains.',
      'descriptions': <String, Object?>{'en': 'For chains.', 'ar': 'للسلاسل.'},
      'is_free': false,
      'limits': <String, Object?>{
        'branches': null,
        'queues': null,
        'members': null,
      },
      'features': <Object?>['insights', 'branch_insights'],
      'prices': <Object?>[price('monthly', 4900), price('yearly', 49000)],
      'sort_order': 3,
    },
  };

  Map<String, Object?> catalogJson({bool online = false}) => <String, Object?>{
    'plans': <Object?>[planJson('free'), planJson('pro'), planJson('business')],
    'billing_enabled': true,
    'default_currency': 'USD',
    'trial': <String, Object?>{'plan': 'pro', 'days': 14},
    'grace_days': 7,
    'payments': <String, Object?>{
      'online': online,
      'provider': online ? 'mastercard' : 'none',
    },
  };

  String inDays(int days) => DateTime.now()
      .toUtc()
      .add(Duration(days: days))
      .subtract(const Duration(minutes: 1))
      .toIso8601String();

  Map<String, Object?> subscriptionJson({
    String status = 'trialing',
    String plan = 'pro',
    bool paid = false,
    bool manage = true,
    bool online = false,
    bool cancel = false,
    String? scheduled,
    Map<String, Object?> usage = const <String, Object?>{
      'branches': 1,
      'queues': 2,
      'members': 1,
    },
  }) {
    final Map<String, Object?> current = planJson(plan);
    return <String, Object?>{
      'status': status,
      'billing_enabled': true,
      'plan': current,
      'subscribed_plan': <String, Object?>{
        'code': plan,
        'name': current['name'],
        'names': current['names'],
        'is_free': current['is_free'],
      },
      'is_paid': paid,
      'is_complimentary': false,
      'trial_ends_at': status == 'trialing' ? inDays(10) : null,
      'current_period_start': paid ? '2026-11-11T10:00:00+00:00' : null,
      'current_period_end': paid ? '2026-12-11T10:00:00+00:00' : null,
      'grace_ends_at': null,
      'cancel_at_period_end': cancel,
      'scheduled_plan': scheduled == null
          ? null
          : <String, Object?>{
              'code': scheduled,
              'name': planJson(scheduled)['name'],
              'names': planJson(scheduled)['names'],
            },
      'scheduled_billing_period': scheduled == null ? null : 'monthly',
      'billing_period': paid ? 'monthly' : null,
      'currency': paid ? 'USD' : null,
      'amount_minor': paid ? (plan == 'business' ? 4900 : 1500) : null,
      'amount': paid ? (plan == 'business' ? '49.00' : '15.00') : null,
      'limits': current['limits'],
      'usage': usage,
      'features': current['features'],
      'payments': <String, Object?>{
        'online': online,
        'provider': online ? 'mastercard' : 'none',
      },
      'permissions': <String, Object?>{
        'manage_billing': manage,
        'view_billing': manage,
      },
    };
  }

  FakeHttpAdapter api({
    Map<String, Object?>? subscription,
    bool online = false,
  }) => FakeHttpAdapter()
    ..reply(
      'GET',
      '/businesses/manage',
      FakeResponse.ok(<Object?>[businessJson(permissions: billingPermissions)]),
    )
    ..reply(
      'GET',
      '/businesses/$businessId',
      FakeResponse.ok(businessJson(permissions: billingPermissions)),
    )
    ..reply(
      'GET',
      '/billing/plans',
      FakeResponse.ok(catalogJson(online: online)),
    )
    ..reply(
      'GET',
      '/businesses/$businessId/subscription',
      FakeResponse.ok(subscription ?? subscriptionJson(online: online)),
    );

  final Finder list = find.byType(Scrollable).first;

  /// Scrolls [target] into view and lets the frame lay out at the new
  /// offset before tapping it.
  Future<void> scrollAndTap(WidgetTester tester, Finder target) async {
    await tester.scrollUntilVisible(target, 300, scrollable: list);
    await tester.pumpAndSettle();
    await tester.tap(target);
    await tester.pumpAndSettle();
  }

  group('parsing', () {
    test('plans, a subscription and an invoice', () {
      final PlanCatalog catalog = ApiSubscriptionRepository.catalogFromJson(
        catalogJson(),
      );
      expect(catalog.plans.map((p) => p.code), <String>[
        'free',
        'pro',
        'business',
      ]);
      expect(catalog.plan('business')!.limits.branches, isNull);
      expect(
        catalog.plan('pro')!.priceFor(BillingPeriod.yearly, 'USD')!.amount,
        '150.00',
      );
      expect(catalog.trialDays, 14);

      final BusinessSubscription s =
          ApiSubscriptionRepository.subscriptionFromJson(
            subscriptionJson(
              status: 'past_due',
              plan: 'business',
              paid: true,
              scheduled: 'pro',
            ),
          );
      expect(s.status, SubscriptionStatus.pastDue);
      expect(s.plan.has(PlanFeatures.branchInsights), isTrue);
      expect(s.scheduledPlanCode, 'pro');
      expect(s.isOverLimit(PlanLimitKind.branches), isFalse);

      final BillingInvoice invoice = ApiSubscriptionRepository.invoiceFromJson(
        <String, Object?>{
          'id': '01jinvoicexxxxxxxxxxxxxxxx',
          'number': 'DORY-2026-000042',
          'status': 'failed',
          'plan': <String, Object?>{
            'code': 'pro',
            'name': 'Pro',
            'names': <String, Object?>{'en': 'Pro', 'ar': 'احترافي'},
          },
          'billing_period': 'yearly',
          'currency': 'USD',
          'amount_minor': 15000,
          'amount': '150.00',
          'issued_at': '2026-11-11T10:00:00+00:00',
        },
      );
      expect(invoice.status, InvoiceStatus.failed);
      expect(invoice.planName!.of('ar'), 'احترافي');
      expect(invoice.period, BillingPeriod.yearly);
    });

    test('a plan limit is explained with its number', () {
      final AppLocalizations en = lookupAppLocalizations(const Locale('en'));
      final AppLocalizations ar = lookupAppLocalizations(const Locale('ar'));
      const AppFailure one = AppFailure(
        FailureKind.forbidden,
        code: 'plan_limit_reached',
        details: <String, Object?>{
          'limit': 'branches',
          'max': 1,
          'used': 1,
          'plan': 'free',
        },
      );
      const AppFailure three = AppFailure(
        FailureKind.forbidden,
        code: 'plan_limit_reached',
        details: <String, Object?>{
          'limit': 'queues',
          'max': 3,
          'used': 3,
          'plan': 'free',
        },
      );

      expect(
        failureMessage(en, one),
        'This plan includes 1 branch. Upgrade to add more.',
      );
      expect(
        failureMessage(en, three),
        'This plan includes up to 3 queues. Upgrade to add more.',
      );
      expect(
        failureMessage(ar, three),
        'تتضمن هذه الخطة حتى 3 طوابير. قم بالترقية لإضافة المزيد.',
      );
      expect(
        failureMessage(
          en,
          const AppFailure(FailureKind.server, code: 'payments_unavailable'),
        ),
        startsWith('Online payment is not available yet'),
      );
    });
  });

  group('subscription screen', () {
    testWidgets('the owner sees the trial, the usage and what is included', (
      tester,
    ) async {
      await pumpAppAt(tester, AppRoutes.subscription(businessId), http: api());

      expect(find.text('Pro'), findsOneWidget);
      expect(find.text('Free trial'), findsOneWidget);
      expect(find.text('10 days left'), findsOneWidget);
      expect(find.text('1 of 3'), findsOneWidget); // branches
      expect(find.text('2 of 15'), findsOneWidget); // queues
      expect(find.text('1 of 10'), findsOneWidget); // seats
      await tester.scrollUntilVisible(
        find.text('End the trial'),
        300,
        scrollable: list,
      );
      expect(find.text('Insights and peak times'), findsOneWidget);
      expect(find.text('See plans'), findsOneWidget);
      expect(find.text('Billing history'), findsOneWidget);
    });

    testWidgets('a manager sees the plan but cannot change it', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.subscription(businessId),
        http: api(subscription: subscriptionJson(manage: false)),
      );

      await tester.scrollUntilVisible(
        find.text('Only the business owner can change the plan.'),
        300,
        scrollable: list,
      );
      expect(find.text('Billing history'), findsNothing);
      expect(find.text('End the trial'), findsNothing);
    });

    testWidgets('the owner cancels a paid plan at the end of its period', (
      tester,
    ) async {
      final FakeHttpAdapter http =
          api(subscription: subscriptionJson(status: 'active', paid: true))
            ..reply(
              'POST',
              '/businesses/$businessId/subscription/cancel',
              FakeResponse.ok(
                subscriptionJson(status: 'active', paid: true, cancel: true),
              ),
            );
      await pumpAppAt(tester, AppRoutes.subscription(businessId), http: http);
      expect(find.textContaining('15.00 USD'), findsOneWidget);

      await scrollAndTap(tester, find.text('Cancel subscription'));
      expect(find.textContaining('The plan stays until'), findsOneWidget);
      await tester.tap(
        find.widgetWithText(FilledButton, 'Cancel subscription'),
      );
      await tester.pumpAndSettle();

      expect(
        http.sent('POST', '/businesses/$businessId/subscription/cancel'),
        hasLength(1),
      );
      expect(find.text('Subscription canceled'), findsOneWidget);
    });

    testWidgets('it reads right to left in Arabic', (tester) async {
      await pumpAppAt(
        tester,
        AppRoutes.subscription(businessId),
        http: api(),
        storage: InMemoryKeyValueStorage(<String, String>{
          StorageKeys.languageCode: 'ar',
        }),
      );

      expect(find.text('الخطة والفوترة'), findsWidgets);
      expect(find.text('احترافي'), findsOneWidget);
      expect(find.text('تجربة مجانية'), findsOneWidget);
      expect(find.text('بقيت 10 أيام'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text('احترافي'))),
        TextDirection.rtl,
      );
    });
  });

  group('plans screen', () {
    testWidgets(
      'monthly and yearly prices, the comparison and the free customer note',
      (tester) async {
        await pumpAppAt(
          tester,
          AppRoutes.subscriptionPlans(businessId),
          http: api(),
        );

        expect(find.text('${money('15.00', 'USD')} / month'), findsOneWidget);
        expect(find.text('Save 16% with yearly billing'), findsOneWidget);
        await tester.tap(find.text('Yearly'));
        await tester.pumpAndSettle();
        expect(find.text('${money('150.00', 'USD')} / year'), findsOneWidget);
        expect(find.text('${money('490.00', 'USD')} / year'), findsOneWidget);

        await tester.scrollUntilVisible(
          find.text('Compare plans'),
          300,
          scrollable: list,
        );
        await tester.scrollUntilVisible(
          find.textContaining('Customers never pay'),
          300,
          scrollable: list,
        );
        expect(
          find.text('New businesses try Pro free for 14 days.'),
          findsOneWidget,
        );
      },
    );

    testWidgets('without online payment, it explains how to pay', (
      tester,
    ) async {
      final FakeHttpAdapter http = api();
      await pumpAppAt(
        tester,
        AppRoutes.subscriptionPlans(businessId),
        http: http,
      );

      await scrollAndTap(tester, find.text('Choose Business'));

      expect(find.text('Pay by transfer or cash'), findsOneWidget);
      expect(
        find.textContaining('bank transfer, cash or mobile wallet'),
        findsOneWidget,
      );
      expect(
        http.sent('POST', '/businesses/$businessId/subscription/checkout'),
        isEmpty,
      );
    });

    testWidgets('online payment opens the payment page and waits for the server', (
      tester,
    ) async {
      final List<Uri> opened = <Uri>[];
      final FakeHttpAdapter http = api(online: true)
        ..reply(
          'POST',
          '/businesses/$businessId/subscription/checkout',
          FakeResponse.ok(<String, Object?>{
            'checkout': <String, Object?>{
              'id': '01jcheckoutxxxxxxxxxxxxxxx',
              'status': 'pending',
            },
            'payment_url':
                'http://dory.test/billing/checkout/01jcheckoutxxxxxxxxxxxxxxx/pay?signature=x',
          }, status: 201),
        )
        ..reply(
          'GET',
          '/businesses/$businessId/subscription/checkouts/01jcheckoutxxxxxxxxxxxxxxx',
          FakeResponse.ok(<String, Object?>{
            'checkout': <String, Object?>{
              'id': '01jcheckoutxxxxxxxxxxxxxxx',
              'status': 'paid',
            },
            'subscription': subscriptionJson(
              status: 'active',
              plan: 'business',
              paid: true,
              online: true,
            ),
          }),
        );
      final GoRouter router = await pumpAppAt(
        tester,
        AppRoutes.subscriptionPlans(businessId),
        http: http,
        overrides: <Override>[
          paymentPageLauncherProvider.overrideWithValue((url) async {
            opened.add(url);
            return true;
          }),
        ],
      );

      await scrollAndTap(tester, find.text('Choose Business'));

      final Map<String, Object?> body =
          http
                  .sent('POST', '/businesses/$businessId/subscription/checkout')
                  .single
                  .data
              as Map<String, Object?>;
      expect(body, <String, Object?>{
        'plan': 'business',
        'billing_period': 'monthly',
      });
      expect(
        opened.single.path,
        '/billing/checkout/01jcheckoutxxxxxxxxxxxxxxx/pay',
      );
      expect(find.text('Complete the payment'), findsOneWidget);

      await tester.tap(find.text('Check the payment'));
      await tester.pumpAndSettle();
      expect(
        find.text('Payment received. Your plan is active.'),
        findsOneWidget,
      );
      expect(topLocation(router), AppRoutes.subscription(businessId));
    });

    testWidgets('a smaller plan is scheduled for the end of the period', (
      tester,
    ) async {
      final FakeHttpAdapter http =
          api(
            subscription: subscriptionJson(
              status: 'active',
              plan: 'business',
              paid: true,
            ),
          )..reply(
            'POST',
            '/businesses/$businessId/subscription/downgrade',
            FakeResponse.ok(
              subscriptionJson(
                status: 'active',
                plan: 'business',
                paid: true,
                scheduled: 'pro',
              ),
            ),
          );
      await pumpAppAt(
        tester,
        AppRoutes.subscriptionPlans(businessId),
        http: http,
      );

      await scrollAndTap(tester, find.text('Switch to Pro at renewal'));
      expect(
        find.textContaining('You keep your current plan until'),
        findsOneWidget,
      );
      await tester.tap(
        find.widgetWithText(FilledButton, 'Switch to Pro at renewal'),
      );
      await tester.pumpAndSettle();

      expect(
        http
            .sent('POST', '/businesses/$businessId/subscription/downgrade')
            .single
            .data,
        <String, Object?>{'plan': 'pro', 'billing_period': 'monthly'},
      );
      expect(find.text('The change is scheduled'), findsOneWidget);
    });
  });

  testWidgets('billing history lists payments and failed attempts', (
    tester,
  ) async {
    final FakeHttpAdapter http = api()
      ..reply(
        'GET',
        '/businesses/$businessId/invoices',
        FakeResponse.ok(<Object?>[
          <String, Object?>{
            'id': '01jinvoice2xxxxxxxxxxxxxxx',
            'number': 'DORY-2026-000002',
            'status': 'failed',
            'plan': <String, Object?>{
              'code': 'business',
              'name': 'Business',
              'names': <String, Object?>{'en': 'Business', 'ar': 'الأعمال'},
            },
            'billing_period': 'yearly',
            'currency': 'USD',
            'amount_minor': 49000,
            'amount': '490.00',
            'issued_at': '2026-11-12T10:00:00+00:00',
            'payment_method': 'card',
          },
          <String, Object?>{
            'id': '01jinvoice1xxxxxxxxxxxxxxx',
            'number': 'DORY-2026-000001',
            'status': 'paid',
            'plan': <String, Object?>{
              'code': 'pro',
              'name': 'Pro',
              'names': <String, Object?>{'en': 'Pro', 'ar': 'احترافي'},
            },
            'billing_period': 'monthly',
            'currency': 'USD',
            'amount_minor': 1500,
            'amount': '15.00',
            'period_start': '2026-11-11T10:00:00+00:00',
            'period_end': '2026-12-11T10:00:00+00:00',
            'issued_at': '2026-11-11T10:00:00+00:00',
            'paid_at': '2026-11-11T10:00:00+00:00',
            'payment_method': 'bank_transfer',
          },
        ]),
      );
    await pumpAppAt(tester, AppRoutes.billingHistory(businessId), http: http);

    expect(find.text('Business · Yearly'), findsOneWidget);
    expect(find.text('Failed'), findsOneWidget);
    expect(find.text('Pro · Monthly'), findsOneWidget);
    expect(find.text('Paid'), findsOneWidget);
    expect(find.text('Bank transfer'), findsOneWidget);
    expect(find.textContaining('DORY-2026-000001'), findsOneWidget);
  });

  testWidgets('a reached limit explains itself and leads to the plans', (
    tester,
  ) async {
    final FakeHttpAdapter http = api()
      ..reply(
        'GET',
        '/businesses/$businessId/branches',
        FakeResponse.ok(<Object?>[branchJson()]),
      )
      ..reply(
        'POST',
        '/businesses/$businessId/invitations',
        const FakeResponse(403, <String, Object?>{
          'success': false,
          'message':
              'This plan includes up to 3 team seats. Upgrade to add more',
          'code': 'plan_limit_reached',
          'errors': null,
          'details': <String, Object?>{
            'limit': 'members',
            'max': 3,
            'used': 3,
            'plan': 'free',
          },
        }),
      );
    final GoRouter router = await pumpAppAt(
      tester,
      AppRoutes.teamInvite(businessId),
      http: http,
    );

    await tester.enterText(
      find.widgetWithText(TextFormField, 'Their email address'),
      'sara@example.com',
    );
    await tester.ensureVisible(find.text('Create invitation'));
    await tester.tap(find.text('Create invitation'));
    await tester.pumpAndSettle();

    expect(find.text('Plan limit reached'), findsOneWidget);
    expect(
      find.text(
        'This plan includes up to 3 team seats, counting the owner and pending invitations. Upgrade to add more.',
      ),
      findsWidgets,
    );
    await tester.tap(find.text('See plans'));
    await tester.pumpAndSettle();
    expect(topLocation(router), AppRoutes.subscriptionPlans(businessId));
  });

  testWidgets('insights outside the plan show what the plans offer', (
    tester,
  ) async {
    final FakeHttpAdapter http = api()
      ..reply(
        'GET',
        '/businesses/$businessId/insights',
        const FakeResponse(403, <String, Object?>{
          'success': false,
          'message': 'This feature is not included in the current plan',
          'code': 'feature_not_in_plan',
          'errors': null,
          'details': <String, Object?>{'feature': 'insights', 'plan': 'free'},
        }),
      );
    await pumpAppAt(tester, AppRoutes.businessInsights(businessId), http: http);

    expect(find.text('Not included in your plan'), findsOneWidget);
    expect(find.textContaining('part of the paid plans'), findsOneWidget);
    expect(find.text('See plans'), findsOneWidget);
  });

  testWidgets('opening plan and billing asks the server again', (tester) async {
    final FakeHttpAdapter http = api();
    await pumpAppAt(tester, AppRoutes.business(businessId), http: http);
    expect(find.text('Pro trial: 10 days left'), findsOneWidget);

    // Meanwhile DORY records a bank transfer for the business.
    http.reply(
      'GET',
      '/businesses/$businessId/subscription',
      FakeResponse.ok(
        subscriptionJson(status: 'active', plan: 'business', paid: true),
      ),
    );
    await scrollAndTap(tester, find.text('Plan and billing'));

    expect(find.text('Business'), findsOneWidget);
    expect(find.text('Active'), findsOneWidget);
    expect(find.textContaining('Paid until'), findsOneWidget);
    // Unmount, and let the overview's pending refresh timers end.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(minutes: 1));
  });

  testWidgets('the overview shows the plan and a running trial', (
    tester,
  ) async {
    await pumpAppAt(tester, AppRoutes.business(businessId), http: api());

    expect(find.text('Pro trial: 10 days left'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Plan and billing'),
      300,
      scrollable: list,
    );
    expect(find.text('Plan: Pro · Free trial'), findsOneWidget);
    await tester.tap(find.text('Plan and billing'));
    await tester.pumpAndSettle();
    expect(find.text('10 days left'), findsOneWidget);
    // Unmount, and let the overview's pending refresh timers end.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(minutes: 1));
  });
}
