import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// Brand tagline shown under the DORY name.
  ///
  /// In en, this message translates to:
  /// **'Your Turn. Your Time.'**
  String get appTagline;

  /// Button that retries a failed action.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get commonRetry;

  /// Snackbar shown when the user taps a feature that is not available yet.
  ///
  /// In en, this message translates to:
  /// **'This feature will be available in an upcoming update.'**
  String get commonComingSoonMessage;

  /// Title of a generic error state.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong'**
  String get commonGenericErrorTitle;

  /// Main headline on the welcome screen.
  ///
  /// In en, this message translates to:
  /// **'Smart queues for every service'**
  String get welcomeHeadline;

  /// Supporting text on the welcome screen.
  ///
  /// In en, this message translates to:
  /// **'Join queues remotely, follow your turn live, and arrive right on time.'**
  String get welcomeBody;

  /// Welcome screen button that opens the login screen.
  ///
  /// In en, this message translates to:
  /// **'Log in'**
  String get welcomeLogin;

  /// Welcome screen button that opens the registration screen.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get welcomeCreateAccount;

  /// Welcome screen button that opens the customer home without an account.
  ///
  /// In en, this message translates to:
  /// **'Explore as a guest'**
  String get welcomeExploreAsGuest;

  /// Label of the email input.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get authEmailLabel;

  /// Label of the password input.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get authPasswordLabel;

  /// Label of the full name input on registration.
  ///
  /// In en, this message translates to:
  /// **'Full name'**
  String get authFullNameLabel;

  /// Label of the phone number input on registration.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get authPhoneLabel;

  /// Tooltip of the button that reveals the password.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get authShowPassword;

  /// Tooltip of the button that hides the password.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get authHidePassword;

  /// Login screen title.
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get loginTitle;

  /// Login screen subtitle.
  ///
  /// In en, this message translates to:
  /// **'Log in to manage your tickets and your businesses.'**
  String get loginSubtitle;

  /// Login form submit button.
  ///
  /// In en, this message translates to:
  /// **'Log in'**
  String get loginSubmit;

  /// Prompt before the link to registration.
  ///
  /// In en, this message translates to:
  /// **'Don\'t have an account?'**
  String get loginNoAccountPrompt;

  /// Registration screen title.
  ///
  /// In en, this message translates to:
  /// **'Create your account'**
  String get registerTitle;

  /// Registration screen subtitle.
  ///
  /// In en, this message translates to:
  /// **'Join DORY and spend less time waiting in line.'**
  String get registerSubtitle;

  /// Registration form submit button.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get registerSubmit;

  /// Prompt before the link to login.
  ///
  /// In en, this message translates to:
  /// **'Already have an account?'**
  String get registerHaveAccountPrompt;

  /// Customer navigation tab: home.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// Customer navigation tab: the user's queue tickets.
  ///
  /// In en, this message translates to:
  /// **'Tickets'**
  String get navTickets;

  /// Navigation tab: account and settings.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get navAccount;

  /// Business navigation tab: dashboard.
  ///
  /// In en, this message translates to:
  /// **'Dashboard'**
  String get navDashboard;

  /// Business navigation tab: queues.
  ///
  /// In en, this message translates to:
  /// **'Queues'**
  String get navQueues;

  /// Customer home headline.
  ///
  /// In en, this message translates to:
  /// **'Where are you heading today?'**
  String get homeTitle;

  /// Placeholder of the customer home search bar.
  ///
  /// In en, this message translates to:
  /// **'Search businesses and services'**
  String get homeSearchHint;

  /// Section title for business categories.
  ///
  /// In en, this message translates to:
  /// **'Categories'**
  String get homeCategoriesTitle;

  /// Section title for nearby businesses.
  ///
  /// In en, this message translates to:
  /// **'Nearby businesses'**
  String get homeNearbyTitle;

  /// Empty state title when there are no nearby businesses to show.
  ///
  /// In en, this message translates to:
  /// **'No nearby businesses yet'**
  String get homeNearbyEmptyTitle;

  /// Empty state message when there are no nearby businesses to show.
  ///
  /// In en, this message translates to:
  /// **'Businesses near you will appear here once location-based discovery is available.'**
  String get homeNearbyEmptyMessage;

  /// Business category: restaurants and cafes.
  ///
  /// In en, this message translates to:
  /// **'Restaurants'**
  String get categoryRestaurants;

  /// Business category: medical clinics.
  ///
  /// In en, this message translates to:
  /// **'Clinics'**
  String get categoryClinics;

  /// Business category: beauty and barber salons.
  ///
  /// In en, this message translates to:
  /// **'Salons'**
  String get categorySalons;

  /// Business category: banks.
  ///
  /// In en, this message translates to:
  /// **'Banks'**
  String get categoryBanks;

  /// Business category: government service centers.
  ///
  /// In en, this message translates to:
  /// **'Government services'**
  String get categoryGovernment;

  /// Business category: repair and maintenance shops.
  ///
  /// In en, this message translates to:
  /// **'Repair shops'**
  String get categoryRepair;

  /// Business category: any other service provider.
  ///
  /// In en, this message translates to:
  /// **'Other services'**
  String get categoryOther;

  /// Title of the customer tickets screen.
  ///
  /// In en, this message translates to:
  /// **'My tickets'**
  String get ticketsTitle;

  /// Empty state title on the tickets screen.
  ///
  /// In en, this message translates to:
  /// **'No active tickets'**
  String get ticketsEmptyTitle;

  /// Empty state message on the tickets screen.
  ///
  /// In en, this message translates to:
  /// **'When you join a queue, your digital ticket and live position will appear here.'**
  String get ticketsEmptyMessage;

  /// Title of the account screen.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get accountTitle;

  /// Account screen header when the user is not signed in.
  ///
  /// In en, this message translates to:
  /// **'You are browsing as a guest'**
  String get accountGuestTitle;

  /// Account screen message when the user is not signed in.
  ///
  /// In en, this message translates to:
  /// **'Log in to join queues and get notified when your turn is near.'**
  String get accountGuestMessage;

  /// Account screen section title for app preferences.
  ///
  /// In en, this message translates to:
  /// **'Preferences'**
  String get accountPreferencesSection;

  /// Label of the language setting.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get accountLanguage;

  /// Account screen section title for switching between customer and business workspaces.
  ///
  /// In en, this message translates to:
  /// **'Workspace'**
  String get accountWorkspaceSection;

  /// Action that opens the business workspace.
  ///
  /// In en, this message translates to:
  /// **'Switch to business workspace'**
  String get accountSwitchToBusiness;

  /// Description of the business workspace action.
  ///
  /// In en, this message translates to:
  /// **'Manage branches, queues, and staff.'**
  String get accountSwitchToBusinessDescription;

  /// Action that opens the customer workspace.
  ///
  /// In en, this message translates to:
  /// **'Switch to customer workspace'**
  String get accountSwitchToCustomer;

  /// Description of the customer workspace action.
  ///
  /// In en, this message translates to:
  /// **'Discover businesses and join queues.'**
  String get accountSwitchToCustomerDescription;

  /// Language option that follows the device language.
  ///
  /// In en, this message translates to:
  /// **'Device language'**
  String get languageSystem;

  /// Name of the English language, written in English in every locale.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get languageEnglish;

  /// Name of the Arabic language, written in Arabic in every locale.
  ///
  /// In en, this message translates to:
  /// **'العربية'**
  String get languageArabic;

  /// Business dashboard title.
  ///
  /// In en, this message translates to:
  /// **'Dashboard'**
  String get dashboardTitle;

  /// Business dashboard overview section title.
  ///
  /// In en, this message translates to:
  /// **'Business overview'**
  String get dashboardOverviewTitle;

  /// Card title inviting the owner to register a business.
  ///
  /// In en, this message translates to:
  /// **'Set up your business'**
  String get dashboardSetupTitle;

  /// Card message inviting the owner to register a business.
  ///
  /// In en, this message translates to:
  /// **'Register your business, add branches and services, then start managing your queues.'**
  String get dashboardSetupMessage;

  /// Button that starts business registration.
  ///
  /// In en, this message translates to:
  /// **'Register a business'**
  String get dashboardSetupAction;

  /// Business dashboard queue summary section title.
  ///
  /// In en, this message translates to:
  /// **'Queue summary'**
  String get dashboardQueueSummaryTitle;

  /// Explains why queue metrics have no values yet.
  ///
  /// In en, this message translates to:
  /// **'Live figures appear once your queues are active.'**
  String get dashboardQueueSummaryEmpty;

  /// Queue metric: customers waiting.
  ///
  /// In en, this message translates to:
  /// **'Waiting'**
  String get dashboardMetricWaiting;

  /// Queue metric: customers currently being served.
  ///
  /// In en, this message translates to:
  /// **'Being served'**
  String get dashboardMetricServing;

  /// Queue metric: average waiting time.
  ///
  /// In en, this message translates to:
  /// **'Average wait'**
  String get dashboardMetricAverageWait;

  /// Queue metric: customers served today.
  ///
  /// In en, this message translates to:
  /// **'Served today'**
  String get dashboardMetricServedToday;

  /// Accessibility label for a metric that has no value yet.
  ///
  /// In en, this message translates to:
  /// **'No data'**
  String get dashboardMetricNoValue;

  /// Business dashboard employees section title.
  ///
  /// In en, this message translates to:
  /// **'Employees'**
  String get dashboardEmployeesTitle;

  /// Empty state title for the employees section.
  ///
  /// In en, this message translates to:
  /// **'No employees yet'**
  String get dashboardEmployeesEmptyTitle;

  /// Empty state message for the employees section.
  ///
  /// In en, this message translates to:
  /// **'Invite staff to call customers and run your service counters.'**
  String get dashboardEmployeesEmptyMessage;

  /// Business queues screen title.
  ///
  /// In en, this message translates to:
  /// **'Queues'**
  String get queuesTitle;

  /// Empty state title on the business queues screen.
  ///
  /// In en, this message translates to:
  /// **'No queues yet'**
  String get queuesEmptyTitle;

  /// Empty state message on the business queues screen.
  ///
  /// In en, this message translates to:
  /// **'Queues you create for your services will appear here.'**
  String get queuesEmptyMessage;

  /// Title shown for an unknown route.
  ///
  /// In en, this message translates to:
  /// **'Page not found'**
  String get notFoundTitle;

  /// Message shown for an unknown route.
  ///
  /// In en, this message translates to:
  /// **'The page you are looking for does not exist or has moved.'**
  String get notFoundMessage;

  /// Button on the not-found screen that returns to the start.
  ///
  /// In en, this message translates to:
  /// **'Back to start'**
  String get notFoundAction;

  /// Validation error when the email field is empty.
  ///
  /// In en, this message translates to:
  /// **'Enter your email address.'**
  String get authEmailRequired;

  /// Validation error when the email is malformed.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email address.'**
  String get authEmailInvalid;

  /// Validation error when the password field is empty.
  ///
  /// In en, this message translates to:
  /// **'Enter your password.'**
  String get authPasswordRequired;

  /// Validation error for the full name on registration.
  ///
  /// In en, this message translates to:
  /// **'Enter your name (2 to 120 characters).'**
  String get authNameInvalid;

  /// Helper text under the optional phone field on registration.
  ///
  /// In en, this message translates to:
  /// **'Optional. International format, e.g. +963912345678'**
  String get authPhoneHint;

  /// Validation error for a malformed phone number.
  ///
  /// In en, this message translates to:
  /// **'Use the international format, e.g. +963912345678.'**
  String get authPhoneInvalid;

  /// Password requirements shown under the new password field.
  ///
  /// In en, this message translates to:
  /// **'At least 8 characters, with upper and lower case letters and a number.'**
  String get authPasswordRules;

  /// Validation error when the new password is too weak.
  ///
  /// In en, this message translates to:
  /// **'The password does not meet the requirements below.'**
  String get authPasswordTooWeak;

  /// Label of the password confirmation field on registration.
  ///
  /// In en, this message translates to:
  /// **'Confirm password'**
  String get authConfirmPasswordLabel;

  /// Validation error when the confirmation differs from the password.
  ///
  /// In en, this message translates to:
  /// **'The passwords do not match.'**
  String get authPasswordMismatch;

  /// Error shown when login credentials are rejected.
  ///
  /// In en, this message translates to:
  /// **'The email or password is incorrect.'**
  String get authErrorInvalidCredentials;

  /// Error shown when a suspended or deactivated account tries to log in.
  ///
  /// In en, this message translates to:
  /// **'This account is not active. Please contact support.'**
  String get authErrorAccountInactive;

  /// Error shown when login or registration is rate limited.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please wait a minute and try again.'**
  String get authErrorRateLimited;

  /// Error shown when the server rejects some fields.
  ///
  /// In en, this message translates to:
  /// **'Please correct the highlighted fields.'**
  String get authErrorValidation;

  /// Notice on the login screen after the server rejected the stored session.
  ///
  /// In en, this message translates to:
  /// **'Your session has ended. Please log in again.'**
  String get authSessionExpired;

  /// Error shown when the server cannot be reached.
  ///
  /// In en, this message translates to:
  /// **'Cannot reach DORY. Check your connection and try again.'**
  String get errorNetwork;

  /// Error shown when the server fails (HTTP 5xx).
  ///
  /// In en, this message translates to:
  /// **'DORY is having trouble right now. Please try again shortly.'**
  String get errorServer;

  /// Fallback error message.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get errorUnknown;

  /// Label above the signed-in user's name on the account screen.
  ///
  /// In en, this message translates to:
  /// **'Signed in as'**
  String get accountSignedInSection;

  /// Button that ends the session.
  ///
  /// In en, this message translates to:
  /// **'Log out'**
  String get accountLogout;

  /// Title of the logout confirmation dialog.
  ///
  /// In en, this message translates to:
  /// **'Log out of DORY?'**
  String get accountLogoutConfirmTitle;

  /// Message of the logout confirmation dialog.
  ///
  /// In en, this message translates to:
  /// **'You will need to log in again to manage your businesses.'**
  String get accountLogoutConfirmMessage;

  /// Notice on the account screen when the session was restored without reaching the server.
  ///
  /// In en, this message translates to:
  /// **'You are offline. Showing your last known details.'**
  String get accountOfflineNotice;

  /// Button that dismisses a dialog without acting.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get commonCancel;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
