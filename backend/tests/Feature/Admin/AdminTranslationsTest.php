<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPeriod;
use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Enums\CheckoutStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Enums\PlatformAuditAction;
use App\Enums\SubscriptionEventType;
use App\Enums\UserStatus;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Services\Admin\PlatformStatistics;
use App\Services\Platform\PlatformSettings;
use Illuminate\Support\Arr;
use ReflectionClassConstant;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The admin dashboard is complete in English and Arabic
 * (lang/en/admin.php, lang/ar/admin.php).
 */
class AdminTranslationsTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function strings(string $locale): array
    {
        return Arr::dot(require lang_path($locale.'/admin.php'));
    }

    public function test_english_and_arabic_have_the_same_keys_and_placeholders(): void
    {
        $en = $this->strings('en');
        $ar = $this->strings('ar');

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ar))), 'Missing in Arabic.');
        $this->assertSame([], array_values(array_diff(array_keys($ar), array_keys($en))), 'Missing in English.');
        foreach ($en as $key => $text) {
            preg_match_all('/:([a-z_]+)/', $text, $enPlaceholders);
            preg_match_all('/:([a-z_]+)/', $ar[$key], $arPlaceholders);
            $this->assertEqualsCanonicalizing(array_unique($enPlaceholders[1]), array_unique($arPlaceholders[1]), "Placeholders differ for admin.$key.");
            $this->assertNotSame('', trim($ar[$key]), "admin.$key is empty in Arabic.");
        }
    }

    public function test_every_key_used_in_the_code_exists(): void
    {
        $keys = [];
        $finder = (new Finder)->files()->in([app_path(), resource_path('views/admin')])->name(['*.php']);
        foreach ($finder as $file) {
            $source = $file->getContents();
            foreach ($this->firstArguments($source) as $argument) {
                preg_match_all("/'(admin\\.[a-z0-9_.]+)'/", $argument, $literals);
                foreach ($literals[1] as $literal) {
                    if (! str_ends_with($literal, '.')) {
                        $keys[$literal] = $file->getRelativePathname();
                    }
                }
            }
            preg_match_all("/\\\$label\\('([a-z_]+)'\\)/", $source, $labels);
            foreach ($labels[1] as $label) {
                $keys['admin.settings.server.'.$label] = $file->getRelativePathname();
            }
        }
        $this->assertGreaterThan(300, count($keys));

        foreach (['en', 'ar'] as $locale) {
            $strings = $this->strings($locale);
            foreach ($keys as $key => $file) {
                $this->assertArrayHasKey(substr($key, strlen('admin.')), $strings, "$key ($file) is missing in $locale.");
            }
        }
    }

    public function test_every_value_shown_by_name_has_a_label(): void
    {
        $values = fn (array $cases) => array_map(fn ($case) => $case->value, $cases);
        $groups = [
            'states' => PlatformStatistics::STATES,
            'business_status' => $values(BusinessStatus::cases()),
            'user_status' => $values(UserStatus::cases()),
            'membership_status' => $values(MembershipStatus::cases()),
            'roles' => $values(BusinessRole::cases()),
            'periods' => $values(BillingPeriod::cases()),
            'methods' => $values(PaymentMethod::cases()),
            'limits' => $values(PlanLimit::cases()),
            'features' => [...$values(PlanFeature::cases()), ...array_map(fn ($f) => $f.'_help', $values(PlanFeature::cases()))],
            'checkout_status' => $values(CheckoutStatus::cases()),
            'events' => $values(SubscriptionEventType::cases()),
            'audit.actions' => PlatformAuditAction::values(),
            'payments.statuses' => ['awaiting', ...$values(InvoiceStatus::cases())],
            'payments.sources' => ['manual', 'gateway', 'admin'],
            'payments.sources_help' => ['manual', 'gateway', 'admin'],
            'payments.tabs' => (new ReflectionClassConstant(PaymentController::class, 'TABS'))->getValue(),
            'settings.fields' => array_keys(PlatformSettings::FIELDS),
            'settings.help' => array_keys(PlatformSettings::FIELDS),
            'settings.groups' => array_keys((new ReflectionClassConstant(SettingsController::class, 'GROUPS'))->getValue()),
            'errors' => $this->reasons('/(?:notAllowed|forbidden)\(\'([a-z_]+)\'\)/'),
            'errors.change' => $this->reasons('/changeNotAllowed\(\'([a-z_]+)\'\)/'),
        ];

        foreach (['en', 'ar'] as $locale) {
            $strings = $this->strings($locale);
            foreach ($groups as $group => $members) {
                $this->assertNotEmpty($members, $group);
                foreach ($members as $member) {
                    $this->assertArrayHasKey("$group.$member", $strings, "admin.$group.$member is missing in $locale.");
                }
            }
        }
    }

    public function test_arabic_validation_messages_exist_for_the_rules_the_forms_use(): void
    {
        $messages = require lang_path('ar/validation.php');
        foreach (['required', 'string', 'integer', 'boolean', 'in', 'unique', 'regex', 'date_format', 'after_or_equal', 'before_or_equal', 'array', 'distinct', 'required_unless', 'email'] as $rule) {
            $this->assertArrayHasKey($rule, $messages, $rule);
        }
        foreach (['min', 'max', 'size'] as $rule) {
            $this->assertSame(['array', 'file', 'numeric', 'string'], array_keys($messages[$rule]), $rule);
        }
    }

    /**
     * The first argument of each translation call (__, trans, trans_choice, Lang::has).
     *
     * @return list<string>
     */
    private function firstArguments(string $source): array
    {
        $arguments = [];
        preg_match_all('/(?<![A-Za-z0-9_>$])(?:__|trans_choice|trans|Lang::has)\(/', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$call, $offset]) {
            $depth = 0;
            $quote = null;
            $argument = '';
            for ($i = $offset + strlen($call); $i < strlen($source); $i++) {
                $char = $source[$i];
                if ($quote !== null) {
                    $argument .= $char;
                    if ($char === '\\') {
                        $argument .= $source[++$i] ?? '';
                    } elseif ($char === $quote) {
                        $quote = null;
                    }

                    continue;
                }
                if ($char === "'" || $char === '"') {
                    $quote = $char;
                } elseif ($char === '(' || $char === '[') {
                    $depth++;
                } elseif ($char === ')' || $char === ']') {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($char === ',' && $depth === 0) {
                    break;
                }
                $argument .= $char;
            }
            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * @return list<string>
     */
    private function reasons(string $pattern): array
    {
        $reasons = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            preg_match_all($pattern, $file->getContents(), $found);
            array_push($reasons, ...$found[1]);
        }

        return array_values(array_unique($reasons));
    }
}
