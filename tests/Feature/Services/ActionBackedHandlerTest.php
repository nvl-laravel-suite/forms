<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\MethodFormHandler;

test('action backed handler proxies execution through the container', function (): void {
    $form = Form::factory()->create();
    $request = Request::create('/forms/'.$form->id.'/submit', 'POST', ['subject' => 'Demo']);
    $action = new class
    {
        /** @var array<string, mixed> */
        public array $captured = [];

        /**
         * @return array<string, mixed>
         */
        public function handle(Form $form, array $data, array $context): array
        {
            $this->captured = ['form_id' => $form->id, 'payload' => $data, 'request_ip' => ($context['ipAddress'] ?? null)];

            return ['entry_id' => 'action-'.$data['subject'], 'meta' => ['handled_by' => 'handle']];
        }
    };
    $handler = new MethodFormHandler($action, 'handle');
    $result = $handler->handle($form, ['subject' => 'Demo'], formSubmissionContext($request)->toArray());
    expect($action->captured['form_id'])->toBe($form->id)->and($action->captured['payload'])->toMatchArray(['subject' => 'Demo'])->and($result)->toMatchArray(['entry_id' => 'action-Demo', 'meta' => ['handled_by' => 'handle']]);
});
test('action backed handler fails when action result is not an array', function (): void {
    $form = Form::factory()->create();
    $request = Request::create('/forms/'.$form->id.'/submit', 'POST');
    $action = new class
    {
        public function handle(Form $form, array $data, array $context): string
        {
            return 'invalid';
        }
    };
    $handler = new MethodFormHandler($action, 'handle');
    expect(fn () => $handler->handle($form, [], formSubmissionContext($request)->toArray()))->toThrow(LogicException::class, 'must return an array');
});
