<?php

declare(strict_types=1);

use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    config()->set('model-mcp.tenancy.enabled', true);
});

it('hides records belonging to another tenant when listing', function (): void {
    $user = $this->makeUser(['tenant_id' => 1]);
    Post::query()->create(['title' => 'Tenant A post', 'user_id' => $user->id, 'team_id' => 1]);
    Post::query()->create(['title' => 'Tenant B post', 'user_id' => 999, 'team_id' => 2]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertOk()
        ->assertSee('Tenant A post')
        ->assertDontSee('Tenant B post');
});

it('treats a cross-tenant record as not found when fetching by id', function (): void {
    $user = $this->makeUser(['tenant_id' => 1]);
    $foreign = Post::query()->create(['title' => 'Tenant B post', 'user_id' => 1, 'team_id' => 2]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('get_post'), ['id' => $foreign->id])
        ->assertHasErrors();
});

it('stamps the resolved tenant onto created records', function (): void {
    $user = $this->makeUser(['tenant_id' => 7]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('create_post'), ['title' => 'New post', 'user_id' => $user->id])
        ->assertOk();

    expect(Post::query()->where('title', 'New post')->first()->team_id)->toEqual(7);
});

it('fails closed when tenancy is enabled but no tenant can be resolved', function (): void {
    $user = $this->makeUser(['tenant_id' => null]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertHasErrors();
});

it('relies on the host global scopes when explicit tenancy is disabled', function (): void {
    config()->set('model-mcp.tenancy.enabled', false);

    $user = $this->makeUser(['tenant_id' => 1]);
    Post::query()->create(['title' => 'Visible everywhere', 'user_id' => $user->id, 'team_id' => 2]);

    // With explicit scoping off, the package adds no where() — the row is visible.
    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertOk()
        ->assertSee('Visible everywhere');
});
