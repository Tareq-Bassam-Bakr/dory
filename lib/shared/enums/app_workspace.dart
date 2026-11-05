/// A top-level area of the app with its own navigation shell.
///
/// A signed-in user may have access to several workspaces at once (for
/// example a business owner who also books as a customer) and switches
/// between them; the workspace is never fixed per user.
enum AppWorkspace {
  /// Discover businesses, join queues, follow tickets.
  customer,

  /// Run businesses, branches, queues, and staff.
  business,

  /// Platform administration. No mobile shell exists yet.
  administration,
}
