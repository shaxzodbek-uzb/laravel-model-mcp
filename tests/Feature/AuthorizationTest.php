<?php

declare(strict_types=1);

use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;

it('fails closed when a model has no policy', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_tags'), [])
        ->assertHasErrors();
});

it('requires an authenticated user by default', function (): void {
    ModelMcpServer::tool($this->mcpTool('list_posts'), [])
        ->assertHasErrors();
});

it('allows listing when the viewAny ability passes', function (): void {
    $user = $this->makeUser();
    Post::query()->create(['title' => 'Hello World', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertOk()
        ->assertSee('Hello World');
});

it('denies updating a post the user does not own', function (): void {
    $owner = $this->makeUser(['email' => 'owner@example.com']);
    $other = $this->makeUser(['email' => 'other@example.com']);
    $post = Post::query()->create(['title' => 'Owned', 'user_id' => $owner->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($other)
        ->tool($this->mcpTool('update_post'), ['id' => $post->id, 'title' => 'Hijacked'])
        ->assertHasErrors();

    expect($post->fresh()->title)->toBe('Owned');
});

it('allows the owner to update their own post', function (): void {
    $owner = $this->makeUser();
    $post = Post::query()->create(['title' => 'Mine', 'user_id' => $owner->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($owner)
        ->tool($this->mcpTool('update_post'), ['id' => $post->id, 'title' => 'Updated'])
        ->assertOk();

    expect($post->fresh()->title)->toBe('Updated');
});

it('denies deleting a post the user does not own', function (): void {
    $owner = $this->makeUser(['email' => 'owner@example.com']);
    $other = $this->makeUser(['email' => 'other@example.com']);
    $post = Post::query()->create(['title' => 'Keep', 'user_id' => $owner->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($other)
        ->tool($this->mcpTool('delete_post'), ['id' => $post->id])
        ->assertHasErrors();

    expect(Post::query()->find($post->id))->not->toBeNull();
});
