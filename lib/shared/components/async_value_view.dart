import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

/// Renders the loading, error and data states of an [AsyncValue] the same
/// way on every screen. The error state offers [onRetry].
class AsyncValueView<T> extends StatelessWidget {
  const AsyncValueView({
    super.key,
    required this.value,
    required this.data,
    required this.onRetry,
  });

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    return switch (value) {
      AsyncData<T>(:final T value) => data(value),
      AsyncError<T>(:final Object error) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 48),
        child: DoryErrorState(
          title: l10n.commonGenericErrorTitle,
          message: failureMessage(l10n, error),
          retry: DoryStatusAction(label: l10n.commonRetry, onPressed: onRetry),
        ),
      ),
      _ => const Padding(
        padding: EdgeInsets.symmetric(vertical: 48),
        child: DoryLoadingIndicator(),
      ),
    };
  }
}
