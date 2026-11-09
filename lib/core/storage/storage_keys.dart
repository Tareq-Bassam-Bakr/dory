/// Every key persisted through `KeyValueStorage`.
///
/// Keys are namespaced with `dory.` and must be added to [all], which is the
/// allow-list handed to shared_preferences.
abstract final class StorageKeys {
  static const String languageCode = 'dory.language_code';

  /// Non-secret details of this device's guest tickets, for the ticket list.
  /// Their access tokens live only in `SecureStore`.
  static const String guestTickets = 'dory.guest_tickets';

  /// The city chosen by hand. Device positions are never persisted.
  static const String chosenCity = 'dory.chosen_city';
  static const String searchRadiusKm = 'dory.search_radius_km';
  static const String recentSearches = 'dory.recent_searches';

  /// Businesses saved on this device while signed out.
  static const String localFavorites = 'dory.local_favorites';

  /// "Not now" on the offer to turn notifications on.
  static const String notificationOfferDismissed =
      'dory.notification_offer_dismissed';

  /// The server's push device ids for this phone, per account and per guest
  /// ticket (JSON). Not secret: the FCM token itself is never stored here.
  static const String pushDevices = 'dory.push_devices';

  static const Set<String> all = <String>{
    languageCode,
    guestTickets,
    chosenCity,
    searchRadiusKm,
    recentSearches,
    localFavorites,
    notificationOfferDismissed,
    pushDevices,
  };
}
