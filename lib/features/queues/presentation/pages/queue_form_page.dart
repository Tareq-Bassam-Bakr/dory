import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/branch_management/domain/entities/branch.dart';
import 'package:dory_app/features/branch_management/presentation/providers/branch_providers.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_labels.dart';
import 'package:dory_app/features/services/domain/entities/service.dart';
import 'package:dory_app/features/services/presentation/providers/service_providers.dart';
import 'package:dory_app/features/subscriptions/presentation/widgets/subscription_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Create Queue: pick a branch and one of its services without a queue,
/// then configure it.
class CreateQueuePage extends ConsumerStatefulWidget {
  const CreateQueuePage({super.key, required this.businessId});

  final String businessId;

  @override
  ConsumerState<CreateQueuePage> createState() => _CreateQueuePageState();
}

class _CreateQueuePageState extends ConsumerState<CreateQueuePage> {
  String? _branchId;
  String? _serviceId;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Branch>> branches = ref.watch(
      branchesProvider(widget.businessId),
    );

    return DoryPageScaffold(
      title: l10n.queueCreate,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<List<Branch>>(
        value: branches,
        onRetry: () => ref.invalidate(branchesProvider(widget.businessId)),
        data: (items) {
          final List<Branch> manageable = items
              .where((branch) => branch.canManageServices)
              .toList();
          if (manageable.isEmpty) {
            return DoryNotice(message: l10n.queueNoBranches);
          }
          final String branchId = _branchId ?? manageable.first.id;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              DorySectionHeader(title: l10n.queueBranchLabel),
              RadioGroup<String>(
                groupValue: branchId,
                onChanged: (value) => setState(() {
                  _branchId = value;
                  _serviceId = null;
                }),
                child: DoryCard(
                  padding: EdgeInsets.zero,
                  child: Column(
                    children: <Widget>[
                      for (final Branch branch in manageable)
                        RadioListTile<String>(
                          value: branch.id,
                          title: Text(branch.name),
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              _ServicePicker(
                businessId: widget.businessId,
                branchId: branchId,
                selected: _serviceId,
                onSelected: (id) => setState(() => _serviceId = id),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ServicePicker extends ConsumerWidget {
  const _ServicePicker({
    required this.businessId,
    required this.branchId,
    required this.selected,
    required this.onSelected,
  });

  final String businessId;
  final String branchId;
  final String? selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<List<Service>> services = ref.watch(
      servicesProvider(branchId),
    );
    final Set<String> taken = <String>{
      for (final QueueConfig queue
          in ref.watch(businessQueuesProvider(businessId)).value ??
              const <QueueConfig>[])
        if (queue.service != null) queue.service!.id,
    };

    return AsyncValueView<List<Service>>(
      value: services,
      onRetry: () => ref.invalidate(servicesProvider(branchId)),
      data: (items) {
        final List<Service> free = items
            .where((service) => !taken.contains(service.id))
            .toList();
        if (free.isEmpty) {
          return DoryNotice(message: l10n.queueNoServices);
        }
        final Service service = free.firstWhere(
          (item) => item.id == selected,
          orElse: () => free.first,
        );
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            DorySectionHeader(title: l10n.queueServiceLabel),
            RadioGroup<String>(
              groupValue: service.id,
              onChanged: (value) {
                if (value != null) {
                  onSelected(value);
                }
              },
              child: DoryCard(
                padding: EdgeInsets.zero,
                child: Column(
                  children: <Widget>[
                    for (final Service item in free)
                      RadioListTile<String>(
                        value: item.id,
                        title: Text(item.name),
                        subtitle: Text(
                          l10n.serviceDurationValue(item.durationMinutes),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.xs),
              child: Text(
                l10n.queueServiceHelper,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            QueueForm(
              key: ValueKey<String>('queue-form-${service.id}'),
              businessId: businessId,
              branchId: branchId,
              initial: QueueDraft(
                serviceId: service.id,
                name: service.name,
                ticketPrefix: _suggestPrefix(service.name),
              ),
            ),
          ],
        );
      },
    );
  }

  /// "Haircut" → "HAI"; names without Latin letters fall back to "A".
  static String _suggestPrefix(String name) {
    final String letters = name.toUpperCase().replaceAll(RegExp('[^A-Z]'), '');
    return letters.isEmpty
        ? 'A'
        : letters.substring(0, letters.length.clamp(1, 3));
  }
}

/// Edit Queue Configuration.
class EditQueuePage extends ConsumerWidget {
  const EditQueuePage({super.key, required this.queueId});

  final String queueId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<QueueConfig> queue = ref.watch(queueProvider(queueId));
    return DoryPageScaffold(
      title: context.l10n.queueEditTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<QueueConfig>(
        value: queue,
        onRetry: () => ref.invalidate(queueProvider(queueId)),
        data: (value) => QueueForm(
          queueId: queueId,
          branchId: value.branch.id,
          initial: value.toDraft(),
        ),
      ),
    );
  }
}

/// The queue configuration form shared by create and edit.
class QueueForm extends ConsumerStatefulWidget {
  const QueueForm({
    super.key,
    required this.branchId,
    required this.initial,
    this.queueId,
    this.businessId,
  });

  final String branchId;

  /// For the plan prompt when a new queue is over the plan's limit.
  final String? businessId;

  /// Null when creating.
  final String? queueId;
  final QueueDraft initial;

  @override
  ConsumerState<QueueForm> createState() => _QueueFormState();
}

class _QueueFormState extends ConsumerState<QueueForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: widget.initial.name,
  );
  late final TextEditingController _prefix = TextEditingController(
    text: widget.initial.ticketPrefix,
  );
  late final TextEditingController _capacity = TextEditingController(
    text: widget.initial.capacity?.toString() ?? '',
  );
  late final TextEditingController _duration = TextEditingController(
    text: widget.initial.estimatedServiceMinutes?.toString() ?? '',
  );
  late final TextEditingController _grace = TextEditingController(
    text: '${widget.initial.gracePeriodSeconds}',
  );
  late bool _isActive = widget.initial.isActive;
  late bool _allowGuests = widget.initial.allowGuestJoin;
  late bool _guestName = widget.initial.requireGuestName;
  late bool _guestPhone = widget.initial.requireGuestPhone;
  bool _busy = false;
  AppFailure? _failure;

  static final RegExp _prefixPattern = RegExp(r'^[A-Z][A-Z0-9]{0,5}$');

  @override
  void initState() {
    super.initState();
    _prefix.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _name.dispose();
    _prefix.dispose();
    _capacity.dispose();
    _duration.dispose();
    _grace.dispose();
    super.dispose();
  }

  String? _number(String? value, int min, int max, {required bool optional}) {
    final String text = value?.trim() ?? '';
    if (text.isEmpty && optional) {
      return null;
    }
    final int? number = int.tryParse(text);
    return number == null || number < min || number > max
        ? context.l10n.queueValidationNumber(min, max)
        : null;
  }

  int? _optionalInt(TextEditingController controller) =>
      int.tryParse(controller.text.trim());

  Future<void> _save() async {
    if (_busy || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    final QueueDraft draft = QueueDraft(
      serviceId: widget.initial.serviceId,
      name: _name.text.trim(),
      ticketPrefix: _prefix.text.trim().toUpperCase(),
      isActive: _isActive,
      capacity: _optionalInt(_capacity),
      allowGuestJoin: _allowGuests,
      requireGuestName: _guestName,
      requireGuestPhone: _guestPhone,
      estimatedServiceMinutes: _optionalInt(_duration),
      gracePeriodSeconds: int.parse(_grace.text.trim()),
    );
    await _run(() async {
      final QueueActions actions = ref.read(queueActionsProvider);
      final String? queueId = widget.queueId;
      final QueueConfig saved = queueId == null
          ? await actions.create(widget.branchId, draft)
          : await actions.update(queueId, draft);
      if (mounted) {
        final AppLocalizations l10n = context.l10n;
        showResultMessage(
          context,
          queueId == null ? l10n.queueCreated : l10n.commonSaved,
        );
        context.go(AppRoutes.queue(saved.id));
      }
    });
  }

  Future<void> _delete() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.queueDeleteConfirmTitle,
      message: l10n.queueDeleteConfirmMessage,
      confirmLabel: l10n.queueDelete,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    await _run(() async {
      await ref.read(queueActionsProvider).delete(widget.queueId!);
      if (mounted) {
        showResultMessage(context, l10n.queueDeleted);
        context.go(AppRoutes.businessQueues);
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
        final String? businessId = widget.businessId;
        if (failure.isPlanLimit && businessId != null) {
          unawaited(
            showPlanLimitDialog(context, failure, businessId: businessId),
          );
        }
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
              message:
                  errors['service'] ??
                  errors['ticket_prefix'] ??
                  queueFailureMessage(l10n, failure),
              tone: DoryNoticeTone.error,
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          DoryTextField(
            label: l10n.queueNameLabel,
            controller: _name,
            textCapitalization: TextCapitalization.sentences,
            enabled: !_busy,
            errorText: errors['name'],
            validator: (value) {
              final int length = value?.trim().length ?? 0;
              return length < 2 || length > 150
                  ? l10n.queueValidationName
                  : null;
            },
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.queuePrefixLabel,
            controller: _prefix,
            textCapitalization: TextCapitalization.characters,
            textDirection: TextDirection.ltr,
            enabled: !_busy,
            errorText: errors['ticket_prefix'],
            helperText: l10n.queuePrefixHelper(
              firstTicketExample(_prefix.text.trim()),
            ),
            validator: (value) =>
                _prefixPattern.hasMatch(value?.trim().toUpperCase() ?? '')
                ? null
                : l10n.queueValidationPrefix,
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.queueDurationLabel,
            controller: _duration,
            keyboardType: TextInputType.number,
            textDirection: TextDirection.ltr,
            enabled: !_busy,
            helperText: l10n.queueDurationHelper,
            errorText: errors['estimated_service_duration'],
            validator: (value) => _number(value, 1, 1440, optional: true),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.queueCapacityLabel,
            controller: _capacity,
            keyboardType: TextInputType.number,
            textDirection: TextDirection.ltr,
            enabled: !_busy,
            helperText: l10n.queueCapacityHelper,
            errorText: errors['capacity'],
            validator: (value) => _number(value, 1, 10000, optional: true),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.queueGraceLabel,
            controller: _grace,
            keyboardType: TextInputType.number,
            textDirection: TextDirection.ltr,
            enabled: !_busy,
            helperText: l10n.queueGraceHelper,
            errorText: errors['grace_period_seconds'],
            validator: (value) => _number(value, 0, 3600, optional: false),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryCard(
            padding: EdgeInsets.zero,
            child: Column(
              children: <Widget>[
                SwitchListTile(
                  title: Text(l10n.queueGuestJoinLabel),
                  value: _allowGuests,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _allowGuests = value),
                ),
                SwitchListTile(
                  title: Text(l10n.queueGuestNameLabel),
                  value: _guestName,
                  onChanged: _busy || !_allowGuests
                      ? null
                      : (value) => setState(() => _guestName = value),
                ),
                SwitchListTile(
                  title: Text(l10n.queueGuestPhoneLabel),
                  value: _guestPhone,
                  onChanged: _busy || !_allowGuests
                      ? null
                      : (value) => setState(() => _guestPhone = value),
                ),
                SwitchListTile(
                  title: Text(l10n.queueActiveLabel),
                  value: _isActive,
                  onChanged: _busy
                      ? null
                      : (value) => setState(() => _isActive = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: widget.queueId == null ? l10n.commonCreate : l10n.commonSave,
            isLoading: _busy,
            onPressed: _save,
          ),
          if (widget.queueId != null) ...<Widget>[
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.queueDelete,
              icon: Icons.delete_outline_rounded,
              variant: DoryButtonVariant.tertiary,
              onPressed: _busy ? null : _delete,
            ),
          ],
        ],
      ),
    );
  }
}
