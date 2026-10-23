<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalog\CatalogApi;
use App\Catalog\PublicSpec;
use Geoapi\Platform\MemoryUsageStore;
use JsonSchema\Validator;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class PublicDocsTest extends TestCase
{
    use SeedsPublicCatalog;

    public function test_openapi_document_matches_the_official_schema(): void
    {
        $schema = json_decode((string) file_get_contents(base_path('tests/fixtures/openapi-3.0.schema.json')), false, 512, JSON_THROW_ON_ERROR);
        $document = json_decode(json_encode(PublicSpec::document(), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $validator = new Validator;
        $validator->validate($document, $schema);

        $errors = array_map(
            fn (array $error): string => $error['property'].': '.$error['message'],
            $validator->getErrors(),
        );
        $this->assertSame([], $errors);
        $this->assertSame('3.0.3', PublicSpec::document()['openapi']);
        $this->assertSame(PublicSpec::VERSION, PublicSpec::document()['info']['version']);
        $this->assertSame(PublicSpec::ORIGIN, PublicSpec::document()['servers'][0]['url']);
        $this->assertSame('https://geoapi.ge', PublicSpec::ORIGIN);
    }

    public function test_every_operation_documents_the_live_contract(): void
    {
        $document = PublicSpec::document();
        $encoded = json_encode($document, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('/api/admin', $encoded);
        $this->assertStringNotContainsString('/api/docs', $encoded);
        $this->assertStringNotContainsString('/api/v1/vin', $encoded);
        $this->assertStringNotContainsString('59', $encoded);
        $this->assertDoesNotMatchRegularExpression('/geoapi_sk_[A-Za-z0-9]{8,}_/', $encoded);

        $operationIds = [];
        foreach ($this->endpointRows() as $row) {
            $operation = $document['paths'][$row['path']]['get'];
            $operationIds[] = $operation['operationId'];
            $description = (string) $operation['description'];
            foreach ([
                'geoapi_sk_',
                'geoapi_pk_',
                '401 unauthorized',
                '403 forbidden',
                '404 not_found',
                '405 method_not_allowed',
                '422 invalid_parameter',
                '429',
                '503 unavailable',
                'RATE_LIMIT_EXCEEDED',
                'DAILY_QUOTA_EXCEEDED',
                'MONTHLY_QUOTA_EXCEEDED',
                'null',
                'not published',
                '1.2.0',
                'https://geoapi.ge',
                '401, not 405',
                'not enforced in production today',
                'not published yet',
                'confirm your email',
                '/account/products',
            ] as $needle) {
                $this->assertStringContainsString($needle, $description, $row['path'].' '.$needle);
            }
            $this->assertSame($row['example'], $operation['x-geoapi-example']);
            $this->assertCount(4, $operation['x-codeSamples']);

            if ($row['mode'] !== 'list') {
                continue;
            }
            $names = [];
            foreach ($operation['parameters'] as $parameter) {
                if (($parameter['in'] ?? null) === 'path') {
                    continue;
                }
                $names[] = $this->parameterName($parameter, $document);
            }
            $this->assertSame(CatalogApi::listParameters($row['kind']), $names, $row['path']);
            $sort = $this->sortParameter($operation);
            $this->assertSame(CatalogApi::sortAllowlist($row['kind']), $sort['schema']['enum']);
        }
        $this->assertSame($operationIds, array_values(array_unique($operationIds)));

        $fuel = $this->parameterNames($document['paths']['/api/v1/fuel']['get'], $document);
        $this->assertContains('body', $fuel);
        $this->assertContains('drive', $fuel);
        $this->assertContains('transmission', $fuel);
        $bodies = $this->parameterNames($document['paths']['/api/v1/bodies']['get'], $document);
        $this->assertNotContains('modified_after', $bodies);
        $years = $this->parameterNames($document['paths']['/api/v1/models/{id}/years']['get'], $document);
        $this->assertNotContains('q', $years);
        $this->assertStringContainsString('verified', (string) $document['paths']['/api/v1/colors']['get']['description']);
        $this->assertStringNotContainsString('omitted', (string) $document['paths']['/api/v1/colors']['get']['description']);
    }

    public function test_published_examples_execute_and_match_the_schema(): void
    {
        MemoryUsageStore::shared()->perMinute = 5000;
        $document = PublicSpec::document();
        foreach ($this->endpointRows() as $row) {
            $response = $this->getJson($row['example'])->assertOk();
            $schema = $document['paths'][$row['path']]['get']['responses']['200']['content']['application/json']['schema'];
            $this->assertMatchesSchema($response->json(), $schema, $document, $row['example']);

            $path = (string) parse_url($row['example'], PHP_URL_PATH);
            if ($row['mode'] !== 'list') {
                continue;
            }
            foreach (CatalogApi::listParameters($row['kind']) as $name) {
                if ($name === 'sort') {
                    foreach (CatalogApi::sortAllowlist($row['kind']) as $sort) {
                        $this->getJson($path.'?sort='.urlencode($sort))->assertOk();
                    }

                    continue;
                }
                $this->getJson($path.'?'.$name.'='.urlencode($this->sampleQuery($name)))->assertOk();
            }
        }
    }

    public function test_code_samples_compile_and_the_guide_runs_them(): void
    {
        $directory = sys_get_temp_dir().'/geoapi-docs-'.bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            foreach ($this->endpointRows() as $row) {
                $samples = PublicSpec::codeSamples($row['example']);
                $this->assertStringContainsString(PublicSpec::ORIGIN.$row['example'], $samples['cURL']);
                $this->assertDoesNotMatchRegularExpression('/geoapi_sk_[A-Za-z0-9]{8,}_/', implode("\n", $samples));
                $this->lint($directory, $samples);
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }

        foreach (['ka', 'en'] as $locale) {
            $html = (string) $this->withSession(['locale' => $locale])->get('/guide')->assertOk()->getContent();
            $this->assertStringContainsString('https://geoapi.ge/api/v1/brands?limit=1', $html);
            $this->assertStringNotContainsString('Unverified rows are omitted', $html);
            $this->assertStringNotContainsString('დაუდასტურებელი სტრიქონი არ ბრუნდება', $html);
            if ($locale === 'en') {
                $this->assertStringContainsString('those rows stay in the list.', $html);
            } else {
                $this->assertStringContainsString('ასეთი ჩანაწერი სიაში რჩება', $html);
            }
            $this->assertStringNotContainsString('/api/admin', $html);
            $this->assertStringNotContainsString('$59', $html);
            $this->assertStringNotContainsString('npm install', $html);
            $this->assertStringContainsString($locale === 'en' ? 'not published' : 'გამოქვეყნებული არ არის', $html);
            $this->assertStringContainsString($locale === 'en' ? 'MCP server 0.1.0' : 'MCP სერვერი 0.1.0', $html);
            $this->assertStringContainsString('CLI 0.1.0', $html);
            $this->assertStringContainsString('geoapi_list_brands', $html);
            $this->assertStringContainsString('geoapi brands list --limit 20 --json', $html);
            $this->assertStringContainsString('public-key=&quot;geoapi_pk_…&quot;', $html);
            preg_match_all('#https://geoapi\.ge(/api/v1/[^\\\\\s"<&]+)#', $html, $matches);
            $paths = array_values(array_unique($matches[1]));
            sort($paths);
            $expected = array_column($this->endpointRows(), 'example');
            sort($expected);
            $this->assertSame($expected, $paths);
            foreach ($paths as $path) {
                $this->getJson($path)->assertOk();
            }
        }
    }

    public function test_postman_collection_matches_openapi_and_has_no_secret(): void
    {
        $collection = $this->getJson('/api/v1/postman.json')->assertOk()->json();
        $this->assertSame(PublicSpec::ORIGIN, $collection['variable'][0]['value']);
        $this->assertSame('geoapi_sk_…', $collection['variable'][1]['value']);
        $encoded = json_encode($collection, JSON_THROW_ON_ERROR);
        $this->assertDoesNotMatchRegularExpression('/geoapi_sk_[A-Za-z0-9]{8,}_/', $encoded);
        $this->assertStringNotContainsString('/api/admin', $encoded);

        $document = PublicSpec::document();
        $names = array_column($collection['item'], 'name');
        $this->assertSame(array_keys($document['paths']), $names);
        foreach ($collection['item'] as $item) {
            $this->assertSame('GET', $item['request']['method']);
            $this->assertSame($document['paths'][$item['name']]['get']['summary'], $item['request']['description']);
            $this->assertStringStartsWith('{{baseUrl}}', $item['request']['url']['raw']);
            $this->assertSame([], $item['request']['header']);
            foreach ($item['request']['url']['variable'] ?? [] as $variable) {
                $this->assertSame('', $variable['value']);
            }
        }

        $this->withSession(['locale' => 'en'])->get('/api/v1/docs')
            ->assertOk()
            ->assertSee('Admin routes are not in this document', false);
        $this->withSession(['locale' => 'ka'])->get('/api/v1/docs')
            ->assertOk()
            ->assertSee('ადმინისტრაციული მისამართები ამ დოკუმენტში არ არის', false);
    }

    public function test_error_bodies_match_the_declared_schema_and_have_no_request_id(): void
    {
        $schema = PublicSpec::document()['components']['schemas']['Error'];

        $this->withoutTestProject();
        $unauthorized = $this->getJson('/api/v1/brands')->assertUnauthorized();
        $this->assertMatchesSchema($unauthorized->json(), $schema, PublicSpec::document(), '401');
        $this->assertSame(['code', 'message'], array_keys($unauthorized->json('error')));
        $_ENV['GEOAPI_CATALOG_TEST_PROJECT'] = '7';
        $_SERVER['GEOAPI_CATALOG_TEST_PROJECT'] = '7';

        $invalid = $this->getJson('/api/v1/brands?limit=1001')->assertStatus(422);
        $this->assertMatchesSchema($invalid->json(), $schema, PublicSpec::document(), '422');
        $missing = $this->getJson('/api/v1/modifications/999')->assertNotFound();
        $this->assertMatchesSchema($missing->json(), $schema, PublicSpec::document(), '404');

        $method = $this->postJson('/api/v1/brands')->assertStatus(405);
        $method->assertJsonPath('error.code', 'method_not_allowed')->assertHeader('Allow', 'GET');
        $this->assertMatchesSchema($method->json(), $schema, PublicSpec::document(), '405');

        MemoryUsageStore::reset();
        MemoryUsageStore::shared()->perMinute = 1;
        $this->getJson('/api/v1/coverage')->assertOk();
        $limited = $this->getJson('/api/v1/coverage')->assertStatus(429);
        $limited->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')->assertHeader('Retry-After');
        $this->assertMatchesSchema($limited->json(), $schema, PublicSpec::document(), '429');
        $this->assertSame(['code', 'message'], array_keys($limited->json('error')));
    }

    public function test_a_post_without_a_key_is_401_and_the_daily_cap_is_documented_inactive(): void
    {
        $this->withoutTestProject();
        $this->postJson('/api/v1/brands')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthorized');
        $_ENV['GEOAPI_CATALOG_TEST_PROJECT'] = '7';
        $_SERVER['GEOAPI_CATALOG_TEST_PROJECT'] = '7';
        $this->postJson('/api/v1/brands')
            ->assertStatus(405)
            ->assertHeader('Allow', 'GET')
            ->assertJsonPath('error.code', 'method_not_allowed');

        $english = (string) $this->withSession(['locale' => 'en'])->get('/guide')->assertOk()->getContent();
        $this->assertStringContainsString('401, not 405', $english);
        $this->assertStringContainsString('not enforced in production today', $english);
        $this->assertStringContainsString('not published yet', $english);
        $this->assertStringContainsString('requestId is null', $english);
        $this->assertStringContainsString('<details class="toc-m">', $english);
        $this->assertStringContainsString('<summary>On this page</summary>', $english);
        $this->assertStringContainsString('<aside class="toc"', $english);

        $georgian = (string) $this->withSession(['locale' => 'ka'])->get('/guide')->assertOk()->getContent();
        $this->assertStringContainsString('არა 405', $georgian);
        $this->assertStringContainsString('მოქმედ სერვისზე დღეს არ მოქმედებს', $georgian);
        $this->assertStringContainsString('<summary>ამ გვერდზე</summary>', $georgian);

        $css = (string) file_get_contents(public_path('assets/public-design.css'));
        $this->assertStringContainsString('.guide-toc-mobile { display: none; }', $css);
        $this->assertStringContainsString('.guide-toc-desktop { display: none; }', $css);
        $this->assertStringContainsString('.guide-toc-mobile { display: block; }', $css);

        $snapshot = json_decode((string) file_get_contents(base_path('packages/sdk-js/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(PublicSpec::document(), $snapshot);
    }

    /** @param array<string, string> $samples */
    private function lint(string $directory, array $samples): void
    {
        file_put_contents($directory.'/node.mjs', $samples['Node.js']);
        $this->command(['node', '--check', $directory.'/node.mjs']);
        file_put_contents($directory.'/sample.py', $samples['Python']);
        $this->command(['python3', '-c', 'import ast, pathlib, sys; ast.parse(pathlib.Path(sys.argv[1]).read_text());', $directory.'/sample.py']);
        file_put_contents($directory.'/sample.php', "<?php\n".$samples['PHP']);
        $this->command(['php', '-l', $directory.'/sample.php']);
    }

    /** @param list<string> $command */
    private function command(array $command): void
    {
        $process = new Process($command, null, ['PYTHONDONTWRITEBYTECODE' => '1']);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }

    /** @return list<array{path:string, kind:string, mode:string, example:string}> */
    private function endpointRows(): array
    {
        $rows = [];
        foreach (PublicSpec::document()['paths'] as $path => $item) {
            $operation = $item['get'];
            $rows[] = [
                'path' => $path,
                'kind' => $operation['x-geoapi-kind'],
                'mode' => $operation['x-geoapi-mode'],
                'example' => $operation['x-geoapi-example'],
            ];
        }

        return $rows;
    }

    private function sampleQuery(string $name): string
    {
        return match ($name) {
            'page', 'limit', 'per_page' => '1',
            'direction' => 'desc',
            'q' => 'a',
            'modified_after' => '2020-01-01T00:00:00Z',
            'year_from' => '2020',
            'year_to' => '2024',
            'body' => 'sedan',
            'fuel' => 'petrol',
            'drive' => 'fwd',
            'transmission' => 'at',
            'brand_id' => '3',
            'model_id' => '10',
            'volume_from' => '1000',
            'volume_to' => '3000',
            default => '1',
        };
    }

    /** @param array<string, mixed> $parameter @param array<string, mixed> $document */
    private function parameterName(array $parameter, array $document): string
    {
        if (isset($parameter['$ref'])) {
            $name = substr((string) $parameter['$ref'], strlen('#/components/parameters/'));

            return (string) $document['components']['parameters'][$name]['name'];
        }

        return (string) $parameter['name'];
    }

    /** @param array<string, mixed> $operation @param array<string, mixed> $document @return list<string> */
    private function parameterNames(array $operation, array $document): array
    {
        $names = [];
        foreach ($operation['parameters'] as $parameter) {
            $names[] = $this->parameterName($parameter, $document);
        }

        return $names;
    }

    /** @param array<string, mixed> $operation @return array<string, mixed> */
    private function sortParameter(array $operation): array
    {
        foreach ($operation['parameters'] as $parameter) {
            if (($parameter['name'] ?? null) === 'sort') {
                return $parameter;
            }
        }

        $this->fail('sort parameter missing');
    }

    private function withoutTestProject(): void
    {
        unset($_ENV['GEOAPI_CATALOG_TEST_PROJECT'], $_SERVER['GEOAPI_CATALOG_TEST_PROJECT']);
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $document */
    private function assertMatchesSchema(mixed $value, array $schema, array $document, string $pointer): void
    {
        if (isset($schema['$ref'])) {
            $schema = $this->resolveRef((string) $schema['$ref'], $document);
        }
        if (isset($schema['allOf'])) {
            foreach ($schema['allOf'] as $index => $part) {
                $this->assertMatchesSchema($value, $part, $document, $pointer.' allOf '.$index);
            }

            return;
        }
        if ($value === null) {
            $this->assertTrue((bool) ($schema['nullable'] ?? false), $pointer.' is null');

            return;
        }
        $type = $schema['type'] ?? null;
        if ($type === 'object') {
            $this->assertIsArray($value, $pointer);
            $properties = $schema['properties'] ?? [];
            if (($schema['additionalProperties'] ?? true) === false) {
                $this->assertEqualsCanonicalizing(array_keys($properties), array_keys($value), $pointer);
            }
            foreach ($schema['required'] ?? [] as $key) {
                $this->assertArrayHasKey($key, $value, $pointer.'.'.$key);
            }
            foreach ($properties as $key => $property) {
                if (array_key_exists($key, $value)) {
                    $this->assertMatchesSchema($value[$key], $property, $document, $pointer.'.'.$key);
                }
            }

            return;
        }
        if ($type === 'array') {
            $this->assertIsArray($value, $pointer);
            if (isset($schema['items']) && array_is_list($value) && $value !== []) {
                $this->assertMatchesSchema($value[0], $schema['items'], $document, $pointer.'.0');
            }

            return;
        }
        if ($type === 'string') {
            $this->assertIsString($value, $pointer);
        } elseif ($type === 'integer') {
            $this->assertIsInt($value, $pointer);
        } elseif ($type === 'number') {
            $this->assertTrue(is_int($value) || is_float($value), $pointer);
        } elseif ($type === 'boolean') {
            $this->assertIsBool($value, $pointer);
        }
    }

    /** @param array<string, mixed> $document @return array<string, mixed> */
    private function resolveRef(string $ref, array $document): array
    {
        $cursor = $document;
        foreach (explode('/', substr($ref, 2)) as $part) {
            $cursor = $cursor[$part];
        }

        return $cursor;
    }
}
