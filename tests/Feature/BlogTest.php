<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Electrik\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_blog_lists_filters_and_hides_drafts(): void
    {
        $token = $this->adminToken();
        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/categories', ['name' => ['ka' => 'სიახლეები', 'en' => 'News']])
            ->assertCreated()
            ->json('data');
        $tag = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/tags', ['name' => ['ka' => 'გასაღები', 'en' => 'Keys']])
            ->assertCreated()
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/posts', [
                'status' => 'published',
                'category_ids' => [$category['id']],
                'tag_ids' => [$tag['id']],
                'translations' => [
                    'ka' => ['title' => 'თაობა', 'body' => str_repeat('სიტყვა ', 220), 'excerpt' => 'მოკლე'],
                    'en' => ['title' => 'Generation notes', 'body' => 'English body', 'meta_title' => 'Generation meta', 'meta_description' => 'A short English description for the card.'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'generation-notes');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/posts', [
                'slug' => 'hidden-draft',
                'status' => 'draft',
                'translations' => ['ka' => ['title' => 'შავი', 'body' => 'დაფარული']],
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/posts', [
                'slug' => 'generation-notes',
                'status' => 'draft',
                'translations' => ['ka' => ['title' => 'დუბლი', 'body' => 'ტექსტი']],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');

        $this->leaveAdmin();

        $this->get('/blog')
            ->assertOk()
            ->assertSee('თაობა')
            ->assertSee('2 წთ', false)
            ->assertDontSee('შავი');

        $this->get('/blog?category=news')->assertOk()->assertSee('თაობა');
        $this->get('/blog?category=missing')->assertOk()->assertDontSee('თაობა');
        $this->get('/blog?tag=keys')->assertOk()->assertSee('თაობა');
        $this->get('/blog/hidden-draft')->assertNotFound();
        $this->get('/blog/missing-post')->assertNotFound();
        $this->get('/en/blog/generation-notes')
            ->assertOk()
            ->assertSee('Generation meta')
            ->assertSee('application/ld+json', false)
            ->assertSee('BlogPosting', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('hreflang="en"', false)
            ->assertSee('og:image', false);

        $this->get('/ka/blog/feed.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('თაობა', false);

        $this->get('/sitemap.xml')->assertOk()->assertSee('/ka/blog/generation-notes', false);
    }

    public function test_scheduled_posts_wait_for_publish_at_and_related_posts_render(): void
    {
        $token = $this->adminToken();
        $category = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/blog/categories', ['slug' => 'guides', 'name' => ['en' => 'Guides']])
            ->json('data.id');
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/blog/posts', [
            'slug' => 'live-note',
            'status' => 'published',
            'category_ids' => [$category],
            'translations' => ['ka' => ['title' => 'ცოცხალი', 'body' => 'ტექსტი']],
        ])->assertCreated();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/blog/posts', [
            'slug' => 'later-note',
            'status' => 'scheduled',
            'publish_at' => now()->addDay()->toIso8601String(),
            'category_ids' => [$category],
            'translations' => ['ka' => ['title' => 'მერე', 'body' => 'ტექსტი']],
        ])->assertCreated();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/blog/posts', [
            'status' => 'scheduled',
            'translations' => ['ka' => ['title' => 'უვადო', 'body' => 'ტექსტი']],
        ])->assertStatus(422);
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/admin/blog/posts', [
            'slug' => 'sibling-note',
            'status' => 'published',
            'category_ids' => [$category],
            'translations' => ['ka' => ['title' => 'მეზობელი', 'body' => 'ტექსტი']],
        ])->assertCreated();

        $this->leaveAdmin();
        $this->get('/blog')->assertOk()->assertSee('ცოცხალი')->assertDontSee('მერე');
        $this->get('/blog/later-note')->assertNotFound();
        $this->get('/ka/blog/live-note')->assertOk()->assertSee('მეზობელი')->assertDontSee('მერე');
    }

    public function test_admin_panel_and_cover_upload(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        $this->actingAs($admin)->get('/admin/blog')->assertOk()->assertSee('ბლოგი');
        $this->actingAs($admin)->get('/admin/blog/create')->assertOk()->assertSee('სლაგის შევსება', false)->assertSee('Google', false);

        $this->actingAs($admin)->post('/admin/blog', [
            'status' => 'draft',
            'translations' => [
                'ka' => ['title' => 'პანელი', 'body' => 'ტექსტი **სქელი**'],
            ],
        ])->assertRedirect('/admin/blog/1/edit');

        $this->actingAs($admin)->get('/blog/paneli')->assertOk()->assertSee('noindex', false)->assertSee('<strong>სქელი</strong>', false);

        $this->actingAs($admin)->put('/admin/blog/1', [
            'status' => 'draft',
            'translations' => [
                'ka' => ['title' => 'პანელი', 'body' => 'ტექსტი **სქელი**'],
            ],
            'file' => UploadedFile::fake()->image('panel.jpg'),
        ])->assertRedirect('/admin/blog/1/edit');
        $this->assertStringStartsWith('blog/covers/', (string) BlogPost::query()->findOrFail(1)->cover_path);

        $this->actingAsGuest();
        $token = $admin->createToken('drom.amin', ['*'])->plainTextToken;
        $cover = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/admin/blog/posts/1/cover', ['file' => UploadedFile::fake()->image('cover.jpg')]);
        $cover->assertOk();
        $this->assertStringStartsWith('blog/covers/', (string) $cover->json('data.cover_path'));

        $this->actingAs(User::factory()->create())->get('/admin/blog')->assertNotFound();
        $this->leaveAdmin();
        $this->get('/admin/blog')->assertRedirect('/login');

        $spec = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/docs/openapi.json');
        $spec->assertOk();
        $spec->assertJsonStructure(['paths' => ['/api/admin/blog/posts' => ['get', 'post']]]);
        $this->assertSame(
            '#/components/schemas/BlogPost',
            $spec->json('paths')['/api/admin/blog/posts']['post']['requestBody']['content']['application/json']['schema']['$ref']
        );
    }

    private function leaveAdmin(): void
    {
        $this->withoutHeader('Authorization');
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function adminToken(): string
    {
        return $this->adminUser()->createToken('drom.amin', ['*'])->plainTextToken;
    }

    private function adminUser(): User
    {
        $user = User::factory()->create([
            'name' => 'drom.amin',
            'email' => 'blog-admin-'.uniqid().'@example.com',
        ]);
        $team = $user->createOwnedTeam(['name' => 'blog'], true);
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $role = Role::findOrCreateForTeam('admin', 'web', (int) $team->getKey());
        $user->assignRole($role);
        $this->assertTrue(DB::table('roles')->where('name', 'admin')->exists());

        return $user;
    }
}
