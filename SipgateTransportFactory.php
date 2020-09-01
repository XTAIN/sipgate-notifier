<?php

namespace XTAIN\Notifier\Bridge\Sipgate;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @experimental in 5.1
 */
final class SipgateTransportFactory extends AbstractTransportFactory
{
    /**
     * @return SipgateTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $user = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $smsId = $dsn->getOption('smsId');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        if ('sipgate' === $scheme) {
            return (new SipgateTransport($user, $password, $smsId, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
        }

        throw new UnsupportedSchemeException($dsn, 'sipgate', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['sipgate'];
    }
}
