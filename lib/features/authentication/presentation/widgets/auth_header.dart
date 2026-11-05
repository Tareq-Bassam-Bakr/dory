import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Title and subtitle at the top of authentication screens.
class AuthHeader extends StatelessWidget {
  const AuthHeader({super.key, required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final TextTheme text = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Semantics(header: true, child: Text(title, style: text.headlineMedium)),
        const SizedBox(height: AppSpacing.sm),
        Text(
          subtitle,
          style: text.bodyLarge?.copyWith(color: AppColors.textSecondary),
        ),
      ],
    );
  }
}
