import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Centered progress indicator with an optional message.
class DoryLoadingIndicator extends StatelessWidget {
  const DoryLoadingIndicator({super.key, this.message, this.semanticsLabel});

  final String? message;

  /// Read by screen readers. Defaults to [message].
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) {
    final String? message = this.message;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          CircularProgressIndicator(semanticsLabel: semanticsLabel ?? message),
          if (message != null) ...<Widget>[
            const SizedBox(height: AppSpacing.lg),
            Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}
