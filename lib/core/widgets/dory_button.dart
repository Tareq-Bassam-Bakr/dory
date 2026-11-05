import 'package:material_ui/material_ui.dart';

/// Visual emphasis of a [DoryButton].
enum DoryButtonVariant {
  /// Main action of a screen. Filled with the brand color.
  primary,

  /// Alternative action. Outlined.
  secondary,

  /// Low-emphasis action. Text only.
  tertiary,
}

/// The DORY button. Styling comes from the theme's button themes.
///
/// While [isLoading] is true the button is disabled and shows a spinner, but
/// keeps its size and its label for screen readers.
class DoryButton extends StatelessWidget {
  const DoryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = DoryButtonVariant.primary,
    this.icon,
    this.isLoading = false,
    this.expand = true,
  });

  final String label;

  /// `null` disables the button.
  final VoidCallback? onPressed;
  final DoryButtonVariant variant;
  final IconData? icon;
  final bool isLoading;

  /// Whether the button fills the available width.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final VoidCallback? effectiveOnPressed = isLoading ? null : onPressed;
    final Widget content = isLoading
        ? _LoadingLabel(label: label)
        : Text(label);
    final Widget? leading = isLoading || icon == null ? null : Icon(icon);

    final Widget button = switch (variant) {
      DoryButtonVariant.primary =>
        leading == null
            ? FilledButton(onPressed: effectiveOnPressed, child: content)
            : FilledButton.icon(
                onPressed: effectiveOnPressed,
                icon: leading,
                label: content,
              ),
      DoryButtonVariant.secondary =>
        leading == null
            ? OutlinedButton(onPressed: effectiveOnPressed, child: content)
            : OutlinedButton.icon(
                onPressed: effectiveOnPressed,
                icon: leading,
                label: content,
              ),
      DoryButtonVariant.tertiary =>
        leading == null
            ? TextButton(onPressed: effectiveOnPressed, child: content)
            : TextButton.icon(
                onPressed: effectiveOnPressed,
                icon: leading,
                label: content,
              ),
    };

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

class _LoadingLabel extends StatelessWidget {
  const _LoadingLabel({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Stack(
      alignment: Alignment.center,
      children: <Widget>[
        // Invisible label keeps the button width and the semantics label.
        Opacity(opacity: 0, alwaysIncludeSemantics: true, child: Text(label)),
        const SizedBox.square(
          dimension: 20,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ],
    );
  }
}
