<?php

declare(strict_types=1);

namespace App\User\Application\Dto;

final readonly class PurgeReport
{
    /**
     * @param list<string> $purgedEmails
     * @param list<string> $skippedEmails
     */
    public function __construct(
        public \DateTimeImmutable $before,
        public int $candidates,
        public array $purgedEmails,
        public array $skippedEmails,
    ) {
    }

    public function purgedCount(): int
    {
        return count($this->purgedEmails);
    }

    public function skippedCount(): int
    {
        return count($this->skippedEmails);
    }
}
