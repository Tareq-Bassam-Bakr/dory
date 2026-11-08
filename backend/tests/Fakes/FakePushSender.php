<?php

namespace Tests\Fakes;

use App\Services\Push\PushMessage;
use App\Services\Push\PushOutcome;
use App\Services\Push\PushSender;

/**
 * Records push messages instead of calling FCM, and answers with a chosen
 * outcome. Live FCM delivery is tested separately with a real project.
 */
final class FakePushSender implements PushSender
{
    /**
     * @var list<PushMessage>
     */
    public array $sent = [];

    public function __construct(public PushOutcome $outcome = PushOutcome::Accepted) {}

    public function send(PushMessage $message): PushOutcome
    {
        $this->sent[] = $message;

        return $this->outcome;
    }
}
