<?php

namespace XTAIN\Notifier\Bridge\Sipgate;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental in 5.1
 */
final class SipgateTransport extends AbstractTransport
{
    protected const HOST = 'api.sipgate.com';

    private $username;
    private $password;
    private $smsId;

    public function __construct(string $username, string $password, string $smsId = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->smsId = $smsId;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('sipgate://%s?smsId=%s', $this->getEndpoint(), $this->smsId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function findSmsId() : ?string
    {
        $endpoint = sprintf('https://%s/v1/authorization/userinfo', $this->getEndpoint());
        $response = $this->client->request('GET', $endpoint, [
            'auth_basic' => [$this->username, $this->password]
        ]);

        $user = $response->toArray();

        $endpoint = sprintf('https://%s/v2/%s/sms', $this->getEndpoint(), $user['sub']);
        $response = $this->client->request('GET', $endpoint, [
            'auth_basic' => [$this->username, $this->password]
        ]);

        $smsIds = $response->toArray();

        if (!empty($smsIds['items'])) {
            return $this->smsId = $smsIds['items'][0]['id'];
        }

        return null;
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, get_debug_type($message)));
        }

        $smsId = $this->smsId;
        if (empty($this->smsId)) {
            $smsId = $this->findSmsId();
        }

        $endpoint = sprintf('https://%s/v2/sessions/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => [$this->username, $this->password],
            'json' => [
                'smsId' => $smsId,
                'recipient' => $message->getPhone(),
                'message' => $message->getSubject(),
            ],
        ]);

        if (204 !== $response->getStatusCode()) {
            throw new TransportException('Unable to send the SMS: '.$response->getStatusCode(), $response);
        }
    }
}
