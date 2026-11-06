import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/queues/domain/entities/queue_entities.dart';
import 'package:dory_app/features/queues/presentation/providers/queue_providers.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_qr_code.dart';
import 'package:dory_app/features/queues/presentation/widgets/queue_widgets.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Queue QR Code & Public Link: what customers scan to join as guests.
class QueueQrPage extends ConsumerStatefulWidget {
  const QueueQrPage({super.key, required this.queueId});

  final String queueId;

  @override
  ConsumerState<QueueQrPage> createState() => _QueueQrPageState();
}

class _QueueQrPageState extends ConsumerState<QueueQrPage> {
  bool _busy = false;

  Future<void> _rotate() async {
    final AppLocalizations l10n = context.l10n;
    final bool confirmed = await confirmAction(
      context,
      title: l10n.queueRotateConfirmTitle,
      message: l10n.queueRotateConfirmMessage,
      confirmLabel: l10n.queueRotate,
      destructive: true,
    );
    if (!confirmed || !mounted) {
      return;
    }
    setState(() => _busy = true);
    await runQueueAction(
      context,
      () => ref.read(queueActionsProvider).rotatePublicLink(widget.queueId),
      successMessage: l10n.queueRotated,
    );
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  Future<void> _copy(String url) async {
    await Clipboard.setData(ClipboardData(text: url));
    if (mounted) {
      showResultMessage(context, context.l10n.commonCopied);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AsyncValue<QueuePublicLink> link = ref.watch(
      queuePublicLinkProvider(widget.queueId),
    );
    final String name =
        ref.watch(queueProvider(widget.queueId)).value?.name ?? '';

    return DoryPageScaffold(
      title: l10n.queueQrTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<QueuePublicLink>(
        value: link,
        onRetry: () => ref.invalidate(queuePublicLinkProvider(widget.queueId)),
        data: (value) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (!value.allowGuestJoin) ...<Widget>[
              DoryNotice(
                message: l10n.queueQrGuestsOff,
                tone: DoryNoticeTone.warning,
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
            DoryCard(
              child: Column(
                children: <Widget>[
                  if (name.isNotEmpty) ...<Widget>[
                    Text(name, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: AppSpacing.md),
                  ],
                  Center(
                    child: QueueQrCode(
                      data: value.qrPayload,
                      semanticLabel: l10n.queueQrSemantics(name),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    l10n.queueQrMessage,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            DorySectionHeader(title: l10n.queueLinkLabel),
            DoryCard(
              child: SelectableText(
                value.url,
                textDirection: TextDirection.ltr,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.commonCopy,
              icon: Icons.copy_rounded,
              variant: DoryButtonVariant.secondary,
              onPressed: () => _copy(value.url),
            ),
            const SizedBox(height: AppSpacing.md),
            DoryButton(
              label: l10n.queueRotate,
              icon: Icons.autorenew_rounded,
              variant: DoryButtonVariant.tertiary,
              isLoading: _busy,
              onPressed: _rotate,
            ),
          ],
        ),
      ),
    );
  }
}
