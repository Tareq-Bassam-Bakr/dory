import 'dart:async';

import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/discovery/domain/entities/discovery_entities.dart';
import 'package:dory_app/features/discovery/presentation/providers/discovery_providers.dart';
import 'package:dory_app/features/discovery/presentation/widgets/discovery_widgets.dart';
import 'package:dory_app/features/favorites/presentation/widgets/favorite_button.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:url_launcher/url_launcher.dart';

/// A business's public profile and its branches.
class BusinessDetailsPage extends ConsumerWidget {
  const BusinessDetailsPage({super.key, required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<PublicBusiness> business = ref.watch(
      publicBusinessProvider(slug),
    );

    return DoryPageScaffold(
      title: business.value?.name,
      actions: <Widget>[
        if (business.value != null) FavoriteButton(business: business.value!),
      ],
      body: AsyncValueView<PublicBusiness>(
        value: business,
        onRetry: () => ref.invalidate(publicBusinessProvider(slug)),
        data: (data) => _Profile(business: data),
      ),
    );
  }
}

class _Profile extends ConsumerWidget {
  const _Profile({required this.business});

  final PublicBusiness business;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AppLocalizations l10n = context.l10n;
    final TextTheme text = Theme.of(context).textTheme;
    final String language = Localizations.localeOf(context).languageCode;
    final String? cover = business.coverUrl;
    final RatingSummary? rating = business.rating;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (cover != null) ...<Widget>[
          ClipRRect(
            borderRadius: AppRadii.lgAll,
            child: AspectRatio(
              aspectRatio: 16 / 7,
              child: Image.network(
                cover,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) =>
                    const ColoredBox(color: AppColors.primaryContainer),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        Row(
          children: <Widget>[
            BusinessLogo(
              url: business.logoUrl,
              category: business.category,
              size: 64,
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(business.name, style: text.titleLarge),
                  if (business.category != null)
                    Text(
                      business.category!.nameFor(language),
                      style: text.bodyMedium,
                    ),
                  if (rating != null) RatingLabel(rating: rating),
                ],
              ),
            ),
          ],
        ),
        if (business.description != null) ...<Widget>[
          const SizedBox(height: AppSpacing.md),
          Text(business.description!, style: text.bodyMedium),
        ],
        ?_contact(context, l10n),
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.businessDetailsBranches),
        _Branches(slug: business.slug),
      ],
    );
  }

  /// Only the contact details the business itself published.
  Widget? _contact(BuildContext context, AppLocalizations l10n) {
    final String? phone = business.phone;
    final String? email = business.email;
    final String? website = business.website;
    if (phone == null && email == null && website == null) {
      return null;
    }

    Widget tile(IconData icon, String value, Uri uri) => ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: AppColors.primary),
      title: Text(value, textDirection: TextDirection.ltr),
      onTap: () => unawaited(launchUrl(uri)),
    );

    final Uri? site = website == null ? null : Uri.tryParse(website);
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.md),
      child: DoryCard(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
        child: Column(
          children: <Widget>[
            if (phone != null)
              tile(Icons.call_outlined, phone, Uri(scheme: 'tel', path: phone)),
            if (email != null)
              tile(
                Icons.mail_outline_rounded,
                email,
                Uri(scheme: 'mailto', path: email),
              ),
            if (website != null && site != null)
              tile(Icons.language_rounded, website, site),
          ],
        ),
      ),
    );
  }
}

class _Branches extends ConsumerWidget {
  const _Branches({required this.slug});

  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return SectionValue<List<PublicBranch>>(
      value: ref.watch(publicBranchesProvider(slug)),
      onRetry: () => ref.invalidate(publicBranchesProvider(slug)),
      data: (branches) => CardColumn(
        children: <Widget>[
          for (final PublicBranch branch in branches)
            BranchCard(branch: branch, showBusiness: false),
        ],
      ),
    );
  }
}
