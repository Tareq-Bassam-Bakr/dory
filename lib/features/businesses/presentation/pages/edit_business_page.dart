import 'package:dory_app/app/localization/l10n.dart';
import 'package:dory_app/app/router/app_routes.dart';
import 'package:dory_app/app/theme/theme.dart';
import 'package:dory_app/core/errors/app_failure.dart';
import 'package:dory_app/core/widgets/widgets.dart';
import 'package:dory_app/features/businesses/domain/entities/business.dart';
import 'package:dory_app/features/businesses/domain/entities/business_draft.dart';
import 'package:dory_app/features/businesses/presentation/providers/business_providers.dart';
import 'package:dory_app/features/businesses/presentation/validation/business_validators.dart';
import 'package:dory_app/features/businesses/presentation/widgets/category_picker.dart';
import 'package:dory_app/shared/components/async_value_view.dart';
import 'package:dory_app/shared/components/confirm_dialog.dart';
import 'package:dory_app/shared/components/failure_message.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';

/// Edits the business profile (owner, or manager with all-branch access).
class EditBusinessPage extends ConsumerWidget {
  const EditBusinessPage({super.key, required this.businessId});

  final String businessId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Business> business = ref.watch(
      businessProvider(businessId),
    );
    return DoryPageScaffold(
      title: context.l10n.editBusinessTitle,
      maxContentWidth: AppLayout.maxFormWidth,
      body: AsyncValueView<Business>(
        value: business,
        onRetry: () => ref.invalidate(businessProvider(businessId)),
        data: (value) => _EditBusinessForm(business: value),
      ),
    );
  }
}

class _EditBusinessForm extends ConsumerStatefulWidget {
  const _EditBusinessForm({required this.business});

  final Business business;

  @override
  ConsumerState<_EditBusinessForm> createState() => _EditBusinessFormState();
}

class _EditBusinessFormState extends ConsumerState<_EditBusinessForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _name = TextEditingController(
    text: widget.business.name,
  );
  late final TextEditingController _description = TextEditingController(
    text: widget.business.description,
  );
  late final TextEditingController _email = TextEditingController(
    text: widget.business.email,
  );
  late final TextEditingController _phone = TextEditingController(
    text: widget.business.phone,
  );
  late final TextEditingController _website = TextEditingController(
    text: widget.business.website,
  );
  late final TextEditingController _timezone = TextEditingController(
    text: widget.business.timezone,
  );
  late String? _category = widget.business.category?.slug;
  bool _saving = false;
  AppFailure? _failure;

  @override
  void dispose() {
    for (final TextEditingController c in <TextEditingController>[
      _name,
      _description,
      _email,
      _phone,
      _website,
      _timezone,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }
    setState(() {
      _saving = true;
      _failure = null;
    });
    try {
      await ref
          .read(businessActionsProvider)
          .update(
            widget.business.id,
            BusinessProfileChanges(
              name: _name.text,
              categorySlug: _category,
              description: _description.text,
              email: _email.text.toLowerCase(),
              phone: _phone.text.replaceAll(RegExp(r'[\s\-.()]'), ''),
              website: _website.text,
              timezone: _timezone.text.trim().isEmpty ? null : _timezone.text,
            ),
          );
      if (mounted) {
        showResultMessage(context, context.l10n.commonSaved);
        context.go(AppRoutes.business(widget.business.id));
      }
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() => _failure = failure);
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppLocalizations l10n = context.l10n;
    final AppFailure? failure = _failure;
    final Map<String, String> errors =
        failure?.fieldErrors ?? const <String, String>{};

    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          if (failure != null) ...<Widget>[
            DoryNotice(
              message: failureMessage(l10n, failure),
              tone: DoryNoticeTone.error,
            ),
            const SizedBox(height: AppSpacing.lg),
          ],
          DoryTextField(
            label: l10n.businessNameLabel,
            controller: _name,
            prefixIcon: Icons.storefront_outlined,
            textCapitalization: TextCapitalization.words,
            enabled: !_saving,
            errorText: errors['name'],
            validator: (value) => BusinessValidators.name(l10n, value),
          ),
          const SizedBox(height: AppSpacing.xl),
          CategoryPicker(
            selected: _category,
            errorText: errors['category'],
            enabled: !_saving,
            onChanged: (slug) => setState(() => _category = slug),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryTextField(
            label: l10n.businessDescriptionLabel,
            hint: l10n.businessDescriptionHint,
            controller: _description,
            maxLines: 4,
            minLines: 3,
            enabled: !_saving,
            errorText: errors['description'],
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.businessEmailLabel,
            controller: _email,
            prefixIcon: Icons.mail_outline_rounded,
            keyboardType: TextInputType.emailAddress,
            textDirection: TextDirection.ltr,
            enabled: !_saving,
            errorText: errors['email'],
            validator: (value) => BusinessValidators.optionalEmail(l10n, value),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.businessPhoneLabel,
            controller: _phone,
            prefixIcon: Icons.phone_outlined,
            keyboardType: TextInputType.phone,
            textDirection: TextDirection.ltr,
            enabled: !_saving,
            errorText: errors['phone'],
            validator: (value) => BusinessValidators.optionalPhone(l10n, value),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.businessWebsiteLabel,
            controller: _website,
            prefixIcon: Icons.language_rounded,
            keyboardType: TextInputType.url,
            textDirection: TextDirection.ltr,
            enabled: !_saving,
            errorText: errors['website'],
            validator: (value) =>
                BusinessValidators.optionalWebsite(l10n, value),
          ),
          const SizedBox(height: AppSpacing.lg),
          DoryTextField(
            label: l10n.timezoneLabel,
            helperText: l10n.timezoneHint,
            controller: _timezone,
            prefixIcon: Icons.schedule_rounded,
            textDirection: TextDirection.ltr,
            enabled: !_saving,
            errorText: errors['timezone'],
            validator: (value) =>
                BusinessValidators.optionalTimezone(l10n, value),
          ),
          const SizedBox(height: AppSpacing.xl),
          DoryButton(
            label: l10n.commonSave,
            isLoading: _saving,
            onPressed: _save,
          ),
        ],
      ),
    );
  }
}
