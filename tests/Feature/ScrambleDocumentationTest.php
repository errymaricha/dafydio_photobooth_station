<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScrambleDocumentationTest extends TestCase
{
    public function test_scramble_documentation_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('scramble.docs.ui'));
        $this->assertTrue(Route::has('scramble.docs.document'));

        $this->assertSame('/docs/api', route('scramble.docs.ui', absolute: false));
        $this->assertSame('/docs/api.json', route('scramble.docs.document', absolute: false));
    }

    public function test_scramble_documentation_configuration_matches_application_api(): void
    {
        $this->assertSame('api', config('scramble.api_path'));
        $this->assertSame('Photobooth Station API', config('scramble.ui.title'));
        $this->assertSame(
            'API documentation for device, editor, and print agent workflows.',
            config('scramble.info.description'),
        );
        $this->assertTrue(Gate::allows('viewApiDocs'));
    }
}
