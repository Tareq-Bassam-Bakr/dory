import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/features/analytics/data/api_intelligence_repository.dart';
import 'package:dory_app/features/analytics/domain/business_intelligence.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<IntelligenceRepository> intelligenceRepositoryProvider =
    Provider<IntelligenceRepository>(
      (ref) => ApiIntelligenceRepository(ref.watch(apiClientProvider)),
      name: 'intelligenceRepositoryProvider',
    );

/// Insights of a business (owners and managers; the server decides).
final insightsProvider = FutureProvider.autoDispose
    .family<InsightsReport, String>(
      (ref, businessId) {
        ref.watch(currentUserIdProvider);
        return ref.watch(intelligenceRepositoryProvider).insights(businessId);
      },
      retry: noAutomaticRetry,
      name: 'insightsProvider',
    );

final peakTimesProvider = FutureProvider.autoDispose
    .family<PeakTimesReport, String>(
      (ref, businessId) {
        ref.watch(currentUserIdProvider);
        return ref.watch(intelligenceRepositoryProvider).peakTimes(businessId);
      },
      retry: noAutomaticRetry,
      name: 'peakTimesProvider',
    );
