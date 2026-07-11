<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiGenerationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_generations_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ai_generations'));
        $this->assertTrue(Schema::hasColumns('ai_generations', [
            'id', 'organization_id', 'release_id', 'requested_by_user_id',
            'provider', 'status', 'input_fields', 'output', 'error_message',
        ]));
    }
}
