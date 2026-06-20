<?php

declare(strict_types=1);

it('names every tool in snake_case using the verb_model scheme', function (): void {
    expect($this->mcpTool('list_posts')->name())->toBe('list_posts');
    expect($this->mcpTool('get_post')->name())->toBe('get_post');
    expect($this->mcpTool('create_post')->name())->toBe('create_post');
    expect($this->mcpTool('update_post')->name())->toBe('update_post');
    expect($this->mcpTool('delete_post')->name())->toBe('delete_post');
    expect($this->mcpTool('search_posts')->name())->toBe('search_posts');
});

it('tags each tool with the correct behavior annotation', function (): void {
    expect($this->mcpTool('list_posts')->toArray()['annotations'])->toHaveKey('readOnlyHint');
    expect($this->mcpTool('get_post')->toArray()['annotations'])->toHaveKey('readOnlyHint');
    expect($this->mcpTool('search_posts')->toArray()['annotations'])->toHaveKey('readOnlyHint');
    expect($this->mcpTool('update_post')->toArray()['annotations'])->toHaveKey('idempotentHint');

    $delete = $this->mcpTool('delete_post')->toArray()['annotations'];
    expect($delete)->toHaveKey('destructiveHint')->toHaveKey('idempotentHint');
});

it('builds the create schema from fillable and requires non-nullable columns', function (): void {
    $schema = $this->mcpTool('create_post')->toArray()['inputSchema'];

    expect($schema['properties'])->toHaveKeys(['title', 'body', 'status', 'published']);
    expect($schema['required'] ?? [])->toContain('title');
    expect($schema['required'] ?? [])->not->toContain('body');
});

it('maps an enum cast onto an enum schema', function (): void {
    $status = $this->mcpTool('create_post')->toArray()['inputSchema']['properties']['status'];

    expect($status)->toHaveKey('enum');
    expect($status['enum'])->toContain('draft', 'published', 'archived');
});

it('maps a boolean cast onto a boolean schema', function (): void {
    $published = $this->mcpTool('create_post')->toArray()['inputSchema']['properties']['published'];

    expect($published['type'])->toBe('boolean');
});

it('never exposes the primary key or tenant column as writable input', function (): void {
    $properties = $this->mcpTool('create_post')->toArray()['inputSchema']['properties'];

    expect($properties)->not->toHaveKey('id');
    expect($properties)->not->toHaveKey('team_id');
});

it('caps per_page in the list schema at the configured maximum', function (): void {
    $perPage = $this->mcpTool('list_posts')->toArray()['inputSchema']['properties']['per_page'];

    expect($perPage['maximum'])->toBe(100);
    expect($perPage['minimum'])->toBe(1);
});

it('requires a query argument on the search tool', function (): void {
    $schema = $this->mcpTool('search_posts')->toArray()['inputSchema'];

    expect($schema['required'] ?? [])->toContain('q');
});

it('exposes the identifier as an "id" argument on get/update/delete', function (): void {
    foreach (['get_post', 'update_post', 'delete_post'] as $name) {
        $schema = $this->mcpTool($name)->toArray()['inputSchema'];

        expect($schema['properties'])->toHaveKey('id');
        expect($schema['properties'])->not->toHaveKey('0');
        expect($schema['required'] ?? [])->toContain('id');
    }
});
