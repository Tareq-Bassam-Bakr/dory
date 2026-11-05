import 'package:material_ui/material_ui.dart';

/// Labels for the password visibility toggle of [DoryTextField].
class PasswordVisibilityLabels {
  const PasswordVisibilityLabels({required this.show, required this.hide});

  final String show;
  final String hide;
}

/// The DORY text input. Styling comes from the theme's input decoration.
///
/// Pass [passwordVisibilityLabels] to make it a password field: the text is
/// obscured and a show/hide toggle is added.
class DoryTextField extends StatefulWidget {
  const DoryTextField({
    super.key,
    required this.label,
    this.controller,
    this.hint,
    this.prefixIcon,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.passwordVisibilityLabels,
    this.textDirection,
    this.validator,
    this.onChanged,
    this.onFieldSubmitted,
    this.enabled = true,
  });

  final String label;
  final TextEditingController? controller;
  final String? hint;
  final IconData? prefixIcon;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final PasswordVisibilityLabels? passwordVisibilityLabels;

  /// Forces the direction of the typed text, e.g. [TextDirection.ltr] for
  /// emails and phone numbers inside an Arabic (RTL) layout.
  final TextDirection? textDirection;
  final FormFieldValidator<String>? validator;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onFieldSubmitted;
  final bool enabled;

  @override
  State<DoryTextField> createState() => _DoryTextFieldState();
}

class _DoryTextFieldState extends State<DoryTextField> {
  late bool _obscured = widget.passwordVisibilityLabels != null;

  bool get _isPassword => widget.passwordVisibilityLabels != null;

  @override
  Widget build(BuildContext context) {
    final PasswordVisibilityLabels? labels = widget.passwordVisibilityLabels;

    return TextFormField(
      controller: widget.controller,
      enabled: widget.enabled,
      keyboardType: widget.keyboardType,
      textInputAction: widget.textInputAction,
      autofillHints: widget.autofillHints,
      textDirection: widget.textDirection,
      obscureText: _obscured,
      enableSuggestions: !_isPassword,
      autocorrect: !_isPassword,
      validator: widget.validator,
      onChanged: widget.onChanged,
      onFieldSubmitted: widget.onFieldSubmitted,
      decoration: InputDecoration(
        labelText: widget.label,
        hintText: widget.hint,
        prefixIcon: widget.prefixIcon == null ? null : Icon(widget.prefixIcon),
        suffixIcon: labels == null
            ? null
            : IconButton(
                tooltip: _obscured ? labels.show : labels.hide,
                icon: Icon(
                  _obscured
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                ),
                onPressed: () => setState(() => _obscured = !_obscured),
              ),
      ),
    );
  }
}
