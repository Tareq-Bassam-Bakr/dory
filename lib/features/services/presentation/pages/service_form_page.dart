import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/validation/business_validators.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';
import 'package:dory_app/features/services/presentation/providers/service_providers.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Adds a service to a branch.
class CreateServicePage extends StatelessWidget {
  const CreateServicePage({
    super.key,
    required this.businessId,
    required this.branchId,
  });

  final String businessId;
  final String branchId;

  @override
  Widget build(BuildContext context) {
    return DoryPageScaffold(
      title: context.l10n.serviceCreateTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: ServiceForm(
        businessId: businessId,
        branchId: branchId,
        initial: const ServiceDraft(name: ''),
      ),
    );
  }
}

/// Edits, deactivates or archives a service.
class EditServicePage extends ConsumerWidget {
  const EditServicePage({
    super.key,
    required this.businessId,
    required this.branchId,
    required this.serviceId,
  });

  final String businessId;
  final String branchId;
  final String serviceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Service> service = ref.watch(serviceProvider(serviceId));
    return DoryPageScaffold(
      title: context.l10n.serviceEditTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<Service>(
        value: service,
        onRetry: () => ref.invalidate(serviceProvider(serviceId)),
        data: (value) => ServiceForm(
          businessId: businessId,
          branchId: branchId,
          serviceId: serviceId,
          initial: value.toDraft(),
        ),
      ),
    );
  }
}

/// The service form shared by create and edit.
class ServiceForm extends ConsumerStatefulWidget {
  const ServiceForm({
    super.key,
    required this.businessId,
    required this.branchId,
    required this.initial,
    this.serviceId,
  });

  final String businessId;
  final String branchId;

  /// Null when creating.
  final String? serviceId;
  final ServiceDraft initial;

  @override
  ConsumerState<ServiceForm> createState() => _ServiceFormState();
}

class _ServiceFormState extends ConsumerState<ServiceForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: widget.initial.name,
  );
  late final TextEditingController _description = TextEditingController(
    text: widget.initial.description,
  );
  late final TextEditingController _duration = TextEditingController(
    text: '${widget.initial.durationMinutes}',
  );
  late bool _isActive = widget.initial.isActive;
  bool _busy = false;
  AppFailure? _failure;

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _duration.dispose();
    super.dispose();
  }

  String get _listLocation =>
      AppRoutes.services(widget.businessId, widget.branchId);

  Future<void> _save() async {
    if (_busy || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    final ServiceDraft draft = ServiceDraft(
      name: _name.text.trim(),
      description: _description.text.trim(),
      durationMinutes: int.parse(_duration.text.trim()),
      isActive: _isActive,
    );
    await _run(() async {
      final ServiceActions actions = ref.read(serviceActionsProvider);
      final String? serviceId = widget.serviceId;
      if (serviceId == null) {
        await actions.create(widget.businessId, widget.branchId, draft);
      } else {
        await actions.update(
          widget.businessId,
          widget.branchId,
          serviceId,
          draft,
        );
      }
      if (mounted) {
        final AppLocalizations l10n = context.l10n;
        showResultMessage(
          context,
          serviceId == null ? l10n.serviceCreated : l10n.commonSaved,
        );
        context.go(_listLocation);
      }
    });
  }

  Future<void> _archive() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.serviceArchiveConfirmTitle,
      message: l10n.serviceArchiveConfirmMessage,
      confirmLabel: l10n.serviceArchive,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    await _run(() async {
      await ref
          .read(serviceActionsProvider)
          .archive(widget.businessId, widget.branchId, widget.serviceId!);
      if (mounted) {
        showResultMessage(context, l10n.serviceArchived);
        context.go(_listLocation);
      }
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _failure = null;
    });
    try {
      await action();
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _failure = failure);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AppFailure? failure = _failure;
    final Map<String, String> errors =
        failure?.fieldErrors ?? const <String, String>{};

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
          DoryTextField(
            label: l10n.serviceNameLabel,
            controller: _name,
            textCapitalization: TextCapitalization.sentences,
            enabled: !_busy,
            errorText: errors['name'],
            validator: (value) => BusinessValidators.serviceName(l10n, value),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.serviceDescriptionLabel,
            controller: _description,
            maxLines: 3,
            minLines: 2,
            enabled: !_busy,
            errorText: errors['description'],
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.serviceDurationLabel,
            controller: _duration,
            keyboardType: TextInputType.number,
            textDirection: TextDirection.ltr,
            enabled: !_busy,
            errorText: errors['estimated_duration_minutes'],
            validator: (value) => BusinessValidators.duration(l10n, value),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryCard(
            padding: EdgeInsets.zero,
            child: SwitchListTile(
              title: Text(l10n.serviceStatusLabel),
              value: _isActive,
              onChanged: _busy
                  ? null
                  : (value) => setState(() => _isActive = value),
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: widget.serviceId == null
                ? l10n.commonCreate
                : l10n.commonSave,
            isLoading: _busy,
            onPressed: _save,
          ),
          if (widget.serviceId != null) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.serviceArchive,
              icon: Icons.archive_outlined,
              variant: DoryButtonVariant.tertiary,
              onPressed: _busy ? null : _archive,
            ),
          ],
        ],
      ),
    );
  }
}
