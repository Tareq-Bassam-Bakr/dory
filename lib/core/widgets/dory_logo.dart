import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/constants/app_constants.dart';
import 'package:material_ui/material_ui.dart';

/// DORY brand mark: a gradient tile with the "D" monogram.
///
/// Placeholder brand rendering built from design tokens. Replace with the
/// official vector logo once brand assets are delivered.
class DoryLogoMark extends StatelessWidget {
  const DoryLogoMark({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: AppColors.brandGradient,
          borderRadius: BorderRadius.circular(size * 0.28),
        ),
        child: Text(
          'D',
          style: TextStyle(
            fontFamily: AppTypography.fontFamily,
            fontSize: size * 0.55,
            fontWeight: FontWeight.w700,
            height: 1,
            color: AppColors.onPrimary,
          ),
        ),
      ),
    );
  }
}

/// DORY wordmark. The brand name is Latin in every locale.
class DoryWordmark extends StatelessWidget {
  const DoryWordmark({super.key, this.fontSize = 28, this.color});

  final double fontSize;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Text(
      AppConstants.appName,
      // The wordmark is Latin, so tracking is safe here (unlike Arabic text).
      style: TextStyle(
        fontFamily: AppTypography.fontFamily,
        fontSize: fontSize,
        fontWeight: FontWeight.w700,
        letterSpacing: fontSize * 0.12,
        height: 1.1,
        color: color ?? AppColors.primary,
      ),
      textDirection: TextDirection.ltr,
    );
  }
}

/// Logo mark above the wordmark, with an optional tagline.
class DoryLogo extends StatelessWidget {
  const DoryLogo({super.key, this.markSize = 72, this.tagline});

  final double markSize;
  final String? tagline;

  @override
  Widget build(BuildContext context) {
    final String? tagline = this.tagline;
    return Semantics(
      label: tagline == null
          ? AppConstants.appName
          : '${AppConstants.appName}. $tagline',
      excludeSemantics: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          DoryLogoMark(size: markSize),
          SizedBox(height: markSize * 0.25),
          DoryWordmark(fontSize: markSize * 0.42),
          if (tagline != null) ...<Widget>[
            const SizedBox(height: AppSpacing.sm),
            Text(
              tagline,
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}
