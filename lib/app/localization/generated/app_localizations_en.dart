// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTagline => 'Your Turn. Your Time.';

  @override
  String get commonRetry => 'Try again';

  @override
  String get commonComingSoonMessage =>
      'This feature will be available in an upcoming update.';

  @override
  String get commonGenericErrorTitle => 'Something went wrong';

  @override
  String get welcomeHeadline => 'Smart queues for every service';

  @override
  String get welcomeBody =>
      'Join queues remotely, follow your turn live, and arrive right on time.';

  @override
  String get welcomeLogin => 'Log in';

  @override
  String get welcomeCreateAccount => 'Create account';

  @override
  String get welcomeExploreAsGuest => 'Explore as a guest';

  @override
  String get authEmailLabel => 'Email';

  @override
  String get authPasswordLabel => 'Password';

  @override
  String get authFullNameLabel => 'Full name';

  @override
  String get authPhoneLabel => 'Phone number';

  @override
  String get authShowPassword => 'Show password';

  @override
  String get authHidePassword => 'Hide password';

  @override
  String get loginTitle => 'Welcome back';

  @override
  String get loginSubtitle =>
      'Log in to manage your tickets and your businesses.';

  @override
  String get loginSubmit => 'Log in';

  @override
  String get loginNoAccountPrompt => 'Don\'t have an account?';

  @override
  String get registerTitle => 'Create your account';

  @override
  String get registerSubtitle =>
      'Join DORY and spend less time waiting in line.';

  @override
  String get registerSubmit => 'Create account';

  @override
  String get registerHaveAccountPrompt => 'Already have an account?';

  @override
  String get navHome => 'Home';

  @override
  String get navTickets => 'Tickets';

  @override
  String get navAccount => 'Account';

  @override
  String get navDashboard => 'Dashboard';

  @override
  String get navQueues => 'Queues';

  @override
  String get homeTitle => 'Where are you heading today?';

  @override
  String get homeSearchHint => 'Search businesses and services';

  @override
  String get homeCategoriesTitle => 'Categories';

  @override
  String get homeNearbyTitle => 'Nearby businesses';

  @override
  String get homeNearbyEmptyTitle => 'No nearby businesses yet';

  @override
  String get homeNearbyEmptyMessage =>
      'Businesses near you will appear here once location-based discovery is available.';

  @override
  String get categoryRestaurants => 'Restaurants';

  @override
  String get categoryClinics => 'Clinics';

  @override
  String get categorySalons => 'Salons';

  @override
  String get categoryBanks => 'Banks';

  @override
  String get categoryGovernment => 'Government services';

  @override
  String get categoryRepair => 'Repair shops';

  @override
  String get categoryOther => 'Other services';

  @override
  String get ticketsTitle => 'My tickets';

  @override
  String get ticketsEmptyTitle => 'No active tickets';

  @override
  String get ticketsEmptyMessage =>
      'When you join a queue, your digital ticket and live position will appear here.';

  @override
  String get accountTitle => 'Account';

  @override
  String get accountGuestTitle => 'You are browsing as a guest';

  @override
  String get accountGuestMessage =>
      'Log in to join queues and get notified when your turn is near.';

  @override
  String get accountPreferencesSection => 'Preferences';

  @override
  String get accountLanguage => 'Language';

  @override
  String get accountWorkspaceSection => 'Workspace';

  @override
  String get accountSwitchToBusiness => 'Switch to business workspace';

  @override
  String get accountSwitchToBusinessDescription =>
      'Manage branches, queues, and staff.';

  @override
  String get accountSwitchToCustomer => 'Switch to customer workspace';

  @override
  String get accountSwitchToCustomerDescription =>
      'Discover businesses and join queues.';

  @override
  String get languageSystem => 'Device language';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageArabic => 'العربية';

  @override
  String get dashboardTitle => 'Dashboard';

  @override
  String get dashboardOverviewTitle => 'Business overview';

  @override
  String get dashboardSetupTitle => 'Set up your business';

  @override
  String get dashboardSetupMessage =>
      'Register your business, add branches and services, then start managing your queues.';

  @override
  String get dashboardSetupAction => 'Register a business';

  @override
  String get dashboardQueueSummaryTitle => 'Queue summary';

  @override
  String get dashboardQueueSummaryEmpty =>
      'Live figures appear once your queues are active.';

  @override
  String get dashboardMetricWaiting => 'Waiting';

  @override
  String get dashboardMetricServing => 'Being served';

  @override
  String get dashboardMetricAverageWait => 'Average wait';

  @override
  String get dashboardMetricServedToday => 'Served today';

  @override
  String get dashboardMetricNoValue => 'No data';

  @override
  String get dashboardEmployeesTitle => 'Employees';

  @override
  String get dashboardEmployeesEmptyTitle => 'No employees yet';

  @override
  String get dashboardEmployeesEmptyMessage =>
      'Invite staff to call customers and run your service counters.';

  @override
  String get queuesTitle => 'Queues';

  @override
  String get queuesEmptyTitle => 'No queues yet';

  @override
  String get queuesEmptyMessage =>
      'Queues you create for your services will appear here.';

  @override
  String get notFoundTitle => 'Page not found';

  @override
  String get notFoundMessage =>
      'The page you are looking for does not exist or has moved.';

  @override
  String get notFoundAction => 'Back to start';

  @override
  String get authEmailRequired => 'Enter your email address.';

  @override
  String get authEmailInvalid => 'Enter a valid email address.';

  @override
  String get authPasswordRequired => 'Enter your password.';

  @override
  String get authNameInvalid => 'Enter your name (2 to 120 characters).';

  @override
  String get authPhoneHint =>
      'Optional. International format, e.g. +963912345678';

  @override
  String get authPhoneInvalid =>
      'Use the international format, e.g. +963912345678.';

  @override
  String get authPasswordRules =>
      'At least 8 characters, with upper and lower case letters and a number.';

  @override
  String get authPasswordTooWeak =>
      'The password does not meet the requirements below.';

  @override
  String get authConfirmPasswordLabel => 'Confirm password';

  @override
  String get authPasswordMismatch => 'The passwords do not match.';

  @override
  String get authErrorInvalidCredentials =>
      'The email or password is incorrect.';

  @override
  String get authErrorAccountInactive =>
      'This account is not active. Please contact support.';

  @override
  String get authErrorRateLimited =>
      'Too many attempts. Please wait a minute and try again.';

  @override
  String get authErrorValidation => 'Please correct the highlighted fields.';

  @override
  String get authSessionExpired =>
      'Your session has ended. Please log in again.';

  @override
  String get errorNetwork =>
      'Cannot reach DORY. Check your connection and try again.';

  @override
  String get errorServer =>
      'DORY is having trouble right now. Please try again shortly.';

  @override
  String get errorUnknown => 'Something went wrong. Please try again.';

  @override
  String get accountSignedInSection => 'Signed in as';

  @override
  String get accountLogout => 'Log out';

  @override
  String get accountLogoutConfirmTitle => 'Log out of DORY?';

  @override
  String get accountLogoutConfirmMessage =>
      'You will need to log in again to manage your businesses.';

  @override
  String get accountOfflineNotice =>
      'You are offline. Showing your last known details.';

  @override
  String get commonCancel => 'Cancel';
}
