<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;

/**
 * The launch set of business categories. Reference data, not demo data:
 * safe to run in every environment, including production, and idempotent
 * (rows are matched by slug and updated in place).
 *
 * Slugs are permanent public identifiers. To retire a category, set
 * is_active to false instead of deleting or renaming it.
 */
class BusinessCategorySeeder extends Seeder
{
    /**
     * slug => [icon, English name, Arabic name]
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const CATEGORIES = [
        'restaurants-cafes' => ['restaurant', 'Restaurants & Cafes', 'المطاعم والمقاهي'],
        'healthcare-clinics' => ['healthcare', 'Healthcare & Clinics', 'الرعاية الصحية والعيادات'],
        'beauty-salons' => ['beauty', 'Beauty & Salons', 'التجميل والصالونات'],
        'banking-financial-services' => ['banking', 'Banking & Financial Services', 'البنوك والخدمات المالية'],
        'government-services' => ['government', 'Government Services', 'الخدمات الحكومية'],
        'telecommunications' => ['telecom', 'Telecommunications', 'الاتصالات'],
        'automotive-repair' => ['automotive', 'Automotive & Repair', 'السيارات والصيانة'],
        'education-training' => ['education', 'Education & Training', 'التعليم والتدريب'],
        'retail-shopping' => ['retail', 'Retail & Shopping', 'التجزئة والتسوق'],
        'professional-services' => ['professional', 'Professional Services', 'الخدمات المهنية'],
        'other-services' => ['other', 'Other Services', 'خدمات أخرى'],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::CATEGORIES as $slug => [$icon, $english, $arabic]) {
            $order += 10;

            BusinessCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['en' => $english, 'ar' => $arabic],
                    'icon' => $icon,
                    'sort_order' => $order,
                ],
            );
        }

        $this->command?->info(count(self::CATEGORIES).' business categories ready.');
    }
}
