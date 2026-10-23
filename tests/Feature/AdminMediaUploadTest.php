<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Electrik\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Media is served from the site's own origin, so only inert types detected from the bytes are stored. */
final class AdminMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private function token(): string
    {
        $user = User::factory()->create();
        $team = $user->createOwnedTeam(['name' => 'media'], true);
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $user->assignRole(Role::findOrCreateForTeam('admin', 'web', (int) $team->getKey()));

        return $user->createToken('media', ['*'])->plainTextToken;
    }

    public function test_html_and_svg_are_refused_even_when_labelled_as_images(): void
    {
        Storage::fake('public');
        $token = $this->token();

        foreach ([
            ['x.html', 'text/html', '<html><script>alert(1)</script></html>'],
            ['x.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
            ['x.png', 'image/png', '<script>alert(1)</script>'],
        ] as [$name, $mime, $body]) {
            $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/media', [
                'content_base64' => base64_encode($body),
                'filename' => $name,
                'mime' => $mime,
            ])->assertStatus(422);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_png_is_stored_under_its_detected_extension(): void
    {
        Storage::fake('public');

        $token = $this->token();
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/media', [
            'content_base64' => self::PNG,
            'filename' => '../evil name.html',
            'mime' => 'text/html',
        ])->assertCreated()->assertJsonPath('data.mime', 'image/png');

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^media/[0-9a-f]{32}-[A-Za-z0-9_-]+\.png$#', $files[0]);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/admin/media/'.$response->json('data.id').'/file')
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
