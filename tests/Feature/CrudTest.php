<?php

declare(strict_types=1);

use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;

it('creates a record through the create tool', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('create_post'), [
            'title' => 'Fresh post',
            'body' => 'Hello from MCP',
            'user_id' => $user->id,
            'team_id' => 1,
        ])
        ->assertOk()
        ->assertSee('Fresh post');

    expect(Post::query()->where('title', 'Fresh post')->exists())->toBeTrue();
});

it('reads a single record through the get tool', function (): void {
    $user = $this->makeUser();
    $post = Post::query()->create(['title' => 'Readable', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('get_post'), ['id' => $post->id])
        ->assertOk()
        ->assertSee('Readable');
});

it('deletes a record the user owns', function (): void {
    $owner = $this->makeUser();
    $post = Post::query()->create(['title' => 'Doomed', 'user_id' => $owner->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($owner)
        ->tool($this->mcpTool('delete_post'), ['id' => $post->id])
        ->assertOk();

    expect(Post::query()->find($post->id))->toBeNull();
});

it('searches records by text across textual columns', function (): void {
    $user = $this->makeUser();
    Post::query()->create(['title' => 'Laravel rocks', 'user_id' => $user->id, 'team_id' => 1]);
    Post::query()->create(['title' => 'Symfony stuff', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('search_posts'), ['q' => 'Laravel'])
        ->assertOk()
        ->assertSee('Laravel rocks')
        ->assertDontSee('Symfony stuff');
});

it('returns a validation error when a required field is missing', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('create_post'), ['body' => 'no title here'])
        ->assertHasErrors();

    expect(Post::query()->count())->toBe(0);
});

it('caps per_page at the configured maximum', function (): void {
    $user = $this->makeUser();

    foreach (range(1, 5) as $i) {
        Post::query()->create(['title' => "Post {$i}", 'user_id' => $user->id, 'team_id' => 1]);
    }

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), ['per_page' => 9999])
        ->assertOk()
        ->assertSee('"per_page":100');
});

it('never leaks an attribute listed in always_hidden', function (): void {
    $user = $this->makeUser();
    Post::query()->create([
        'title' => 'With secrets',
        'user_id' => $user->id,
        'team_id' => 1,
        'secret_notes' => 'TOP-SECRET-VALUE',
    ]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('get_post'), ['id' => 1])
        ->assertOk()
        ->assertDontSee('TOP-SECRET-VALUE');
});
