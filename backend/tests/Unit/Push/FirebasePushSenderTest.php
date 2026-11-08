<?php

namespace Tests\Unit\Push;

use App\Services\Push\DisabledPushSender;
use App\Services\Push\FirebasePushSender;
use App\Services\Push\PushMessage;
use App\Services\Push\PushOutcome;
use App\Services\Push\PushSender;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use Tests\TestCase;
use Throwable;

/**
 * FCM answers mapped to what DORY does with them, without calling Firebase.
 */
class FirebasePushSenderTest extends TestCase
{
    private function message(): PushMessage
    {
        return new PushMessage('device-token', 'Title', 'Body', ['kind' => 'ticket_called', 'ticket' => 'T1'], true);
    }

    private function senderThrowing(?Throwable $error): FirebasePushSender
    {
        $messaging = Mockery::mock(Messaging::class);
        $expectation = $messaging->shouldReceive('send')->once();
        $error === null ? $expectation->andReturn([]) : $expectation->andThrow($error);

        return new FirebasePushSender($messaging, 'dory_queue_updates');
    }

    public function test_accepted_messages(): void
    {
        $this->assertSame(PushOutcome::Accepted, $this->senderThrowing(null)->send($this->message()));
    }

    public function test_unknown_tokens_are_invalid(): void
    {
        $this->assertSame(PushOutcome::InvalidToken, $this->senderThrowing(new NotFound('Requested entity was not found.'))->send($this->message()));
        $this->assertSame(
            PushOutcome::InvalidToken,
            $this->senderThrowing(new InvalidMessage('The registration token is not a valid FCM registration token'))->send($this->message()),
        );
    }

    public function test_temporary_failures_are_retried_and_other_refusals_are_not(): void
    {
        $this->assertSame(PushOutcome::Retry, $this->senderThrowing(new ServerUnavailable('Unavailable'))->send($this->message()));
        $this->assertSame(PushOutcome::Retry, $this->senderThrowing(new ApiConnectionFailed('Timeout'))->send($this->message()));
        $this->assertSame(PushOutcome::Failed, $this->senderThrowing(new InvalidMessage('Invalid JSON payload'))->send($this->message()));
    }

    public function test_the_message_targets_the_token_with_the_dory_channel(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->withArgs(function (CloudMessage $message): bool {
            $payload = $message->jsonSerialize();

            return $payload['token'] === 'device-token'
                && $payload['notification']['title'] === 'Title'
                && $payload['data'] === ['kind' => 'ticket_called', 'ticket' => 'T1']
                && $payload['android']['priority'] === 'high'
                && $payload['android']['notification']['channel_id'] === 'dory_queue_updates';
        })->andReturn([]);

        $this->assertSame(PushOutcome::Accepted, (new FirebasePushSender($messaging, 'dory_queue_updates'))->send($this->message()));
    }

    public function test_push_stays_off_without_the_switch_or_the_credentials_file(): void
    {
        config(['dory.push.enabled' => false]);
        $this->app->forgetInstance(PushSender::class);
        $this->assertInstanceOf(DisabledPushSender::class, $this->app->make(PushSender::class));

        config(['dory.push.enabled' => true, 'firebase.projects.app.credentials' => base_path('missing-service-account.json')]);
        $this->app->forgetInstance(PushSender::class);
        $this->assertInstanceOf(DisabledPushSender::class, $this->app->make(PushSender::class));
        $this->assertSame(PushOutcome::Skipped, $this->app->make(PushSender::class)->send($this->message()));
    }
}
