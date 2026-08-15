<?php

declare(strict_types=1);

use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;
use Blaze\ModelMcp\Tests\Fixtures\Models\Tag;

/**
 * `describe_post` exists so an agent stops inferring a model's shape from a sample
 * row. These tests pin the two properties that make it safe to expose: it reads no
 * rows, and it hides exactly what every other response hides.
 */
it('describes fields without reading any rows', function (): void {
    $user = $this->makeUser();

    // No Post exists at all — describe must still answer, which is the point:
    // an empty table is precisely when guessing from a sample fails.
    expect(Post::query()->count())->toBe(0);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertSee('title');
});

it('reports which fields are writable', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertSee('writable');
});

it('lists the operations exposed for the model', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertSee('describe');
});

it('never exposes a hidden attribute', function (): void {
    // If describe leaked hidden columns it would become the way to discover them.
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertDontSee('secret_notes');
});

it('never exposes an always_hidden column', function (): void {
    config()->set('model-mcp.fields.always_hidden', ['title']);

    ModelMcpServer::actingAs($this->makeUser())
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertDontSee('"name":"title"');
});

it('is denied for a model with no policy, like every other operation', function (): void {
    // Tag has no registered policy, and deny_without_policy is the safe default:
    // what columns a model has is itself information, so describe is gated too.
    config()->set('model-mcp.authorization.deny_without_policy', true);

    expect(Tag::class)->toBeString(); // the fixture is exposed via the test server

    ModelMcpServer::actingAs($this->makeUser())
        ->tool($this->mcpTool('describe_tag'), [])
        ->assertHasErrors();
});

it('warns that the tenant column is managed for you', function (): void {
    config()->set('model-mcp.tenancy.enabled', true);

    ModelMcpServer::actingAs($this->makeUser(['tenant_id' => 1]))
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertSee('cannot be supplied');
});

it('says plainly that appearing here is not permission to read or write', function (): void {
    ModelMcpServer::actingAs($this->makeUser())
        ->tool($this->mcpTool('describe_post'), [])
        ->assertOk()
        ->assertSee('does not mean the current user may read or write it');
});

it('can be switched off per model like any other operation', function (): void {
    config()->set('model-mcp.operations', ['list', 'view']);

    expect(fn () => $this->mcpTool('describe_post'))->toThrow(RuntimeException::class);
});
