import 'dart:math';

import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// The onboarding steps, in order.
enum OnboardingStep { basics, details, branch, services, review }

@immutable
class OnboardingState {
  const OnboardingState({
    required this.step,
    required this.draft,
    required this.idempotencyKey,
    this.submitting = false,
    this.failure,
  });

  final OnboardingStep step;
  final BusinessDraft draft;

  /// One key per onboarding session: a retried submission (for example
  /// after a timeout) returns the business created the first time.
  final String idempotencyKey;
  final bool submitting;
  final AppFailure? failure;

  bool get isFirst => step == OnboardingStep.values.first;
  bool get isLast => step == OnboardingStep.values.last;

  OnboardingState copyWith({
    OnboardingStep? step,
    BusinessDraft? draft,
    bool? submitting,
    AppFailure? failure,
    bool clearFailure = false,
  }) => OnboardingState(
    step: step ?? this.step,
    draft: draft ?? this.draft,
    idempotencyKey: idempotencyKey,
    submitting: submitting ?? this.submitting,
    failure: clearFailure ? null : (failure ?? this.failure),
  );
}

/// Drives the multi-step business onboarding. The draft survives moving
/// between steps; nothing is sent to the server before the review step.
class OnboardingController extends Notifier<OnboardingState> {
  @override
  OnboardingState build() => OnboardingState(
    step: OnboardingStep.basics,
    draft: const BusinessDraft(),
    idempotencyKey: _newIdempotencyKey(),
  );

  void update(BusinessDraft Function(BusinessDraft draft) change) {
    state = state.copyWith(draft: change(state.draft));
  }

  void next() {
    if (!state.isLast) {
      state = state.copyWith(
        step: OnboardingStep.values[state.step.index + 1],
        clearFailure: true,
      );
    }
  }

  void back() {
    if (!state.isFirst) {
      state = state.copyWith(
        step: OnboardingStep.values[state.step.index - 1],
        clearFailure: true,
      );
    }
  }

  void goTo(OnboardingStep step) {
    state = state.copyWith(step: step, clearFailure: true);
  }

  void addService(ServiceDraft service) {
    update(
      (draft) =>
          draft.copyWith(services: <ServiceDraft>[...draft.services, service]),
    );
  }

  void removeService(int index) {
    update(
      (draft) => draft.copyWith(
        services: <ServiceDraft>[...draft.services]..removeAt(index),
      ),
    );
  }

  /// Creates the business. Returns null when it failed (see
  /// [OnboardingState.failure]) or a submission is already running.
  Future<Business?> submit() async {
    if (state.submitting) {
      return null;
    }
    state = state.copyWith(submitting: true, clearFailure: true);
    try {
      final Business business = await ref
          .read(businessActionsProvider)
          .create(state.draft, idempotencyKey: state.idempotencyKey);
      state = state.copyWith(submitting: false);
      return business;
    } on AppFailure catch (failure) {
      state = state.copyWith(
        submitting: false,
        failure: failure,
        step: _stepFor(failure),
      );
      return null;
    }
  }

  /// The step holding the first field the server rejected.
  static OnboardingStep _stepFor(AppFailure failure) {
    final Iterable<String> fields = failure.fieldErrors.keys;
    if (fields.any((f) => f == 'name' || f == 'category')) {
      return OnboardingStep.basics;
    }
    if (fields.any(
      (f) => const <String>{
        'description',
        'email',
        'phone',
        'website',
      }.contains(f),
    )) {
      return OnboardingStep.details;
    }
    if (fields.any((f) => f.startsWith('branch.services'))) {
      return OnboardingStep.services;
    }
    if (fields.any((f) => f.startsWith('branch'))) {
      return OnboardingStep.branch;
    }
    return OnboardingStep.review;
  }

  static String _newIdempotencyKey() {
    final Random random = Random.secure();
    return List<String>.generate(
      32,
      (_) => random.nextInt(16).toRadixString(16),
    ).join();
  }
}

final onboardingControllerProvider =
    NotifierProvider.autoDispose<OnboardingController, OnboardingState>(
      OnboardingController.new,
      name: 'onboardingControllerProvider',
    );
