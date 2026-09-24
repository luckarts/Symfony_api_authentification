<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\OAuth2;

use App\Auth\Domain\Contract\OAuth2ClientCheckerInterface;
use App\Auth\Domain\Exception\OAuth2NotConfiguredException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

final class DoctrineOAuth2ClientChecker implements OAuth2ClientCheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function assertAtLeastOneActiveClient(): void
    {
        try {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(1) FROM oauth2_client WHERE active = :active',
                ['active' => true],
            );
        } catch (DBALException) {
            throw OAuth2NotConfiguredException::noActiveClient();
        }

        if ($count < 1) {
            throw OAuth2NotConfiguredException::noActiveClient();
        }
    }
}