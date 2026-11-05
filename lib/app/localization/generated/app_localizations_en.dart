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

  @override
  String get commonActive => 'Active';

  @override
  String get commonInactive => 'Inactive';

  @override
  String get commonSuspended => 'Suspended';

  @override
  String get commonBack => 'Back';

  @override
  String get commonNext => 'Next';

  @override
  String get commonSave => 'Save';

  @override
  String get commonCreate => 'Create';

  @override
  String get commonEdit => 'Edit';

  @override
  String get commonDelete => 'Delete';

  @override
  String get commonRemove => 'Remove';

  @override
  String get commonCopy => 'Copy';

  @override
  String get commonCopied => 'Copied to the clipboard.';

  @override
  String get commonSaved => 'Changes saved.';

  @override
  String get errorForbidden => 'You don\'t have permission to do this.';

  @override
  String get errorNotFound =>
      'This was not found. It may have been removed, or you no longer have access.';

  @override
  String get errorValidation => 'Please correct the highlighted fields.';

  @override
  String get errorBusinessSuspended =>
      'This business is suspended, so changes are disabled.';

  @override
  String get timezoneLabel => 'Time zone';

  @override
  String get timezoneHint =>
      'IANA name, e.g. Asia/Damascus. Leave empty to keep the current one.';

  @override
  String get timezoneInvalid => 'Use an IANA time zone, e.g. Asia/Damascus.';

  @override
  String get myBusinessesTitle => 'My businesses';

  @override
  String get myBusinessesCreate => 'Create another business';

  @override
  String get myBusinessesJoin => 'I have an invitation';

  @override
  String get businessRoleOwner => 'Owner';

  @override
  String get businessRoleManager => 'Manager';

  @override
  String get businessRoleEmployee => 'Employee';

  @override
  String businessYourRole(String role) {
    return 'Your role: $role';
  }

  @override
  String businessProfileCompletion(int percent) {
    return 'Profile $percent% complete';
  }

  @override
  String businessStatsServices(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count services',
      one: '1 service',
      zero: 'No services',
    );
    return '$_temp0';
  }

  @override
  String get businessBranches => 'Branches';

  @override
  String get businessTeam => 'Team';

  @override
  String get businessManageSection => 'Manage';

  @override
  String get businessEditProfile => 'Edit profile';

  @override
  String get businessStatusTitle => 'Accepting customers';

  @override
  String get businessStatusActiveHint => 'Customers can find this business.';

  @override
  String get businessStatusInactiveHint =>
      'Hidden from customers until you reactivate it.';

  @override
  String get businessSuspendedNotice =>
      'DORY has suspended this business. You can view it, but changes are disabled.';

  @override
  String get businessCompletionTitle => 'Complete your profile';

  @override
  String get businessCompletionDone => 'Your profile is complete';

  @override
  String get businessMissingBasics => 'Choose a category';

  @override
  String get businessMissingDescription => 'Add a description';

  @override
  String get businessMissingContact => 'Add an email or phone number';

  @override
  String get businessMissingBranch => 'Add an active branch';

  @override
  String get businessMissingService => 'Add an active service';

  @override
  String get businessDangerZone => 'Danger zone';

  @override
  String get businessDelete => 'Delete business';

  @override
  String businessDeleteConfirmTitle(String name) {
    return 'Delete $name?';
  }

  @override
  String get businessDeleteConfirmMessage =>
      'The business, its branches and services will be archived, and your team will lose access. This cannot be undone in the app.';

  @override
  String get businessDeleted => 'Business deleted.';

  @override
  String get businessNameLabel => 'Business name';

  @override
  String get businessNameInvalid =>
      'Enter a name between 2 and 150 characters.';

  @override
  String get businessCategoryLabel => 'Category';

  @override
  String get businessCategoryRequired => 'Choose a category.';

  @override
  String get businessCategoriesLoadError => 'Couldn\'t load categories.';

  @override
  String get businessDescriptionLabel => 'Description';

  @override
  String get businessDescriptionHint =>
      'What do you offer? Customers will see this.';

  @override
  String get businessContactHint =>
      'Add an email or phone number so customers can reach you. Both are optional.';

  @override
  String get businessEmailLabel => 'Business email';

  @override
  String get businessPhoneLabel => 'Business phone';

  @override
  String get businessWebsiteLabel => 'Website';

  @override
  String get businessWebsiteInvalid =>
      'Enter a full web address, e.g. https://example.com.';

  @override
  String get editBusinessTitle => 'Edit business profile';

  @override
  String get createBusinessTitle => 'Create your business';

  @override
  String get createBusinessIntro =>
      'Set up your business in five short steps. You can change everything later.';

  @override
  String get createBusinessStepsTitle => 'What you will set up';

  @override
  String get createBusinessStart => 'Start';

  @override
  String get onboardingTitle => 'New business';

  @override
  String onboardingStepProgress(int current, int total) {
    return 'Step $current of $total';
  }

  @override
  String get onboardingStepBasics => 'Name and category';

  @override
  String get onboardingStepDetails => 'Description and contact';

  @override
  String get onboardingStepBranch => 'Main branch';

  @override
  String get onboardingStepServices => 'Services';

  @override
  String get onboardingStepReview => 'Review';

  @override
  String get onboardingServicesIntro =>
      'Add the services customers can queue for. You can add more later.';

  @override
  String get onboardingAddService => 'Add service';

  @override
  String get onboardingNoServices => 'No services yet. You can add them later.';

  @override
  String get onboardingReviewIntro =>
      'Check the details, then create your business.';

  @override
  String get onboardingNotProvided => 'Not provided';

  @override
  String get onboardingCreate => 'Create business';

  @override
  String get onboardingCreated => 'Your business is ready.';

  @override
  String get onboardingLeaveTitle => 'Discard this business?';

  @override
  String get onboardingLeaveMessage => 'What you entered will be lost.';

  @override
  String get onboardingLeaveConfirm => 'Discard';

  @override
  String get branchAdd => 'Add branch';

  @override
  String get branchesEmptyTitle => 'No branches yet';

  @override
  String get branchesEmptyMessage =>
      'Add your first branch so customers know where to find you.';

  @override
  String get branchesEmptyLimitedMessage => 'No branches are assigned to you.';

  @override
  String get branchCreateTitle => 'New branch';

  @override
  String get branchEditTitle => 'Edit branch';

  @override
  String get branchCreated => 'Branch created.';

  @override
  String get branchNameLabel => 'Branch name';

  @override
  String get branchNameDefault => 'Main branch';

  @override
  String get branchAddressLabel => 'Address';

  @override
  String get branchCityLabel => 'City';

  @override
  String get branchRegionLabel => 'Region or state';

  @override
  String get branchCountryLabel => 'Country code';

  @override
  String get branchCountryInvalid => 'Use a two-letter country code, e.g. SY.';

  @override
  String get branchLocationSection => 'Map location';

  @override
  String get branchLocationHint =>
      'Optional. Decimal coordinates (WGS 84), as shown on openstreetmap.org.';

  @override
  String get branchLatitudeLabel => 'Latitude';

  @override
  String get branchLongitudeLabel => 'Longitude';

  @override
  String get branchLatitudeInvalid => 'Latitude must be between -90 and 90.';

  @override
  String get branchLongitudeInvalid =>
      'Longitude must be between -180 and 180.';

  @override
  String get branchCoordinatesPair =>
      'Enter both latitude and longitude, or neither.';

  @override
  String get branchNoLocation => 'No map location set';

  @override
  String get branchPhoneLabel => 'Branch phone';

  @override
  String get branchEmailLabel => 'Branch email';

  @override
  String get branchStatusLabel => 'Branch is open';

  @override
  String get branchDetailsSection => 'Details';

  @override
  String get branchDelete => 'Delete branch';

  @override
  String get branchDeleteConfirmTitle => 'Delete this branch?';

  @override
  String get branchDeleteConfirmMessage =>
      'The branch and its services will be archived.';

  @override
  String get branchDeleted => 'Branch deleted.';

  @override
  String get servicesTitle => 'Services';

  @override
  String get servicesEmptyTitle => 'No services yet';

  @override
  String get servicesEmptyMessage =>
      'Add the services customers can queue for at this branch.';

  @override
  String get serviceAdd => 'Add service';

  @override
  String get serviceCreateTitle => 'New service';

  @override
  String get serviceEditTitle => 'Edit service';

  @override
  String get serviceNameLabel => 'Service name';

  @override
  String get serviceNameInvalid => 'Enter a name between 2 and 150 characters.';

  @override
  String get serviceNameDuplicate =>
      'This branch already has a service with this name.';

  @override
  String get serviceDescriptionLabel => 'Description';

  @override
  String get serviceDurationLabel => 'Estimated duration (minutes)';

  @override
  String get serviceDurationInvalid =>
      'Enter a duration between 1 and 1440 minutes.';

  @override
  String serviceDurationValue(int minutes) {
    return '$minutes min';
  }

  @override
  String get serviceStatusLabel => 'Offered to customers';

  @override
  String get serviceCreated => 'Service created.';

  @override
  String get serviceArchive => 'Archive service';

  @override
  String get serviceArchiveConfirmTitle => 'Archive this service?';

  @override
  String get serviceArchiveConfirmMessage =>
      'It will no longer be offered. Its history is kept.';

  @override
  String get serviceArchived => 'Service archived.';

  @override
  String get teamMembersSection => 'Members';

  @override
  String get teamInvitationsSection => 'Pending invitations';

  @override
  String get teamInvite => 'Invite a team member';

  @override
  String get teamYou => 'You';

  @override
  String get teamAllBranches => 'All branches';

  @override
  String get teamChangeRole => 'Member options';

  @override
  String get teamMakeManager => 'Make manager';

  @override
  String get teamMakeEmployee => 'Make employee';

  @override
  String get teamRemove => 'Remove from team';

  @override
  String teamRemoveConfirmTitle(String name) {
    return 'Remove $name?';
  }

  @override
  String get teamRemoveConfirmMessage =>
      'They will lose access to this business immediately.';

  @override
  String get teamMemberUpdated => 'Member updated.';

  @override
  String get teamMemberRemoved => 'Member removed.';

  @override
  String get teamNoInvitations => 'No pending invitations.';

  @override
  String get inviteTitle => 'Invite a team member';

  @override
  String get inviteEmailLabel => 'Their email address';

  @override
  String get inviteRoleLabel => 'Role';

  @override
  String get inviteRoleEmployeeHint => 'Views the business and its branches.';

  @override
  String get inviteRoleManagerHint =>
      'Manages branches and services, and sees the team.';

  @override
  String get inviteAccessLabel => 'Branch access';

  @override
  String get inviteAccessAll => 'All branches';

  @override
  String get inviteSelectBranches => 'Choose at least one branch.';

  @override
  String get inviteSubmit => 'Create invitation';

  @override
  String get inviteCreatedTitle => 'Invitation created';

  @override
  String inviteDeliverySent(String email) {
    return 'The invitation was emailed to $email.';
  }

  @override
  String inviteDeliveryNotConfigured(String email) {
    return 'Email delivery is not set up yet, so no email was sent. Share the link below with $email.';
  }

  @override
  String inviteDeliveryFailed(String email) {
    return 'The email could not be sent. Share the link below with $email.';
  }

  @override
  String get inviteNoShareLink =>
      'The invitation was created, but no email was sent and this server does not show links. Ask your administrator to deliver it.';

  @override
  String get inviteShareLinkLabel => 'Invitation link';

  @override
  String get inviteShareWarning =>
      'Anyone with this link can see the invitation. Share it only with the invited person.';

  @override
  String get inviteDone => 'Done';

  @override
  String get inviteErrorPending =>
      'An invitation for this email is already pending.';

  @override
  String get inviteErrorMember =>
      'This person is already a member of the team.';

  @override
  String get invitationTitle => 'Invitation';

  @override
  String invitationHeadline(String business) {
    return 'You\'re invited to join $business';
  }

  @override
  String invitationRoleLine(String role) {
    return 'Role: $role';
  }

  @override
  String invitationInvitedBy(String name) {
    return 'Invited by $name';
  }

  @override
  String invitationSentTo(String email) {
    return 'Sent to $email';
  }

  @override
  String invitationExpires(String date) {
    return 'Valid until $date';
  }

  @override
  String invitationExpiresOn(String date) {
    return 'Expires $date';
  }

  @override
  String get invitationStatusExpired => 'Expired';

  @override
  String get invitationAccept => 'Accept and join';

  @override
  String get invitationDecline => 'Decline';

  @override
  String get invitationDeclineConfirmTitle => 'Decline this invitation?';

  @override
  String get invitationDeclineConfirmMessage =>
      'You will need a new invitation to join later.';

  @override
  String invitationAccepted(String business) {
    return 'Welcome to $business!';
  }

  @override
  String get invitationDeclined => 'Invitation declined.';

  @override
  String get invitationRevoke => 'Revoke';

  @override
  String get invitationRevoked => 'Invitation revoked.';

  @override
  String get invitationSignInPrompt =>
      'Log in or create an account with the invited email address to respond.';

  @override
  String get invitationWrongAccount =>
      'This invitation was sent to a different email address. Log in with that account to accept it.';

  @override
  String get invitationNotOpen => 'This invitation is no longer open.';

  @override
  String get invitationExpiredMessage =>
      'This invitation has expired. Ask for a new one.';

  @override
  String get invitationNotFound => 'This invitation link is not valid.';

  @override
  String get joinInvitationTitle => 'Open an invitation';

  @override
  String get joinInvitationHint => 'Invitation link or code';

  @override
  String get joinInvitationInvalid =>
      'This doesn\'t look like a DORY invitation link.';

  @override
  String get joinInvitationOpen => 'Open';
}
