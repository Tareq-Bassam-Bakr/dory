# Local settings for start_dory.bat. Copy this file to dory.local.psd1 (it
# is ignored by Git) and set what differs on your computer. Leave a value
# empty to let the script look on PATH and in the usual places.
@{
    # PHP 8.3 or newer. Needed when the PHP on PATH is older, for example
    # XAMPP's PHP 8.2.
    PhpPath = ''

    # adb.exe, if it is not in %LOCALAPPDATA%\Android\Sdk\platform-tools or
    # ANDROID_HOME.
    AdbPath = ''
}
