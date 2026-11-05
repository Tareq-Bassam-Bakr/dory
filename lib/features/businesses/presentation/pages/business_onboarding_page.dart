import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/presentation/widgets/branch_form_fields.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_category.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/providers/onboarding_controller.dart';
import 'package:dory_app/features/businesses/presentation/validation/business_validators.dart';
import 'package:dory_app/features/businesses/presentation/widgets/category_picker.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Five-step business onboarding: name and category, description and
/// contact, main branch, services, review. Data entered in each step is
/// kept when moving back and forth; the business, its branch and its
/// services are created together, once, at the end.
class BusinessOnboardingPage extends ConsumerStatefulWidget {
  const BusinessOnboardingPage({super.key});

  @override
  ConsumerState<BusinessOnboardingPage> createState() =>
      _BusinessOnboardingPageState();
}

class _BusinessOnboardingPageState
    extends ConsumerState<BusinessOnboardingPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _description = TextEditingController();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _phone = TextEditingController();
  final TextEditingController _website = TextEditingController();
  final TextEditingController _serviceName = TextEditingController();
  final TextEditingController _serviceDuration = TextEditingController(
    text: '15',
  );
  final GlobalKey<FormState> _serviceFormKey = GlobalKey<FormState>();
  late final BranchFieldControllers _branch = BranchFieldControllers();
  bool _branchNameDefaulted = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // A sensible default the owner can change.
    if (!_branchNameDefaulted) {
      _branchNameDefaulted = true;
      _branch.name.text = context.l10n.branchNameDefault;
    }
  }

  @override
  void dispose() {
    for (final TextEditingController controller in <TextEditingController>[
      _name,
      _description,
      _email,
      _phone,
      _website,
      _serviceName,
      _serviceDuration,
    ]) {
      controller.dispose();
    }
    _branch.dispose();
    super.dispose();
  }

  OnboardingController get _controller =>
      ref.read(onboardingControllerProvider.notifier);

  /// Copies the text fields into the draft (kept across steps).
  void _syncDraft() {
    _controller.update(
      (draft) => draft.copyWith(
        name: _name.text.trim(),
        description: _description.text.trim(),
        email: _email.text.trim().toLowerCase(),
        phone: _phone.text.replaceAll(RegExp(r'[\s\-.()]'), ''),
        website: _website.text.trim(),
        branch: _branch.toDraft(),
      ),
    );
  }

  Future<void> _next() async {
    final OnboardingState state = ref.read(onboardingControllerProvider);
    if (!(_formKey.currentState?.validate() ?? true)) {
      return;
    }
    _syncDraft();
    if (!state.isLast) {
      _controller.next();
      return;
    }

    final Business? business = await _controller.submit();
    if (business != null && mounted) {
      showResultMessage(context, context.l10n.onboardingCreated);
      context.go(AppRoutes.business(business.id));
    }
  }

  void _back() {
    _syncDraft();
    _controller.back();
  }

  void _addService() {
    if (!(_serviceFormKey.currentState?.validate() ?? false)) {
      return;
    }
    final String name = _serviceName.text.trim();
    final List<ServiceDraft> existing = ref
        .read(onboardingControllerProvider)
        .draft
        .services;
    if (existing.any((s) => s.name.toLowerCase() == name.toLowerCase())) {
      showResultMessage(context, context.l10n.serviceNameDuplicate);
      return;
    }
    _controller.addService(
      ServiceDraft(
        name: name,
        durationMinutes: int.parse(_serviceDuration.text.trim()),
      ),
    );
    _serviceName.clear();
    _serviceDuration.text = '15';
  }

  bool get _hasInput =>
      _name.text.trim().isNotEmpty ||
      ref.read(onboardingControllerProvider).draft.categorySlug != null;

  Future<void> _confirmLeave() async {
    final AppLocalizations l10n = context.l10n;
    final bool leave =
        !_hasInput ||
        await confirmAction(
          context,
          title: l10n.onboardingLeaveTitle,
          message: l10n.onboardingLeaveMessage,
          confirmLabel: l10n.onboardingLeaveConfirm,
          destructive: true,
        );
    if (leave && mounted) {
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final OnboardingState state = ref.watch(onboardingControllerProvider);
    final int stepNumber = state.step.index + 1;
    final int total = OnboardingStep.values.length;
    final AppFailure? failure = state.failure;
    final Map<String, String> errors =
        failure?.fieldErrors ?? const <String, String>{};

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !state.submitting) {
          unawaited(state.isFirst ? _confirmLeave() : Future<void>.sync(_back));
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.onboardingTitle),
          leading: IconButton(
            tooltip: MaterialLocalizations.of(context).closeButtonTooltip,
            icon: const Icon(Icons.close_rounded),
            onPressed: state.submitting ? null : _confirmLeave,
          ),
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(4),
            child: LinearProgressIndicator(value: stepNumber / total),
          ),
        ),
        body: SafeArea(
          child: Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.pageGutter,
                AppSpacing.lg,
                AppSpacing.pageGutter,
                AppSpacing.xl,
              ),
              children: <Widget>[
                Semantics(
                  header: true,
                  child: Text(
                    l10n.onboardingStepProgress(stepNumber, total),
                    style: Theme.of(
                      context,
                    ).textTheme.labelLarge?.copyWith(color: AppColors.primary),
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  _stepTitle(l10n, state.step),
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: AppSpacing.xl),
                if (failure != null) ...<Widget>[
                  DoryNotice(
                    message: failureMessage(l10n, failure),
                    tone: DoryNoticeTone.error,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                ],
                switch (state.step) {
                  OnboardingStep.basics => _basics(l10n, state, errors),
                  OnboardingStep.details => _details(l10n, errors),
                  OnboardingStep.branch => BranchFormFields(
                    controllers: _branch,
                    errors: errors,
                    errorPrefix: 'branch.',
                    enabled: !state.submitting,
                  ),
                  OnboardingStep.services => _services(l10n, state),
                  OnboardingStep.review => _review(l10n, state.draft),
                },
              ],
            ),
          ),
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: <Widget>[
                if (!state.isFirst) ...<Widget>[
                  Expanded(
                    child: DoryButton(
                      label: l10n.commonBack,
                      variant: DoryButtonVariant.secondary,
                      onPressed: state.submitting ? null : _back,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                ],
                Expanded(
                  child: DoryButton(
                    label: state.isLast
                        ? l10n.onboardingCreate
                        : l10n.commonNext,
                    isLoading: state.submitting,
                    onPressed: _next,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _stepTitle(AppLocalizations l10n, OnboardingStep step) =>
      switch (step) {
        OnboardingStep.basics => l10n.onboardingStepBasics,
        OnboardingStep.details => l10n.onboardingStepDetails,
        OnboardingStep.branch => l10n.onboardingStepBranch,
        OnboardingStep.services => l10n.onboardingStepServices,
        OnboardingStep.review => l10n.onboardingStepReview,
      };

  Widget _basics(
    AppLocalizations l10n,
    OnboardingState state,
    Map<String, String> errors,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryTextField(
          label: l10n.businessNameLabel,
          controller: _name,
          prefixIcon: Icons.storefront_outlined,
          textCapitalization: TextCapitalization.words,
          textInputAction: TextInputAction.done,
          enabled: !state.submitting,
          errorText: errors['name'],
          validator: (value) => BusinessValidators.name(l10n, value),
        ),
        const SizedBox(height: AppSpacing.xl),
        CategoryPicker(
          selected: state.draft.categorySlug,
          errorText: errors['category'],
          enabled: !state.submitting,
          onChanged: (slug) =>
              _controller.update((draft) => draft.copyWith(categorySlug: slug)),
        ),
      ],
    );
  }

  Widget _details(AppLocalizations l10n, Map<String, String> errors) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryTextField(
          label: l10n.businessDescriptionLabel,
          hint: l10n.businessDescriptionHint,
          controller: _description,
          maxLines: 4,
          minLines: 3,
          textCapitalization: TextCapitalization.sentences,
          errorText: errors['description'],
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryNotice(message: l10n.businessContactHint),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.businessEmailLabel,
          controller: _email,
          prefixIcon: Icons.mail_outline_rounded,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          textDirection: TextDirection.ltr,
          errorText: errors['email'],
          validator: (value) => BusinessValidators.optionalEmail(l10n, value),
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.businessPhoneLabel,
          controller: _phone,
          prefixIcon: Icons.phone_outlined,
          keyboardType: TextInputType.phone,
          textInputAction: TextInputAction.next,
          textDirection: TextDirection.ltr,
          helperText: l10n.authPhoneHint,
          errorText: errors['phone'],
          validator: (value) => BusinessValidators.optionalPhone(l10n, value),
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.businessWebsiteLabel,
          hint: 'https://',
          controller: _website,
          prefixIcon: Icons.language_rounded,
          keyboardType: TextInputType.url,
          textInputAction: TextInputAction.done,
          textDirection: TextDirection.ltr,
          errorText: errors['website'],
          validator: (value) => BusinessValidators.optionalWebsite(l10n, value),
        ),
      ],
    );
  }

  Widget _services(AppLocalizations l10n, OnboardingState state) {
    final List<ServiceDraft> services = state.draft.services;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          l10n.onboardingServicesIntro,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryCard(
          child: Form(
            key: _serviceFormKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                DoryTextField(
                  label: l10n.serviceNameLabel,
                  controller: _serviceName,
                  textCapitalization: TextCapitalization.sentences,
                  validator: (value) =>
                      BusinessValidators.serviceName(l10n, value),
                ),
                const SizedBox(height: AppSpacing.md),
                DoryTextField(
                  label: l10n.serviceDurationLabel,
                  controller: _serviceDuration,
                  keyboardType: TextInputType.number,
                  textDirection: TextDirection.ltr,
                  validator: (value) =>
                      BusinessValidators.duration(l10n, value),
                ),
                const SizedBox(height: AppSpacing.md),
                DoryButton(
                  label: l10n.onboardingAddService,
                  icon: Icons.add_rounded,
                  variant: DoryButtonVariant.secondary,
                  onPressed: state.submitting ? null : _addService,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        if (services.isEmpty)
          Text(
            l10n.onboardingNoServices,
            style: Theme.of(context).textTheme.bodySmall,
          )
        else
          for (final (int index, ServiceDraft service) in services.indexed)
            DoryCard(
              padding: EdgeInsets.zero,
              child: ListTile(
                leading: const Icon(Icons.design_services_outlined),
                title: Text(service.name),
                subtitle: Text(
                  l10n.serviceDurationValue(service.durationMinutes),
                ),
                trailing: IconButton(
                  tooltip: l10n.commonRemove,
                  icon: const Icon(Icons.delete_outline_rounded),
                  onPressed: state.submitting
                      ? null
                      : () => _controller.removeService(index),
                ),
              ),
            ),
      ],
    );
  }

  Widget _review(AppLocalizations l10n, BusinessDraft draft) {
    final String language = Localizations.localeOf(context).languageCode;
    final List<BusinessCategory> categories =
        ref.watch(businessCategoriesProvider).value ??
        const <BusinessCategory>[];
    final String category =
        categories
            .where((c) => c.slug == draft.categorySlug)
            .firstOrNull
            ?.nameFor(language) ??
        '';
    String shown(String value) =>
        value.isEmpty ? l10n.onboardingNotProvided : value;
    final BranchDraft branch = draft.branch;

    Widget section(
      String title,
      OnboardingStep step,
      List<(String, String)> rows,
    ) {
      return Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.lg),
        child: DoryCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      _syncDraft();
                      _controller.goTo(step);
                    },
                    child: Text(l10n.commonEdit),
                  ),
                ],
              ),
              for (final (String label, String value) in rows)
                Padding(
                  padding: const EdgeInsets.only(top: AppSpacing.sm),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        label,
                        style: Theme.of(context).textTheme.labelMedium,
                      ),
                      Text(
                        value,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          l10n.onboardingReviewIntro,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        const SizedBox(height: AppSpacing.lg),
        section(
          l10n.onboardingStepBasics,
          OnboardingStep.basics,
          <(String, String)>[
            (l10n.businessNameLabel, draft.name),
            (l10n.businessCategoryLabel, category),
          ],
        ),
        section(
          l10n.onboardingStepDetails,
          OnboardingStep.details,
          <(String, String)>[
            (l10n.businessDescriptionLabel, shown(draft.description)),
            (l10n.businessEmailLabel, shown(draft.email)),
            (l10n.businessPhoneLabel, shown(draft.phone)),
            (l10n.businessWebsiteLabel, shown(draft.website)),
          ],
        ),
        section(
          l10n.onboardingStepBranch,
          OnboardingStep.branch,
          <(String, String)>[
            (l10n.branchNameLabel, branch.name),
            (
              l10n.branchAddressLabel,
              shown(
                <String>[
                  branch.address,
                  branch.city,
                  branch.country,
                ].where((s) => s.isNotEmpty).join(', '),
              ),
            ),
            (
              l10n.branchLocationSection,
              branch.latitude.isEmpty
                  ? l10n.onboardingNotProvided
                  : '${branch.latitude}, ${branch.longitude}',
            ),
          ],
        ),
        section(l10n.onboardingStepServices, OnboardingStep.services, <
          (String, String)
        >[
          for (final ServiceDraft service in draft.services)
            (service.name, l10n.serviceDurationValue(service.durationMinutes)),
          if (draft.services.isEmpty)
            (l10n.servicesTitle, l10n.onboardingNoServices),
        ]),
      ],
    );
  }
}
