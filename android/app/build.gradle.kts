plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Firebase (docs/FIREBASE_SETUP.md): only when android/app/google-services.json
// exists. That file is client configuration, never committed. Without it the
// app builds and runs with push notifications off.
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}

// Store identity of the app. Configure it in android/gradle.properties
// (dory.applicationId) or per build with -Pdory.applicationId=<id>.
// It can never change after the first store release.
val doryApplicationId: String =
    providers.gradleProperty("dory.applicationId").orNull ?: "com.dory.app"

android {
    // Code namespace (Kotlin package of MainActivity). Independent of the
    // store applicationId and invisible to users.
    namespace = "com.dory.dory_app"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Required by flutter_local_notifications.
        isCoreLibraryDesugaringEnabled = true
    }

    defaultConfig {
        applicationId = doryApplicationId
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
