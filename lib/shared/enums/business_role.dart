/// A user's role inside one business, as granted by the API.
///
/// The server decides what each role may do; the app uses roles only to
/// shape the interface (see docs/MULTI_TENANCY.md).
enum BusinessRole {
  owner('owner'),
  manager('manager'),
  employee('employee');

  const BusinessRole(this.apiValue);

  /// The value the API sends and accepts.
  final String apiValue;

  /// Parses an API value. Unknown values become [employee], the least
  /// privileged role, so a newer server can never widen the UI.
  static BusinessRole fromApi(String? value) {
    for (final BusinessRole role in values) {
      if (role.apiValue == value) {
        return role;
      }
    }
    return employee;
  }
}
