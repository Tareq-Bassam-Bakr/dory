<?php

namespace App\Services\Push;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\SenderIdMismatch;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Firebase Cloud Messaging HTTP v1 through the Firebase Admin SDK
 * (kreait/firebase-php). Credentials: the service account file named by
 * FIREBASE_CREDENTIALS, outside the repository (docs/FIREBASE_SETUP.md).
 *
 * The message carries a visible notification (so Android shows it while the
 * app is in the background) and data with the kind and public ids, which
 * the app uses to open the right screen.
 */
final class FirebasePushSender implements PushSender
{
    public function __construct(
        private readonly Messaging $messaging,
        private readonly string $androidChannelId,
    ) {}

    public function send(PushMessage $message): PushOutcome
    {
        $cloudMessage = CloudMessage::new()
            ->toToken($message->token)
            ->withNotification(['title' => $message->title, 'body' => $message->body])
            ->withData($message->data)
            ->withAndroidConfig([
                'priority' => $message->urgent ? 'high' : 'normal',
                'notification' => [
                    'channel_id' => $this->androidChannelId,
                    // A newer update about the same ticket replaces the older one.
                    'tag' => $message->data['ticket'] ?? $message->data['kind'] ?? 'dory',
                ],
            ])
            ->withApnsConfig([
                'headers' => ['apns-priority' => $message->urgent ? '10' : '5'],
                'payload' => ['aps' => ['sound' => 'default']],
            ]);

        try {
            $this->messaging->send($cloudMessage);

            return PushOutcome::Accepted;
        } catch (NotFound|SenderIdMismatch) {
            // The token was deleted, expired, or belongs to another project.
            return PushOutcome::InvalidToken;
        } catch (InvalidMessage $e) {
            return str_contains(strtolower($e->getMessage()), 'registration token')
                ? PushOutcome::InvalidToken
                : PushOutcome::Failed;
        } catch (ServerError|ServerUnavailable|QuotaExceeded|ApiConnectionFailed) {
            return PushOutcome::Retry;
        } catch (MessagingException) {
            return PushOutcome::Failed;
        }
    }
}
