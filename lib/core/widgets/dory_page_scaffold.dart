import 'package:dory_app/app/theme/theme.dart';
import 'package:material_ui/material_ui.dart';

/// Standard page layout: app bar, safe area, page gutters, and a content
/// column capped at [maxContentWidth] so it stays readable on tablets.
class DoryPageScaffold extends StatelessWidget {
  const DoryPageScaffold({
    super.key,
    required this.body,
    this.title,
    this.titleWidget,
    this.actions,
    this.showAppBar = true,
    this.scrollable = true,
    this.maxContentWidth = AppLayout.maxContentWidth,
    this.padding = const EdgeInsets.fromLTRB(
      AppSpacing.pageGutter,
      AppSpacing.sm,
      AppSpacing.pageGutter,
      AppSpacing.xl,
    ),
    this.backgroundColor,
  }) : assert(
         title == null || titleWidget == null,
         'Provide either title or titleWidget, not both.',
       );

  final Widget body;
  final String? title;
  final Widget? titleWidget;
  final List<Widget>? actions;
  final bool showAppBar;

  /// Wraps [body] in a scroll view. Disable for bodies that center content
  /// in the viewport or manage their own scrolling.
  final bool scrollable;
  final double maxContentWidth;
  final EdgeInsetsGeometry padding;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) {
    final String? title = this.title;

    final Widget constrained = Align(
      alignment: AlignmentDirectional.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxContentWidth),
        child: body,
      ),
    );

    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: showAppBar
          ? AppBar(
              title: titleWidget ?? (title == null ? null : Text(title)),
              actions: actions,
              backgroundColor: backgroundColor,
            )
          : null,
      body: SafeArea(
        top: !showAppBar,
        child: scrollable
            ? SingleChildScrollView(padding: padding, child: constrained)
            : Padding(padding: padding, child: constrained),
      ),
    );
  }
}
