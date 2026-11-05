<?php

namespace Tests\Feature\Api\V1\Businesses;

use App\Models\BusinessCategory;
use Database\Seeders\BusinessCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCategoryTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/v1/business-categories';

    public function test_the_launch_categories_are_listed_publicly_in_order(): void
    {
        $this->seed(BusinessCategorySeeder::class);

        $response = $this->getJson(self::URI);

        $this->assertApiSuccess($response);
        $this->assertSame(array_keys(BusinessCategorySeeder::CATEGORIES), $response->json('data.*.slug'));
        $response->assertJsonPath('data.0.name.en', 'Restaurants & Cafes')
            ->assertJsonPath('data.0.name.ar', 'المطاعم والمقاهي')
            ->assertJsonPath('data.0.icon', 'restaurant')
            ->assertJsonPath('data.10.slug', 'other-services');
    }

    public function test_every_required_category_exists_with_both_languages(): void
    {
        $this->seed(BusinessCategorySeeder::class);

        $names = collect($this->getJson(self::URI)->json('data'))->pluck('name.en')->all();

        foreach ([
            'Restaurants & Cafes', 'Healthcare & Clinics', 'Beauty & Salons',
            'Banking & Financial Services', 'Government Services', 'Telecommunications',
            'Automotive & Repair', 'Education & Training', 'Retail & Shopping',
            'Professional Services', 'Other Services',
        ] as $required) {
            $this->assertContains($required, $names);
        }

        foreach ($this->getJson(self::URI)->json('data') as $category) {
            $this->assertNotEmpty($category['name']['ar']);
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seed(BusinessCategorySeeder::class);
        $this->seed(BusinessCategorySeeder::class);

        $this->assertSame(count(BusinessCategorySeeder::CATEGORIES), BusinessCategory::query()->count());
    }

    public function test_disabled_categories_are_hidden(): void
    {
        BusinessCategory::factory()->create(['slug' => 'visible']);
        BusinessCategory::factory()->inactive()->create(['slug' => 'retired']);

        $slugs = $this->getJson(self::URI)->json('data.*.slug');

        $this->assertSame(['visible'], $slugs);
    }

    public function test_active_subcategories_are_nested_under_their_parent(): void
    {
        $parent = BusinessCategory::factory()->create(['slug' => 'healthcare-clinics', 'sort_order' => 1]);
        $child = BusinessCategory::factory()->make(['slug' => 'dentists']);
        $child->parent()->associate($parent)->save();
        $retired = BusinessCategory::factory()->inactive()->make(['slug' => 'old-child']);
        $retired->parent()->associate($parent)->save();

        $response = $this->getJson(self::URI);

        $this->assertSame(['healthcare-clinics'], $response->json('data.*.slug'));
        $this->assertSame(['dentists'], $response->json('data.0.children.*.slug'));
    }

    public function test_internal_ids_are_never_exposed(): void
    {
        $this->seed(BusinessCategorySeeder::class);

        foreach ($this->getJson(self::URI)->json('data') as $category) {
            $this->assertArrayNotHasKey('id', $category);
            $this->assertArrayNotHasKey('parent_id', $category);
        }
    }
}
