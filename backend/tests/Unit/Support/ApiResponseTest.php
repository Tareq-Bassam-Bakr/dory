<?php

namespace Tests\Unit\Support;

use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope(): void
    {
        $response = ApiResponse::success(['a' => 1], 'Done');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'message' => 'Done', 'data' => ['a' => 1], 'meta' => null],
            $response->getData(true),
        );
    }

    public function test_error_envelope(): void
    {
        $response = ApiResponse::error('Nope', 409, 'conflict', ['field' => ['Taken']]);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            ['success' => false, 'message' => 'Nope', 'code' => 'conflict', 'errors' => ['field' => ['Taken']]],
            $response->getData(true),
        );
    }

    public function test_arabic_text_is_not_unicode_escaped(): void
    {
        $response = ApiResponse::success(['name' => 'عيادة الشفاء']);

        $this->assertStringContainsString('عيادة الشفاء', $response->getContent());
    }

    public function test_paginated_response_puts_paging_details_in_meta(): void
    {
        // In-memory models only (no creator), so no database is needed.
        $items = Business::factory()->count(2)->make(['created_by' => null]);
        $paginator = new LengthAwarePaginator($items, total: 7, perPage: 2, currentPage: 3);

        $payload = ApiResponse::paginated($paginator, BusinessResource::class)->getData(true);

        $this->assertCount(2, $payload['data']);
        $this->assertSame(
            ['current_page' => 3, 'per_page' => 2, 'total' => 7, 'last_page' => 4],
            $payload['meta']['pagination'],
        );
    }

    public function test_per_page_is_clamped_to_safe_bounds(): void
    {
        $this->assertSame(Pagination::DEFAULT_PER_PAGE, Pagination::perPage(Request::create('/x')));
        $this->assertSame(Pagination::DEFAULT_PER_PAGE, Pagination::perPage(Request::create('/x', parameters: ['per_page' => 'abc'])));
        $this->assertSame(25, Pagination::perPage(Request::create('/x', parameters: ['per_page' => '25'])));
        $this->assertSame(Pagination::MAX_PER_PAGE, Pagination::perPage(Request::create('/x', parameters: ['per_page' => '5000'])));
        $this->assertSame(1, Pagination::perPage(Request::create('/x', parameters: ['per_page' => '-3'])));
    }
}
