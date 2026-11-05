import 'package:dory_app/core/network/network_providers.dart';
import 'package:dory_app/core/storage/secure_store.dart';
import 'package:dory_app/features/authentication/data/datasources/auth_local_data_source.dart';
import 'package:dory_app/features/authentication/data/datasources/auth_remote_data_source.dart';
import 'package:dory_app/features/authentication/data/repositories/auth_repository_impl.dart';
import 'package:dory_app/features/authentication/domain/repositories/auth_repository.dart';
import 'package:dory_app/features/authentication/domain/usecases/auth_use_cases.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Device name sent with login/registration, shown in the token list.
final Provider<String> authDeviceNameProvider = Provider<String>((ref) {
  if (kIsWeb) {
    return 'DORY Web';
  }
  return switch (defaultTargetPlatform) {
    TargetPlatform.android => 'DORY Android',
    TargetPlatform.iOS => 'DORY iOS',
    _ => 'DORY ${defaultTargetPlatform.name}',
  };
}, name: 'authDeviceNameProvider');

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>((ref) {
      return AuthRepositoryImpl(
        remote: AuthRemoteDataSource(
          ref.watch(apiClientProvider),
          deviceName: ref.watch(authDeviceNameProvider),
        ),
        local: AuthLocalDataSource(
          ref.watch(secureStoreProvider),
          ref.watch(sessionTokenHolderProvider),
        ),
      );
    }, name: 'authRepositoryProvider');

final Provider<LoginUseCase> loginUseCaseProvider = Provider<LoginUseCase>(
  (ref) => LoginUseCase(ref.watch(authRepositoryProvider)),
  name: 'loginUseCaseProvider',
);

final Provider<RegisterUseCase> registerUseCaseProvider =
    Provider<RegisterUseCase>(
      (ref) => RegisterUseCase(ref.watch(authRepositoryProvider)),
      name: 'registerUseCaseProvider',
    );

final Provider<RestoreSessionUseCase> restoreSessionUseCaseProvider =
    Provider<RestoreSessionUseCase>(
      (ref) => RestoreSessionUseCase(ref.watch(authRepositoryProvider)),
      name: 'restoreSessionUseCaseProvider',
    );

final Provider<GetCurrentUserUseCase> getCurrentUserUseCaseProvider =
    Provider<GetCurrentUserUseCase>(
      (ref) => GetCurrentUserUseCase(ref.watch(authRepositoryProvider)),
      name: 'getCurrentUserUseCaseProvider',
    );

final Provider<LogoutUseCase> logoutUseCaseProvider = Provider<LogoutUseCase>(
  (ref) => LogoutUseCase(ref.watch(authRepositoryProvider)),
  name: 'logoutUseCaseProvider',
);
