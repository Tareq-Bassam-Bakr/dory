<?php

namespace App\Enums;

/**
 * Languages supported by DORY. Mirrors the Flutter app's supported locales.
 */
enum AppLocale: string
{
    case English = 'en';
    case Arabic = 'ar';
}
