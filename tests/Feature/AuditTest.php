<?php

declare(strict_types=1);

use Blaze\ModelMcp\Audit\ToolCallEvent;
use Blaze\ModelMcp\Contracts\ToolAuditor;
use Blaze\ModelMcp\Server\ModelMcpServer;
use Blaze\ModelMcp\Tests\Fixtures\Models\Post;

beforeEach(function (): void {
    $this->recorded = new ArrayObject;

    $this->app->instance(ToolAuditor::class, new class($this->recorded) implements ToolAuditor
    {
        public function __construct(private ArrayObject $events) {}

        public function record(ToolCallEvent $event): void
        {
            $this->events[] = $event;
        }
    });
});

it('records an allowed outcome for a permitted call', function (): void {
    $user = $this->makeUser();
    Post::query()->create(['title' => 'Auditable', 'user_id' => $user->id, 'team_id' => 1]);

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_posts'), [])
        ->assertOk();

    $outcomes = array_map(fn (ToolCallEvent $e): string => $e->outcome, $this->recorded->getArrayCopy());

    expect($outcomes)->toContain(ToolCallEvent::OUTCOME_ALLOWED);
});

it('records a denied outcome with the acting user for a blocked call', function (): void {
    $user = $this->makeUser();

    ModelMcpServer::actingAs($user)
        ->tool($this->mcpTool('list_tags'), [])
        ->assertHasErrors();

    $denied = array_values(array_filter(
        $this->recorded->getArrayCopy(),
        fn (ToolCallEvent $e): bool => $e->outcome === ToolCallEvent::OUTCOME_DENIED,
    ));

    expect($denied)->not->toBeEmpty();
    expect($denied[0]->userId)->toEqual($user->id);
    expect($denied[0]->operation)->toBe('list');
});
