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

  /// Status label: active.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get commonActive;

  /// Status label: inactive.
  ///
  /// In en, this message translates to:
  /// **'Inactive'**
  String get commonInactive;

  /// Status label: suspended by the platform.
  ///
  /// In en, this message translates to:
  /// **'Suspended'**
  String get commonSuspended;

  /// Button that returns to the previous step.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get commonBack;

  /// Button that continues to the next step.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get commonNext;

  /// Button that saves changes.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get commonSave;

  /// Button that creates a new item.
  ///
  /// In en, this message translates to:
  /// **'Create'**
  String get commonCreate;

  /// Button that opens editing.
  ///
  /// In en, this message translates to:
  /// **'Edit'**
  String get commonEdit;

  /// Button that deletes an item.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get commonDelete;

  /// Button that removes an item from a list.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get commonRemove;

  /// Button that copies text to the clipboard.
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get commonCopy;

  /// Confirmation after copying text.
  ///
  /// In en, this message translates to:
  /// **'Copied to the clipboard.'**
  String get commonCopied;

  /// Confirmation after saving changes.
  ///
  /// In en, this message translates to:
  /// **'Changes saved.'**
  String get commonSaved;

  /// Error when the server refuses an action (HTTP 403).
  ///
  /// In en, this message translates to:
  /// **'You don\'t have permission to do this.'**
  String get errorForbidden;

  /// Error when a resource does not exist or is not visible (HTTP 404).
  ///
  /// In en, this message translates to:
  /// **'This was not found. It may have been removed, or you no longer have access.'**
  String get errorNotFound;

  /// Error when the server rejects some fields.
  ///
  /// In en, this message translates to:
  /// **'Please correct the highlighted fields.'**
  String get errorValidation;

  /// Error when changing a business suspended by the platform.
  ///
  /// In en, this message translates to:
  /// **'This business is suspended, so changes are disabled.'**
  String get errorBusinessSuspended;

  /// Label of a time zone field.
  ///
  /// In en, this message translates to:
  /// **'Time zone'**
  String get timezoneLabel;

  /// Helper text of the time zone field.
  ///
  /// In en, this message translates to:
  /// **'IANA name, e.g. Asia/Damascus. Leave empty to keep the current one.'**
  String get timezoneHint;

  /// Validation error for a malformed time zone.
  ///
  /// In en, this message translates to:
  /// **'Use an IANA time zone, e.g. Asia/Damascus.'**
  String get timezoneInvalid;

  /// Title of the list of businesses the user works in.
  ///
  /// In en, this message translates to:
  /// **'My businesses'**
  String get myBusinessesTitle;

  /// Button that starts creating a business when the user already has one.
  ///
  /// In en, this message translates to:
  /// **'Create another business'**
  String get myBusinessesCreate;

  /// Button that opens an invitation link the user received.
  ///
  /// In en, this message translates to:
  /// **'I have an invitation'**
  String get myBusinessesJoin;

  /// Business role: owner.
  ///
  /// In en, this message translates to:
  /// **'Owner'**
  String get businessRoleOwner;

  /// Business role: manager.
  ///
  /// In en, this message translates to:
  /// **'Manager'**
  String get businessRoleManager;

  /// Business role: employee.
  ///
  /// In en, this message translates to:
  /// **'Employee'**
  String get businessRoleEmployee;

  /// The viewer's role in the business.
  ///
  /// In en, this message translates to:
  /// **'Your role: {role}'**
  String businessYourRole(String role);

  /// Business profile completion.
  ///
  /// In en, this message translates to:
  /// **'Profile {percent}% complete'**
  String businessProfileCompletion(int percent);

  /// Number of services.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{No services} =1{1 service} other{{count} services}}'**
  String businessStatsServices(int count);

  /// Title of the branches section and screen.
  ///
  /// In en, this message translates to:
  /// **'Branches'**
  String get businessBranches;

  /// Title of the team section and screen.
  ///
  /// In en, this message translates to:
  /// **'Team'**
  String get businessTeam;

  /// Section header for business management actions.
  ///
  /// In en, this message translates to:
  /// **'Manage'**
  String get businessManageSection;

  /// Opens the business profile editor.
  ///
  /// In en, this message translates to:
  /// **'Edit profile'**
  String get businessEditProfile;

  /// Switch that activates or deactivates the business.
  ///
  /// In en, this message translates to:
  /// **'Accepting customers'**
  String get businessStatusTitle;

  /// Shown when the business is active.
  ///
  /// In en, this message translates to:
  /// **'Customers can find this business.'**
  String get businessStatusActiveHint;

  /// Shown when the business is inactive.
  ///
  /// In en, this message translates to:
  /// **'Hidden from customers until you reactivate it.'**
  String get businessStatusInactiveHint;

  /// Notice on a business suspended by the platform.
  ///
  /// In en, this message translates to:
  /// **'DORY has suspended this business. You can view it, but changes are disabled.'**
  String get businessSuspendedNotice;

  /// Title of the profile completion card.
  ///
  /// In en, this message translates to:
  /// **'Complete your profile'**
  String get businessCompletionTitle;

  /// Title of the profile completion card when complete.
  ///
  /// In en, this message translates to:
  /// **'Your profile is complete'**
  String get businessCompletionDone;

  /// Missing profile step: category.
  ///
  /// In en, this message translates to:
  /// **'Choose a category'**
  String get businessMissingBasics;

  /// Missing profile step: description.
  ///
  /// In en, this message translates to:
  /// **'Add a description'**
  String get businessMissingDescription;

  /// Missing profile step: contact details.
  ///
  /// In en, this message translates to:
  /// **'Add an email or phone number'**
  String get businessMissingContact;

  /// Missing profile step: branch.
  ///
  /// In en, this message translates to:
  /// **'Add an active branch'**
  String get businessMissingBranch;

  /// Missing profile step: service.
  ///
  /// In en, this message translates to:
  /// **'Add an active service'**
  String get businessMissingService;

  /// Section header for irreversible actions.
  ///
  /// In en, this message translates to:
  /// **'Danger zone'**
  String get businessDangerZone;

  /// Button that deletes the business.
  ///
  /// In en, this message translates to:
  /// **'Delete business'**
  String get businessDelete;

  /// Title of the delete business confirmation.
  ///
  /// In en, this message translates to:
  /// **'Delete {name}?'**
  String businessDeleteConfirmTitle(String name);

  /// Message of the delete business confirmation.
  ///
  /// In en, this message translates to:
  /// **'The business, its branches and services will be archived, and your team will lose access. This cannot be undone in the app.'**
  String get businessDeleteConfirmMessage;

  /// Confirmation after deleting a business.
  ///
  /// In en, this message translates to:
  /// **'Business deleted.'**
  String get businessDeleted;

  /// Label of the business name field.
  ///
  /// In en, this message translates to:
  /// **'Business name'**
  String get businessNameLabel;

  /// Validation error for a name.
  ///
  /// In en, this message translates to:
  /// **'Enter a name between 2 and 150 characters.'**
  String get businessNameInvalid;

  /// Label of the category picker.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get businessCategoryLabel;

  /// Validation error when no category is chosen.
  ///
  /// In en, this message translates to:
  /// **'Choose a category.'**
  String get businessCategoryRequired;

  /// Shown when categories cannot be loaded.
  ///
  /// In en, this message translates to:
  /// **'Couldn\'t load categories.'**
  String get businessCategoriesLoadError;

  /// Label of the business description field.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get businessDescriptionLabel;

  /// Hint of the business description field.
  ///
  /// In en, this message translates to:
  /// **'What do you offer? Customers will see this.'**
  String get businessDescriptionHint;

  /// Hint above the contact fields.
  ///
  /// In en, this message translates to:
  /// **'Add an email or phone number so customers can reach you. Both are optional.'**
  String get businessContactHint;

  /// Label of the business email field.
  ///
  /// In en, this message translates to:
  /// **'Business email'**
  String get businessEmailLabel;

  /// Label of the business phone field.
  ///
  /// In en, this message translates to:
  /// **'Business phone'**
  String get businessPhoneLabel;

  /// Label of the website field.
  ///
  /// In en, this message translates to:
  /// **'Website'**
  String get businessWebsiteLabel;

  /// Validation error for a website.
  ///
  /// In en, this message translates to:
  /// **'Enter a full web address, e.g. https://example.com.'**
  String get businessWebsiteInvalid;

  /// Title of the business profile editor.
  ///
  /// In en, this message translates to:
  /// **'Edit business profile'**
  String get editBusinessTitle;

  /// Title of the business creation entry screen.
  ///
  /// In en, this message translates to:
  /// **'Create your business'**
  String get createBusinessTitle;

  /// Introduction on the business creation entry screen.
  ///
  /// In en, this message translates to:
  /// **'Set up your business in five short steps. You can change everything later.'**
  String get createBusinessIntro;

  /// Header above the list of onboarding steps.
  ///
  /// In en, this message translates to:
  /// **'What you will set up'**
  String get createBusinessStepsTitle;

  /// Button that starts the onboarding steps.
  ///
  /// In en, this message translates to:
  /// **'Start'**
  String get createBusinessStart;

  /// Title of the onboarding screen.
  ///
  /// In en, this message translates to:
  /// **'New business'**
  String get onboardingTitle;

  /// Onboarding progress.
  ///
  /// In en, this message translates to:
  /// **'Step {current} of {total}'**
  String onboardingStepProgress(int current, int total);

  /// Onboarding step 1.
  ///
  /// In en, this message translates to:
  /// **'Name and category'**
  String get onboardingStepBasics;

  /// Onboarding step 2.
  ///
  /// In en, this message translates to:
  /// **'Description and contact'**
  String get onboardingStepDetails;

  /// Onboarding step 3.
  ///
  /// In en, this message translates to:
  /// **'Main branch'**
  String get onboardingStepBranch;

  /// Onboarding step 4.
  ///
  /// In en, this message translates to:
  /// **'Services'**
  String get onboardingStepServices;

  /// Onboarding step 5.
  ///
  /// In en, this message translates to:
  /// **'Review'**
  String get onboardingStepReview;

  /// Introduction of the services step.
  ///
  /// In en, this message translates to:
  /// **'Add the services customers can queue for. You can add more later.'**
  String get onboardingServicesIntro;

  /// Button that adds a service in onboarding.
  ///
  /// In en, this message translates to:
  /// **'Add service'**
  String get onboardingAddService;

  /// Shown when no service was added in onboarding.
  ///
  /// In en, this message translates to:
  /// **'No services yet. You can add them later.'**
  String get onboardingNoServices;

  /// Introduction of the review step.
  ///
  /// In en, this message translates to:
  /// **'Check the details, then create your business.'**
  String get onboardingReviewIntro;

  /// Placeholder for an empty optional value.
  ///
  /// In en, this message translates to:
  /// **'Not provided'**
  String get onboardingNotProvided;

  /// Button that creates the business at the end of onboarding.
  ///
  /// In en, this message translates to:
  /// **'Create business'**
  String get onboardingCreate;

  /// Confirmation after creating a business.
  ///
  /// In en, this message translates to:
  /// **'Your business is ready.'**
  String get onboardingCreated;

  /// Title of the confirmation when leaving onboarding.
  ///
  /// In en, this message translates to:
  /// **'Discard this business?'**
  String get onboardingLeaveTitle;

  /// Message of the confirmation when leaving onboarding.
  ///
  /// In en, this message translates to:
  /// **'What you entered will be lost.'**
  String get onboardingLeaveMessage;

  /// Button that leaves onboarding and discards the input.
  ///
  /// In en, this message translates to:
  /// **'Discard'**
  String get onboardingLeaveConfirm;

  /// Button that opens the new branch form.
  ///
  /// In en, this message translates to:
  /// **'Add branch'**
  String get branchAdd;

  /// Empty state title of the branch list.
  ///
  /// In en, this message translates to:
  /// **'No branches yet'**
  String get branchesEmptyTitle;

  /// Empty state message for users who can add branches.
  ///
  /// In en, this message translates to:
  /// **'Add your first branch so customers know where to find you.'**
  String get branchesEmptyMessage;

  /// Empty state message for users limited to assigned branches.
  ///
  /// In en, this message translates to:
  /// **'No branches are assigned to you.'**
  String get branchesEmptyLimitedMessage;

  /// Title of the new branch form.
  ///
  /// In en, this message translates to:
  /// **'New branch'**
  String get branchCreateTitle;

  /// Title of the branch editor and its entry button.
  ///
  /// In en, this message translates to:
  /// **'Edit branch'**
  String get branchEditTitle;

  /// Confirmation after creating a branch.
  ///
  /// In en, this message translates to:
  /// **'Branch created.'**
  String get branchCreated;

  /// Label of the branch name field.
  ///
  /// In en, this message translates to:
  /// **'Branch name'**
  String get branchNameLabel;

  /// Default name suggested for the first branch.
  ///
  /// In en, this message translates to:
  /// **'Main branch'**
  String get branchNameDefault;

  /// Label of the address field.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get branchAddressLabel;

  /// Label of the city field.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get branchCityLabel;

  /// Label of the region field.
  ///
  /// In en, this message translates to:
  /// **'Region or state'**
  String get branchRegionLabel;

  /// Label of the two-letter country code field.
  ///
  /// In en, this message translates to:
  /// **'Country code'**
  String get branchCountryLabel;

  /// Validation error for a country code.
  ///
  /// In en, this message translates to:
  /// **'Use a two-letter country code, e.g. SY.'**
  String get branchCountryInvalid;

  /// Header of the coordinates fields.
  ///
  /// In en, this message translates to:
  /// **'Map location'**
  String get branchLocationSection;

  /// Explains the coordinates fields.
  ///
  /// In en, this message translates to:
  /// **'Optional. Decimal coordinates (WGS 84), as shown on openstreetmap.org.'**
  String get branchLocationHint;

  /// Label of the latitude field.
  ///
  /// In en, this message translates to:
  /// **'Latitude'**
  String get branchLatitudeLabel;

  /// Label of the longitude field.
  ///
  /// In en, this message translates to:
  /// **'Longitude'**
  String get branchLongitudeLabel;

  /// Validation error for latitude.
  ///
  /// In en, this message translates to:
  /// **'Latitude must be between -90 and 90.'**
  String get branchLatitudeInvalid;

  /// Validation error for longitude.
  ///
  /// In en, this message translates to:
  /// **'Longitude must be between -180 and 180.'**
  String get branchLongitudeInvalid;

  /// Validation error when only one coordinate is given.
  ///
  /// In en, this message translates to:
  /// **'Enter both latitude and longitude, or neither.'**
  String get branchCoordinatesPair;

  /// Shown when a branch has no coordinates.
  ///
  /// In en, this message translates to:
  /// **'No map location set'**
  String get branchNoLocation;

  /// Label of the branch phone field.
  ///
  /// In en, this message translates to:
  /// **'Branch phone'**
  String get branchPhoneLabel;

  /// Label of the branch email field.
  ///
  /// In en, this message translates to:
  /// **'Branch email'**
  String get branchEmailLabel;

  /// Switch that activates or deactivates a branch.
  ///
  /// In en, this message translates to:
  /// **'Branch is open'**
  String get branchStatusLabel;

  /// Header of the branch details.
  ///
  /// In en, this message translates to:
  /// **'Details'**
  String get branchDetailsSection;

  /// Button that deletes a branch.
  ///
  /// In en, this message translates to:
  /// **'Delete branch'**
  String get branchDelete;

  /// Title of the delete branch confirmation.
  ///
  /// In en, this message translates to:
  /// **'Delete this branch?'**
  String get branchDeleteConfirmTitle;

  /// Message of the delete branch confirmation.
  ///
  /// In en, this message translates to:
  /// **'The branch and its services will be archived.'**
  String get branchDeleteConfirmMessage;

  /// Confirmation after deleting a branch.
  ///
  /// In en, this message translates to:
  /// **'Branch deleted.'**
  String get branchDeleted;

  /// Title of the services list.
  ///
  /// In en, this message translates to:
  /// **'Services'**
  String get servicesTitle;

  /// Empty state title of the services list.
  ///
  /// In en, this message translates to:
  /// **'No services yet'**
  String get servicesEmptyTitle;

  /// Empty state message of the services list.
  ///
  /// In en, this message translates to:
  /// **'Add the services customers can queue for at this branch.'**
  String get servicesEmptyMessage;

  /// Button that opens the new service form.
  ///
  /// In en, this message translates to:
  /// **'Add service'**
  String get serviceAdd;

  /// Title of the new service form.
  ///
  /// In en, this message translates to:
  /// **'New service'**
  String get serviceCreateTitle;

  /// Title of the service editor.
  ///
  /// In en, this message translates to:
  /// **'Edit service'**
  String get serviceEditTitle;

  /// Label of the service name field.
  ///
  /// In en, this message translates to:
  /// **'Service name'**
  String get serviceNameLabel;

  /// Validation error for a service name.
  ///
  /// In en, this message translates to:
  /// **'Enter a name between 2 and 150 characters.'**
  String get serviceNameInvalid;

  /// Error for a duplicate service name.
  ///
  /// In en, this message translates to:
  /// **'This branch already has a service with this name.'**
  String get serviceNameDuplicate;

  /// Label of the service description field.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get serviceDescriptionLabel;

  /// Label of the service duration field.
  ///
  /// In en, this message translates to:
  /// **'Estimated duration (minutes)'**
  String get serviceDurationLabel;

  /// Validation error for a service duration.
  ///
  /// In en, this message translates to:
  /// **'Enter a duration between 1 and 1440 minutes.'**
  String get serviceDurationInvalid;

  /// A service duration.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min'**
  String serviceDurationValue(int minutes);

  /// Switch that activates or deactivates a service.
  ///
  /// In en, this message translates to:
  /// **'Offered to customers'**
  String get serviceStatusLabel;

  /// Confirmation after creating a service.
  ///
  /// In en, this message translates to:
  /// **'Service created.'**
  String get serviceCreated;

  /// Button that archives a service.
  ///
  /// In en, this message translates to:
  /// **'Archive service'**
  String get serviceArchive;

  /// Title of the archive service confirmation.
  ///
  /// In en, this message translates to:
  /// **'Archive this service?'**
  String get serviceArchiveConfirmTitle;

  /// Message of the archive service confirmation.
  ///
  /// In en, this message translates to:
  /// **'It will no longer be offered. Its history is kept.'**
  String get serviceArchiveConfirmMessage;

  /// Confirmation after archiving a service.
  ///
  /// In en, this message translates to:
  /// **'Service archived.'**
  String get serviceArchived;

  /// Header of the team members list.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get teamMembersSection;

  /// Header of the pending invitations list.
  ///
  /// In en, this message translates to:
  /// **'Pending invitations'**
  String get teamInvitationsSection;

  /// Button that opens the invitation form.
  ///
  /// In en, this message translates to:
  /// **'Invite a team member'**
  String get teamInvite;

  /// Badge next to the viewer in the team list.
  ///
  /// In en, this message translates to:
  /// **'You'**
  String get teamYou;

  /// Branch access: all branches.
  ///
  /// In en, this message translates to:
  /// **'All branches'**
  String get teamAllBranches;

  /// Tooltip of the member options menu.
  ///
  /// In en, this message translates to:
  /// **'Member options'**
  String get teamChangeRole;

  /// Menu action that makes a member a manager.
  ///
  /// In en, this message translates to:
  /// **'Make manager'**
  String get teamMakeManager;

  /// Menu action that makes a member an employee.
  ///
  /// In en, this message translates to:
  /// **'Make employee'**
  String get teamMakeEmployee;

  /// Menu action that removes a member.
  ///
  /// In en, this message translates to:
  /// **'Remove from team'**
  String get teamRemove;

  /// Title of the remove member confirmation.
  ///
  /// In en, this message translates to:
  /// **'Remove {name}?'**
  String teamRemoveConfirmTitle(String name);

  /// Message of the remove member confirmation.
  ///
  /// In en, this message translates to:
  /// **'They will lose access to this business immediately.'**
  String get teamRemoveConfirmMessage;

  /// Confirmation after changing a member's role.
  ///
  /// In en, this message translates to:
  /// **'Member updated.'**
  String get teamMemberUpdated;

  /// Confirmation after removing a member.
  ///
  /// In en, this message translates to:
  /// **'Member removed.'**
  String get teamMemberRemoved;

  /// Shown when there are no pending invitations.
  ///
  /// In en, this message translates to:
  /// **'No pending invitations.'**
  String get teamNoInvitations;

  /// Title of the invitation form.
  ///
  /// In en, this message translates to:
  /// **'Invite a team member'**
  String get inviteTitle;

  /// Label of the invitee email field.
  ///
  /// In en, this message translates to:
  /// **'Their email address'**
  String get inviteEmailLabel;

  /// Header of the role choice.
  ///
  /// In en, this message translates to:
  /// **'Role'**
  String get inviteRoleLabel;

  /// Explains the employee role.
  ///
  /// In en, this message translates to:
  /// **'Views the business and its branches.'**
  String get inviteRoleEmployeeHint;

  /// Explains the manager role.
  ///
  /// In en, this message translates to:
  /// **'Manages branches and services, and sees the team.'**
  String get inviteRoleManagerHint;

  /// Header of the branch access choice.
  ///
  /// In en, this message translates to:
  /// **'Branch access'**
  String get inviteAccessLabel;

  /// Switch: access to all branches.
  ///
  /// In en, this message translates to:
  /// **'All branches'**
  String get inviteAccessAll;

  /// Error when no branch is selected.
  ///
  /// In en, this message translates to:
  /// **'Choose at least one branch.'**
  String get inviteSelectBranches;

  /// Button that creates the invitation.
  ///
  /// In en, this message translates to:
  /// **'Create invitation'**
  String get inviteSubmit;

  /// Title after creating an invitation.
  ///
  /// In en, this message translates to:
  /// **'Invitation created'**
  String get inviteCreatedTitle;

  /// The invitation email was accepted by the mail server.
  ///
  /// In en, this message translates to:
  /// **'The invitation was emailed to {email}.'**
  String inviteDeliverySent(String email);

  /// No email was sent because mail is not configured.
  ///
  /// In en, this message translates to:
  /// **'Email delivery is not set up yet, so no email was sent. Share the link below with {email}.'**
  String inviteDeliveryNotConfigured(String email);

  /// The invitation email failed.
  ///
  /// In en, this message translates to:
  /// **'The email could not be sent. Share the link below with {email}.'**
  String inviteDeliveryFailed(String email);

  /// No delivery and no shareable link.
  ///
  /// In en, this message translates to:
  /// **'The invitation was created, but no email was sent and this server does not show links. Ask your administrator to deliver it.'**
  String get inviteNoShareLink;

  /// Header of the shareable invitation link.
  ///
  /// In en, this message translates to:
  /// **'Invitation link'**
  String get inviteShareLinkLabel;

  /// Warning under the invitation link.
  ///
  /// In en, this message translates to:
  /// **'Anyone with this link can see the invitation. Share it only with the invited person.'**
  String get inviteShareWarning;

  /// Button that closes the invitation result.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get inviteDone;

  /// Error when an invitation is already pending.
  ///
  /// In en, this message translates to:
  /// **'An invitation for this email is already pending.'**
  String get inviteErrorPending;

  /// Error when the invitee is already a member.
  ///
  /// In en, this message translates to:
  /// **'This person is already a member of the team.'**
  String get inviteErrorMember;

  /// Title of the invitation screen.
  ///
  /// In en, this message translates to:
  /// **'Invitation'**
  String get invitationTitle;

  /// Headline of the invitation screen.
  ///
  /// In en, this message translates to:
  /// **'You\'re invited to join {business}'**
  String invitationHeadline(String business);

  /// The role offered by an invitation.
  ///
  /// In en, this message translates to:
  /// **'Role: {role}'**
  String invitationRoleLine(String role);

  /// Who sent the invitation.
  ///
  /// In en, this message translates to:
  /// **'Invited by {name}'**
  String invitationInvitedBy(String name);

  /// The (masked) invited email.
  ///
  /// In en, this message translates to:
  /// **'Sent to {email}'**
  String invitationSentTo(String email);

  /// Invitation expiry date.
  ///
  /// In en, this message translates to:
  /// **'Valid until {date}'**
  String invitationExpires(String date);

  /// Expiry date in the pending invitations list.
  ///
  /// In en, this message translates to:
  /// **'Expires {date}'**
  String invitationExpiresOn(String date);

  /// Invitation status: expired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get invitationStatusExpired;

  /// Button that accepts the invitation.
  ///
  /// In en, this message translates to:
  /// **'Accept and join'**
  String get invitationAccept;

  /// Button that declines the invitation.
  ///
  /// In en, this message translates to:
  /// **'Decline'**
  String get invitationDecline;

  /// Title of the decline confirmation.
  ///
  /// In en, this message translates to:
  /// **'Decline this invitation?'**
  String get invitationDeclineConfirmTitle;

  /// Message of the decline confirmation.
  ///
  /// In en, this message translates to:
  /// **'You will need a new invitation to join later.'**
  String get invitationDeclineConfirmMessage;

  /// Confirmation after accepting an invitation.
  ///
  /// In en, this message translates to:
  /// **'Welcome to {business}!'**
  String invitationAccepted(String business);

  /// Confirmation after declining an invitation.
  ///
  /// In en, this message translates to:
  /// **'Invitation declined.'**
  String get invitationDeclined;

  /// Button that revokes a pending invitation.
  ///
  /// In en, this message translates to:
  /// **'Revoke'**
  String get invitationRevoke;

  /// Confirmation after revoking an invitation.
  ///
  /// In en, this message translates to:
  /// **'Invitation revoked.'**
  String get invitationRevoked;

  /// Shown to signed-out viewers of an invitation.
  ///
  /// In en, this message translates to:
  /// **'Log in or create an account with the invited email address to respond.'**
  String get invitationSignInPrompt;

  /// Shown when the signed-in account is not the invited one.
  ///
  /// In en, this message translates to:
  /// **'This invitation was sent to a different email address. Log in with that account to accept it.'**
  String get invitationWrongAccount;

  /// Shown for accepted, declined or revoked invitations.
  ///
  /// In en, this message translates to:
  /// **'This invitation is no longer open.'**
  String get invitationNotOpen;

  /// Shown for expired invitations.
  ///
  /// In en, this message translates to:
  /// **'This invitation has expired. Ask for a new one.'**
  String get invitationExpiredMessage;

  /// Shown for unknown invitation links.
  ///
  /// In en, this message translates to:
  /// **'This invitation link is not valid.'**
  String get invitationNotFound;

  /// Title of the dialog for pasting an invitation link.
  ///
  /// In en, this message translates to:
  /// **'Open an invitation'**
  String get joinInvitationTitle;

  /// Label of the invitation link field.
  ///
  /// In en, this message translates to:
  /// **'Invitation link or code'**
  String get joinInvitationHint;

  /// Validation error for the invitation link field.
  ///
  /// In en, this message translates to:
  /// **'This doesn\'t look like a DORY invitation link.'**
  String get joinInvitationInvalid;

  /// Button that opens the pasted invitation.
  ///
  /// In en, this message translates to:
  /// **'Open'**
  String get joinInvitationOpen;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Waiting'**
  String get queueTicketStatusWaiting;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Called'**
  String get queueTicketStatusCalled;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Being served'**
  String get queueTicketStatusServing;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get queueTicketStatusCompleted;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get queueTicketStatusCancelled;

  /// Ticket status.
  ///
  /// In en, this message translates to:
  /// **'Skipped'**
  String get queueTicketStatusSkipped;

  /// Ticket status: the called customer did not come.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get queueTicketStatusNoShow;

  /// Ticket status: closed at the end of the day.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get queueTicketStatusExpired;

  /// Queue session status.
  ///
  /// In en, this message translates to:
  /// **'Open'**
  String get queueSessionStatusOpen;

  /// Queue session status.
  ///
  /// In en, this message translates to:
  /// **'Paused'**
  String get queueSessionStatusPaused;

  /// Queue session status, or no session.
  ///
  /// In en, this message translates to:
  /// **'Closed'**
  String get queueSessionStatusClosed;

  /// Error code session_already_active.
  ///
  /// In en, this message translates to:
  /// **'This queue is already open.'**
  String get queueErrorSessionAlreadyActive;

  /// Error code queue_has_active_session.
  ///
  /// In en, this message translates to:
  /// **'Close the queue before deleting it.'**
  String get queueErrorHasActiveSession;

  /// Error code no_waiting_tickets.
  ///
  /// In en, this message translates to:
  /// **'Nobody is waiting.'**
  String get queueErrorNoWaiting;

  /// Error code staff_busy.
  ///
  /// In en, this message translates to:
  /// **'Finish your current customer before calling the next one.'**
  String get queueErrorStaffBusy;

  /// Error code ticket_handled_by_other_staff.
  ///
  /// In en, this message translates to:
  /// **'Another staff member is handling this ticket.'**
  String get queueErrorOtherStaff;

  /// Error code grace_period_active.
  ///
  /// In en, this message translates to:
  /// **'The customer still has time to arrive. Try again shortly.'**
  String get queueErrorGracePeriod;

  /// Error code ticket_state_changed.
  ///
  /// In en, this message translates to:
  /// **'Someone else just changed this ticket. The screen was refreshed.'**
  String get queueErrorTicketChanged;

  /// Error code invalid_ticket_transition.
  ///
  /// In en, this message translates to:
  /// **'This action is no longer possible for this ticket.'**
  String get queueErrorInvalidTransition;

  /// Error code queue_closed.
  ///
  /// In en, this message translates to:
  /// **'This queue is closed.'**
  String get queueErrorQueueClosed;

  /// Error code queue_inactive.
  ///
  /// In en, this message translates to:
  /// **'This queue is inactive. Activate it in its settings first.'**
  String get queueErrorQueueInactive;

  /// Error codes no_active_session, session_not_open.
  ///
  /// In en, this message translates to:
  /// **'This queue is not open.'**
  String get queueErrorNoActiveSession;

  /// Error code session_not_paused.
  ///
  /// In en, this message translates to:
  /// **'This queue is not paused.'**
  String get queueErrorSessionNotPaused;

  /// Button and title: create a queue.
  ///
  /// In en, this message translates to:
  /// **'New queue'**
  String get queueCreate;

  /// Queues tab without any business.
  ///
  /// In en, this message translates to:
  /// **'Create a business first, then set up a queue for each service.'**
  String get queuesNoBusiness;

  /// Queue list subtitle: branch and ticket prefix.
  ///
  /// In en, this message translates to:
  /// **'{branch} branch · {prefix}'**
  String queueTileSubtitle(String branch, String prefix);

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get queueBranchLabel;

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Service'**
  String get queueServiceLabel;

  /// Queue form helper.
  ///
  /// In en, this message translates to:
  /// **'Each service has its own queue.'**
  String get queueServiceHelper;

  /// Queue form: no branches.
  ///
  /// In en, this message translates to:
  /// **'Add a branch to this business first.'**
  String get queueNoBranches;

  /// Queue form: no services available.
  ///
  /// In en, this message translates to:
  /// **'This branch has no services without a queue. Add a service first.'**
  String get queueNoServices;

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Queue name'**
  String get queueNameLabel;

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Ticket prefix'**
  String get queuePrefixLabel;

  /// Queue form helper.
  ///
  /// In en, this message translates to:
  /// **'1 to 6 capital letters or digits, starting with a letter. Tickets look like {example}.'**
  String queuePrefixHelper(String example);

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Maximum waiting customers'**
  String get queueCapacityLabel;

  /// Queue form helper.
  ///
  /// In en, this message translates to:
  /// **'Leave empty for no limit.'**
  String get queueCapacityHelper;

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Minutes per customer'**
  String get queueDurationLabel;

  /// Queue form helper.
  ///
  /// In en, this message translates to:
  /// **'Used for wait estimates until there is enough history. Leave empty to use the service duration.'**
  String get queueDurationHelper;

  /// Queue form field.
  ///
  /// In en, this message translates to:
  /// **'Time to arrive after being called (seconds)'**
  String get queueGraceLabel;

  /// Queue form helper.
  ///
  /// In en, this message translates to:
  /// **'After this time a called customer can be marked as no-show.'**
  String get queueGraceHelper;

  /// Queue form switch.
  ///
  /// In en, this message translates to:
  /// **'Guests can join with the QR code, without an account'**
  String get queueGuestJoinLabel;

  /// Queue form switch.
  ///
  /// In en, this message translates to:
  /// **'Ask guests for their name'**
  String get queueGuestNameLabel;

  /// Queue form switch.
  ///
  /// In en, this message translates to:
  /// **'Require a phone number from guests'**
  String get queueGuestPhoneLabel;

  /// Queue form switch.
  ///
  /// In en, this message translates to:
  /// **'Queue is active'**
  String get queueActiveLabel;

  /// Validation.
  ///
  /// In en, this message translates to:
  /// **'Enter a name of 2 to 150 characters.'**
  String get queueValidationName;

  /// Validation.
  ///
  /// In en, this message translates to:
  /// **'Use 1 to 6 capital letters or digits, starting with a letter.'**
  String get queueValidationPrefix;

  /// Validation.
  ///
  /// In en, this message translates to:
  /// **'Enter a whole number from {min} to {max}.'**
  String queueValidationNumber(int min, int max);

  /// Validation.
  ///
  /// In en, this message translates to:
  /// **'Choose a service.'**
  String get queueValidationService;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Queue created.'**
  String get queueCreated;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Queue settings'**
  String get queueEditTitle;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Delete queue'**
  String get queueDelete;

  /// Dialog title.
  ///
  /// In en, this message translates to:
  /// **'Delete this queue?'**
  String get queueDeleteConfirmTitle;

  /// Dialog message.
  ///
  /// In en, this message translates to:
  /// **'Its history is kept, but nobody can join it anymore and its QR code stops working.'**
  String get queueDeleteConfirmMessage;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Queue deleted.'**
  String get queueDeleted;

  /// Queue details: no session.
  ///
  /// In en, this message translates to:
  /// **'The queue is closed. Open it to start accepting customers.'**
  String get queueSessionNone;

  /// Queue details.
  ///
  /// In en, this message translates to:
  /// **'Open since {time} · session {number}'**
  String queueSessionOpenSince(String time, int number);

  /// Queue details notice.
  ///
  /// In en, this message translates to:
  /// **'Paused: new customers cannot join, but you can keep serving those waiting.'**
  String get queueSessionPausedNotice;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Open queue'**
  String get queueOpen;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Pause'**
  String get queuePause;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Resume'**
  String get queueResume;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Close queue'**
  String get queueClose;

  /// Dialog title.
  ///
  /// In en, this message translates to:
  /// **'Close the queue?'**
  String get queueCloseConfirmTitle;

  /// Dialog message.
  ///
  /// In en, this message translates to:
  /// **'Everyone still waiting is cancelled. Customers already called or being served are kept so you can finish with them.'**
  String get queueCloseConfirmMessage;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'The queue is open.'**
  String get queueOpened;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'The queue is paused.'**
  String get queuePausedMessage;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'The queue is open again.'**
  String get queueResumed;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'The queue is closed.'**
  String get queueClosedMessage;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Live dashboard'**
  String get queueOpenDashboard;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Staff'**
  String get queueStaffTitle;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Session history'**
  String get queueHistoryTitle;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'QR code & link'**
  String get queueQrTitle;

  /// Queue details value.
  ///
  /// In en, this message translates to:
  /// **'{count} waiting at most'**
  String queueCapacityValue(int count);

  /// Queue details value.
  ///
  /// In en, this message translates to:
  /// **'No limit'**
  String get queueCapacityNone;

  /// Queue details value.
  ///
  /// In en, this message translates to:
  /// **'Guests can join with the QR code'**
  String get queueGuestsAllowed;

  /// Queue details value.
  ///
  /// In en, this message translates to:
  /// **'Only signed-in customers can join'**
  String get queueGuestsNotAllowed;

  /// Queue details value.
  ///
  /// In en, this message translates to:
  /// **'{seconds} s to arrive'**
  String queueGraceValue(int seconds);

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Open the queue'**
  String get queueOpenTitle;

  /// Open queue explanation.
  ///
  /// In en, this message translates to:
  /// **'Opening starts a session for today. Ticket numbers start at {example} each day (midnight in the branch\'s time zone) and are never reused.'**
  String queueOpenMessage(String example);

  /// Open queue explanation.
  ///
  /// In en, this message translates to:
  /// **'Customers can join from the app and, if allowed, with the QR code.'**
  String get queueOpenGuestsNote;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Live queue'**
  String get queueDashboardTitle;

  /// Polling notice.
  ///
  /// In en, this message translates to:
  /// **'Updates automatically every {seconds} seconds.'**
  String queueAutoRefreshNote(int seconds);

  /// Dashboard metric.
  ///
  /// In en, this message translates to:
  /// **'Waiting'**
  String get queueCountWaiting;

  /// Dashboard metric.
  ///
  /// In en, this message translates to:
  /// **'Being served'**
  String get queueCountInService;

  /// Dashboard metric.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get queueCountDone;

  /// Dashboard metric.
  ///
  /// In en, this message translates to:
  /// **'Missed or skipped'**
  String get queueCountMissed;

  /// Dashboard metric.
  ///
  /// In en, this message translates to:
  /// **'Wait for a new customer'**
  String get queueEstimateLabel;

  /// Approximate minutes.
  ///
  /// In en, this message translates to:
  /// **'~{minutes} min'**
  String queueEstimateValue(int minutes);

  /// Estimate explanation.
  ///
  /// In en, this message translates to:
  /// **'Estimate based on recent service times.'**
  String get queueEstimateBasisHistory;

  /// Estimate explanation.
  ///
  /// In en, this message translates to:
  /// **'Estimate based on the configured duration.'**
  String get queueEstimateBasisConfigured;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Call next customer'**
  String get queueCallNext;

  /// Dashboard section.
  ///
  /// In en, this message translates to:
  /// **'Your customer'**
  String get queueYourCustomer;

  /// Dashboard section.
  ///
  /// In en, this message translates to:
  /// **'Next in line'**
  String get queueNextUp;

  /// Dashboard section.
  ///
  /// In en, this message translates to:
  /// **'Being served now'**
  String get queueInServiceTitle;

  /// Empty state.
  ///
  /// In en, this message translates to:
  /// **'Nobody is waiting.'**
  String get queueNobodyWaiting;

  /// Empty state.
  ///
  /// In en, this message translates to:
  /// **'Nobody is being served right now.'**
  String get queueNobodyServed;

  /// Link.
  ///
  /// In en, this message translates to:
  /// **'All waiting customers'**
  String get queueViewAllWaiting;

  /// Link.
  ///
  /// In en, this message translates to:
  /// **'All customers being served'**
  String get queueViewAllServing;

  /// Dashboard notice.
  ///
  /// In en, this message translates to:
  /// **'This session is closed.'**
  String get queueSessionEnded;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Called {number}.'**
  String queueCalledMessage(String number);

  /// Ticket tile.
  ///
  /// In en, this message translates to:
  /// **'Waited {minutes} min'**
  String queueWaitedValue(int minutes);

  /// Ticket tile.
  ///
  /// In en, this message translates to:
  /// **'#{position} in line'**
  String queuePositionValue(int position);

  /// Customer without an account.
  ///
  /// In en, this message translates to:
  /// **'Guest'**
  String get queueGuest;

  /// Customer without a name.
  ///
  /// In en, this message translates to:
  /// **'Customer'**
  String get queueCustomer;

  /// Ticket tile.
  ///
  /// In en, this message translates to:
  /// **'Served by {name}'**
  String queueServedBy(String name);

  /// Ticket tile.
  ///
  /// In en, this message translates to:
  /// **'Served by you'**
  String get queueServedByYou;

  /// Ticket action.
  ///
  /// In en, this message translates to:
  /// **'Call now'**
  String get queueActionCall;

  /// Ticket action.
  ///
  /// In en, this message translates to:
  /// **'Start service'**
  String get queueActionStart;

  /// Ticket action.
  ///
  /// In en, this message translates to:
  /// **'Complete'**
  String get queueActionComplete;

  /// Ticket action.
  ///
  /// In en, this message translates to:
  /// **'Skip'**
  String get queueActionSkip;

  /// Ticket action.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get queueActionNoShow;

  /// Ticket action: return a called ticket to its place.
  ///
  /// In en, this message translates to:
  /// **'Back to the line'**
  String get queueActionRecall;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Done.'**
  String get queueActionDone;

  /// Dialog title.
  ///
  /// In en, this message translates to:
  /// **'Skip this customer?'**
  String get queueSkipConfirmTitle;

  /// Dialog message.
  ///
  /// In en, this message translates to:
  /// **'Their ticket is taken off the line.'**
  String get queueSkipConfirmMessage;

  /// Ticket hint.
  ///
  /// In en, this message translates to:
  /// **'No-show possible from {time}'**
  String queueNoShowAvailableAt(String time);

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Waiting customers'**
  String get queueWaitingTitle;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Being served'**
  String get queueServingTitle;

  /// Title.
  ///
  /// In en, this message translates to:
  /// **'Ticket {number}'**
  String queueTicketTitle(String number);

  /// Ticket detail row.
  ///
  /// In en, this message translates to:
  /// **'Customer'**
  String get queueTicketCustomer;

  /// Ticket detail row.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get queueTicketPhone;

  /// Ticket detail row.
  ///
  /// In en, this message translates to:
  /// **'Position'**
  String get queueTicketPosition;

  /// Ticket detail row.
  ///
  /// In en, this message translates to:
  /// **'Joined'**
  String get queueTicketJoined;

  /// Ticket detail row.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get queueTicketStatus;

  /// Ticket detail section.
  ///
  /// In en, this message translates to:
  /// **'History'**
  String get queueTicketHistory;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Joined'**
  String get queueEventJoined;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Called'**
  String get queueEventCalled;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Service started'**
  String get queueEventServiceStarted;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Service completed'**
  String get queueEventServiceCompleted;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get queueEventCancelled;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Skipped'**
  String get queueEventSkipped;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Marked as no-show'**
  String get queueEventNoShow;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Expired at the end of the day'**
  String get queueEventExpired;

  /// Ticket event.
  ///
  /// In en, this message translates to:
  /// **'Returned to the line'**
  String get queueEventRecalled;

  /// Staff screen explanation.
  ///
  /// In en, this message translates to:
  /// **'Owners and managers of the branch can always operate this queue. Assign the employees who serve customers here.'**
  String get queueStaffMessage;

  /// Empty state.
  ///
  /// In en, this message translates to:
  /// **'No employees assigned yet.'**
  String get queueStaffEmpty;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Assign'**
  String get queueStaffAssign;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Assigned to the queue.'**
  String get queueStaffAssigned;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'Removed from the queue.'**
  String get queueStaffRemoved;

  /// Section title.
  ///
  /// In en, this message translates to:
  /// **'Team members'**
  String get queueStaffAvailable;

  /// Empty state.
  ///
  /// In en, this message translates to:
  /// **'Everyone who can be assigned already is.'**
  String get queueStaffNoneAvailable;

  /// Section title.
  ///
  /// In en, this message translates to:
  /// **'Assigned'**
  String get queueStaffAssignedTitle;

  /// Empty state.
  ///
  /// In en, this message translates to:
  /// **'No sessions yet.'**
  String get queueHistoryEmpty;

  /// History row.
  ///
  /// In en, this message translates to:
  /// **'{date} · session {number}'**
  String queueHistoryItem(String date, int number);

  /// History row.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{No tickets} =1{1 ticket} other{{count} tickets}}'**
  String queueHistoryTickets(int count);

  /// Close reason.
  ///
  /// In en, this message translates to:
  /// **'Closed by staff'**
  String get queueCloseReasonStaff;

  /// Close reason.
  ///
  /// In en, this message translates to:
  /// **'Closed at the end of the day'**
  String get queueCloseReasonDayEnded;

  /// QR screen explanation.
  ///
  /// In en, this message translates to:
  /// **'Customers scan this code with their phone camera to join. No app needed.'**
  String get queueQrMessage;

  /// QR screen warning.
  ///
  /// In en, this message translates to:
  /// **'Guest joining is turned off for this queue, so the QR code only shows its status. Turn it on in the queue settings.'**
  String get queueQrGuestsOff;

  /// QR screen label.
  ///
  /// In en, this message translates to:
  /// **'Public link'**
  String get queueLinkLabel;

  /// Button.
  ///
  /// In en, this message translates to:
  /// **'Replace link and QR code'**
  String get queueRotate;

  /// Dialog title.
  ///
  /// In en, this message translates to:
  /// **'Replace the link?'**
  String get queueRotateConfirmTitle;

  /// Dialog message.
  ///
  /// In en, this message translates to:
  /// **'The current QR code and link stop working immediately. Print or share the new one.'**
  String get queueRotateConfirmMessage;

  /// Snackbar.
  ///
  /// In en, this message translates to:
  /// **'New link created. The old one no longer works.'**
  String get queueRotated;

  /// Accessibility label.
  ///
  /// In en, this message translates to:
  /// **'QR code to join {name}'**
  String queueQrSemantics(String name);
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
