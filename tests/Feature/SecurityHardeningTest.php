<?php

declare(strict_types=1);

use Blaze\ModelMcp\Registry\ModelRegistry;
use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;
use Blaze\ModelMcp\Tests\Fixtures\Models\Tag;
use Blaze\ModelMcp\Tools\ToolFactory;

it('never advertises the global tenant column as writable when tenancy is on', function (): void {
    config()->set('model-mcp.tenancy.enabled', true);
    config()->set('model-mcp.tenancy.column', 'team_id');
    config()->set('model-mcp.models', [Post::class]); // no per-model tenant_column override
    $this->app->make(ModelRegistry::class)->flush();

    $properties = $this->mcpTool('create_post')->toArray()['inputSchema']['properties'];

    expect($properties)->not->toHaveKey('team_id');
});

it('cannot move a record to another tenant through update', function (): void {
    config()->set('model-mcp.tenancy.enabled', true);

    $owner = $this->makeUser(['tenant_id' => 1]);
    $post = Post::query()->create(['title' => 'Original', 'user_id' => $owner->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($owner)
        ->tool($this->mcpTool('update_post'), ['id' => $post->id, 'title' => 'Renamed', 'team_id' => 2])
        ->assertOk();

    expect($post->fresh()->team_id)->toEqual(1);
    expect($post->fresh()->title)->toBe('Renamed');
});

it('treats search wildcards literally instead of dumping every row', function (): void {
    $user = $this->makeUser();
    Post::query()->create(['title' => 'Alpha', 'user_id' => $user->id, 'team_id' => 1]);
    Post::query()->create(['title' => 'Beta', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('search_posts'), ['q' => '%'])
        ->assertOk()
        ->assertDontSee('Alpha')
        ->assertDontSee('Beta');
});

it('throws a clear error when two models generate the same tool name', function (): void {
    config()->set('model-mcp.models', [
        Post::class => ['name' => 'thing'],
        Tag::class => ['name' => 'thing'],
    ]);
    $this->app->make(ModelRegistry::class)->flush();

    expect(fn () => $this->app->make(ToolFactory::class)->make())
        ->toThrow(LogicException::class);
});

it('generates no write tools when the global read_only switch is on', function (): void {
    config()->set('model-mcp.read_only', true);
    $this->app->make(ModelRegistry::class)->flush();

    $names = array_map(fn ($tool): string => $tool->name(), $this->app->make(ToolFactory::class)->make());

    expect($names)->toContain('list_posts', 'get_post', 'search_posts');
    expect($names)->not->toContain('create_post', 'update_post', 'delete_post');
});

it('rejects an array id instead of returning a collection', function (): void {
    $user = $this->makeUser();
    Post::query()->create(['title' => 'Real', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('get_post'), ['id' => [1, 2, 3]])
        ->assertHasErrors();
});

it('returns an empty set when tenancy is on, the tenant is unresolved, and fail_closed is off', function (): void {
    config()->set('model-mcp.tenancy.enabled', true);
    config()->set('model-mcp.tenancy.fail_closed', false);

    $user = $this->makeUser(['tenant_id' => null]);
    Post::query()->create(['title' => 'Should stay hidden', 'user_id' => 1, 'team_id' => 5]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertOk()
        ->assertDontSee('Should stay hidden');
});
