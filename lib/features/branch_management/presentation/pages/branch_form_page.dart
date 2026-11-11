import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/branch_management/presentation/widgets/branch_form_fields.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Creates a branch. The time zone defaults to the business's.
class CreateBranchPage extends ConsumerWidget {
  const CreateBranchPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Business> business = ref.watch(
      businessProvider(businessId),
    );
    return DoryPageScaffold(
      title: context.l10n.branchCreateTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<Business>(
        value: business,
        onRetry: () => ref.invalidate(businessProvider(businessId)),
        data: (value) => BranchForm(
          businessId: businessId,
          initial: BranchDraft(timezone: value.timezone),
        ),
      ),
    );
  }
}

/// Edits a branch, including activating and deactivating it.
class EditBranchPage extends ConsumerWidget {
  const EditBranchPage({
    super.key,
    required this.businessId,
    required this.branchId,
  });

  final String businessId;
  final String branchId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Branch> branch = ref.watch(branchProvider(branchId));
    return DoryPageScaffold(
      title: context.l10n.branchEditTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<Branch>(
        value: branch,
        onRetry: () => ref.invalidate(branchProvider(branchId)),
        data: (value) => BranchForm(
          businessId: businessId,
          branchId: branchId,
          initial: value.toDraft(),
        ),
      ),
    );
  }
}

/// The branch form shared by create and edit.
class BranchForm extends ConsumerStatefulWidget {
  const BranchForm({
    super.key,
    required this.businessId,
    required this.initial,
    this.branchId,
  });

  final String businessId;

  /// Null when creating.
  final String? branchId;
  final BranchDraft initial;

  @override
  ConsumerState<BranchForm> createState() => _BranchFormState();
}

class _BranchFormState extends ConsumerState<BranchForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final BranchFieldControllers _fields = BranchFieldControllers(
    widget.initial,
  );
  late bool _isActive = widget.initial.isActive;
  bool _saving = false;
  AppFailure? _failure;

  @override
  void dispose() {
    _fields.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    setState(() {
      _saving = true;
      _failure = null;
    });
    final BranchActions actions = ref.read(branchActionsProvider);
    final BranchDraft draft = _fields.toDraft(isActive: _isActive);
    try {
      final String? branchId = widget.branchId;
      final Branch branch = branchId == null
          ? await actions.create(widget.businessId, draft)
          : await actions.update(widget.businessId, branchId, draft);
      if (mounted) {
        final AppLocalizations l10n = context.l10n;
        showResultMessage(
          context,
          branchId == null ? l10n.branchCreated : l10n.commonSaved,
        );
        context.go(AppRoutes.branch(widget.businessId, branch.id));
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _failure = failure);
        if (failure.isPlanLimit) {
          unawaited(
            showPlanLimitDialog(
              context,
              failure,
              businessId: widget.businessId,
            ),
          );
        }
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AppFailure? failure = _failure;

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (failure != null) ...<Widget>[
            DoryNotice(
              message: failureMessage(l10n, failure),
              tone: DoryNoticeTone.error,
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          BranchFormFields(
            controllers: _fields,
            errors: failure?.fieldErrors ?? const <String, String>{},
            enabled: !_saving,
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryCard(
            padding: EdgeInsets.zero,
            child: SwitchListTile(
              title: Text(l10n.branchStatusLabel),
              value: _isActive,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _isActive = value),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: widget.branchId == null
                ? l10n.commonCreate
                : l10n.commonSave,
            isLoading: _saving,
            onPressed: _save,
          ),
        ],
      ),
    );
  }
}
