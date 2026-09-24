<?php

declare(strict_types=1);

namespace App\Auth\Domain\Contract;

use App\Auth\Domain\Exception\OAuth2NotConfiguredException;

interface OAuth2ClientCheckerInterface
{
    /**
     * Vérifie qu'au moins un client OAuth2 actif est configuré en base.
     *
     * @throws OAuth2NotConfiguredException si aucun client OAuth2 actif n'existe
     */
    public function assertAtLeastOneActiveClient(): void;
}