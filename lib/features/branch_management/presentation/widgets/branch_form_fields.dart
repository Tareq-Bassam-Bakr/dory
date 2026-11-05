import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/validation/business_validators.dart';
import 'package:material_ui/material_ui.dart';

/// Text controllers for the branch form, created from a [BranchDraft].
class BranchFieldControllers {
  BranchFieldControllers([BranchDraft draft = const BranchDraft()])
    : name = TextEditingController(text: draft.name),
      address = TextEditingController(text: draft.address),
      city = TextEditingController(text: draft.city),
      region = TextEditingController(text: draft.region),
      country = TextEditingController(text: draft.country),
      latitude = TextEditingController(text: draft.latitude),
      longitude = TextEditingController(text: draft.longitude),
      phone = TextEditingController(text: draft.phone),
      email = TextEditingController(text: draft.email),
      timezone = TextEditingController(text: draft.timezone);

  final TextEditingController name;
  final TextEditingController address;
  final TextEditingController city;
  final TextEditingController region;
  final TextEditingController country;
  final TextEditingController latitude;
  final TextEditingController longitude;
  final TextEditingController phone;
  final TextEditingController email;
  final TextEditingController timezone;

  BranchDraft toDraft({bool isActive = true}) => BranchDraft(
    name: name.text.trim(),
    address: address.text.trim(),
    city: city.text.trim(),
    region: region.text.trim(),
    country: country.text.trim().toUpperCase(),
    latitude: latitude.text.trim(),
    longitude: longitude.text.trim(),
    phone: phone.text.replaceAll(RegExp(r'[\s\-.()]'), ''),
    email: email.text.trim().toLowerCase(),
    timezone: timezone.text.trim(),
    isActive: isActive,
  );

  void dispose() {
    for (final TextEditingController controller in <TextEditingController>[
      name,
      address,
      city,
      region,
      country,
      latitude,
      longitude,
      phone,
      email,
      timezone,
    ]) {
      controller.dispose();
    }
  }
}

/// The branch fields, shared by onboarding (step 3) and the branch form.
///
/// [errors] are server messages keyed by API field; [errorPrefix] is
/// "branch." when the fields are nested in the onboarding request.
class BranchFormFields extends StatelessWidget {
  const BranchFormFields({
    super.key,
    required this.controllers,
    this.errors = const <String, String>{},
    this.errorPrefix = '',
    this.enabled = true,
  });

  final BranchFieldControllers controllers;
  final Map<String, String> errors;
  final String errorPrefix;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    String? error(String field) => errors['$errorPrefix$field'];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        DoryTextField(
          label: l10n.branchNameLabel,
          controller: controllers.name,
          prefixIcon: Icons.store_mall_directory_outlined,
          textInputAction: TextInputAction.next,
          textCapitalization: TextCapitalization.words,
          enabled: enabled,
          errorText: error('name'),
          validator: (value) => BusinessValidators.name(l10n, value),
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.branchAddressLabel,
          controller: controllers.address,
          prefixIcon: Icons.place_outlined,
          textInputAction: TextInputAction.next,
          enabled: enabled,
          errorText: error('address'),
        ),
        const SizedBox(height: AppSpacing.lg),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              flex: 3,
              child: DoryTextField(
                label: l10n.branchCityLabel,
                controller: controllers.city,
                textInputAction: TextInputAction.next,
                enabled: enabled,
                errorText: error('city'),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              flex: 2,
              child: DoryTextField(
                label: l10n.branchCountryLabel,
                hint: 'SY',
                controller: controllers.country,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.characters,
                textDirection: TextDirection.ltr,
                enabled: enabled,
                errorText: error('country'),
                validator: (value) =>
                    BusinessValidators.optionalCountry(l10n, value),
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.branchRegionLabel,
          controller: controllers.region,
          textInputAction: TextInputAction.next,
          enabled: enabled,
          errorText: error('region'),
        ),
        const SizedBox(height: AppSpacing.xl),
        DorySectionHeader(title: l10n.branchLocationSection),
        Text(
          l10n.branchLocationHint,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: AppSpacing.md),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: DoryTextField(
                label: l10n.branchLatitudeLabel,
                hint: '33.5138',
                controller: controllers.latitude,
                keyboardType: const TextInputType.numberWithOptions(
                  signed: true,
                  decimal: true,
                ),
                textInputAction: TextInputAction.next,
                textDirection: TextDirection.ltr,
                enabled: enabled,
                errorText: error('latitude'),
                validator: (value) => BusinessValidators.latitude(
                  l10n,
                  value,
                  controllers.longitude.text,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: DoryTextField(
                label: l10n.branchLongitudeLabel,
                hint: '36.2765',
                controller: controllers.longitude,
                keyboardType: const TextInputType.numberWithOptions(
                  signed: true,
                  decimal: true,
                ),
                textInputAction: TextInputAction.next,
                textDirection: TextDirection.ltr,
                enabled: enabled,
                errorText: error('longitude'),
                validator: (value) => BusinessValidators.longitude(
                  l10n,
                  value,
                  controllers.latitude.text,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),
        DoryTextField(
          label: l10n.branchPhoneLabel,
          controller: controllers.phone,
          prefixIcon: Icons.phone_outlined,
          keyboardType: TextInputType.phone,
          textInputAction: TextInputAction.next,
          textDirection: TextDirection.ltr,
          enabled: enabled,
          errorText: error('phone'),
          validator: (value) => BusinessValidators.optionalPhone(l10n, value),
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.branchEmailLabel,
          controller: controllers.email,
          prefixIcon: Icons.mail_outline_rounded,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          textDirection: TextDirection.ltr,
          enabled: enabled,
          errorText: error('email'),
          validator: (value) => BusinessValidators.optionalEmail(l10n, value),
        ),
        const SizedBox(height: AppSpacing.lg),
        DoryTextField(
          label: l10n.timezoneLabel,
          hint: 'Asia/Damascus',
          helperText: l10n.timezoneHint,
          controller: controllers.timezone,
          prefixIcon: Icons.schedule_rounded,
          textInputAction: TextInputAction.done,
          textDirection: TextDirection.ltr,
          enabled: enabled,
          errorText: error('timezone'),
          validator: (value) =>
              BusinessValidators.optionalTimezone(l10n, value),
        ),
      ],
    );
  }
}
