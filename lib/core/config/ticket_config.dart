/// How often an open ticket screen asks the server for its position.
///
/// There is no real-time push in this version: an open ticket refreshes on
/// this timer, and only while it is on screen. Failed requests back off up
/// to [maxPollInterval].
abstract final class TicketConfig {
  static const int _definedSeconds = int.fromEnvironment(
    'DORY_TICKET_POLL_SECONDS',
    defaultValue: 15,
  );

  static const Duration pollInterval = Duration(seconds: _definedSeconds);
  static const Duration maxPollInterval = Duration(minutes: 2);
}
