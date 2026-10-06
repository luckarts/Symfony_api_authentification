<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Service;

use App\User\Application\Service\PurgeUnverifiedUsersService;
use App\User\Domain\Contract\UserCredentialsRevokerInterface;
use App\User\Domain\Contract\UserRepositoryInterface;
use App\User\Domain\Entity\User;
use App\User\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;

#[Group('unit')]
#[Group('user')]
class PurgeUnverifiedUsersServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private UserCredentialsRevokerInterface&MockObject $credentialsRevoker;
    private EntityManagerInterface&MockObject $entityManager;
    private PurgeUnverifiedUsersService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->credentialsRevoker = $this->createMock(UserCredentialsRevokerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15T12:00:00+00:00'));

        $this->service = new PurgeUnverifiedUsersService(
            $this->userRepository,
            $this->credentialsRevoker,
            $this->entityManager,
            $clock,
        );
    }

    #[Test]
    public function it_revokes_credentials_and_removes_unverified_users(): void
    {
        $user = User::register('stale@example.com', 'hashed', 'John', 'Doe');

        $this->userRepository->expects($this->once())
            ->method('findUnverifiedBefore')
            ->with(
                new \DateTimeImmutable('2026-01-08T12:00:00+00:00'),
                500,
            )
            ->willReturn([$user]);

        $this->credentialsRevoker->expects($this->once())
            ->method('revokeAll')
            ->with($user);

        $this->userRepository->expects($this->once())
            ->method('remove')
            ->with($user);

        $this->entityManager->expects($this->once())->method('flush');

        $report = $this->service->purge(new \DateInterval('P7D'), 500, dryRun: false);

        $this->assertSame(1, $report->candidates);
        $this->assertSame(1, $report->purgedCount());
        $this->assertSame(['stale@example.com'], $report->purgedEmails);
        $this->assertSame(0, $report->skippedCount());
    }

    #[Test]
    public function it_never_deletes_protected_admin_accounts(): void
    {
        $admin = User::register('admin@example.com', 'hashed', 'Ada', 'Min');
        $admin->setRoles([Role::ROLE_ADMIN->value]);

        $superAdmin = User::register('super@example.com', 'hashed', 'Root', 'Admin');
        $superAdmin->setRoles([Role::ROLE_SUPER_ADMIN->value]);

        $regular = User::register('regular@example.com', 'hashed', 'John', 'Doe');

        $this->userRepository->method('findUnverifiedBefore')->willReturn([$admin, $superAdmin, $regular]);

        $this->credentialsRevoker->expects($this->once())
            ->method('revokeAll')
            ->with($regular);

        $this->userRepository->expects($this->once())
            ->method('remove')
            ->with($regular);

        $report = $this->service->purge(new \DateInterval('P7D'), 500, dryRun: false);

        $this->assertSame(3, $report->candidates);
        $this->assertSame(1, $report->purgedCount());
        $this->assertSame(['regular@example.com'], $report->purgedEmails);
        $this->assertSame(['admin@example.com', 'super@example.com'], $report->skippedEmails);
    }

    #[Test]
    public function dry_run_reports_candidates_without_touching_anything(): void
    {
        $user = User::register('stale@example.com', 'hashed', 'John', 'Doe');

        $this->userRepository->method('findUnverifiedBefore')->willReturn([$user]);

        $this->credentialsRevoker->expects($this->never())->method('revokeAll');
        $this->userRepository->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $report = $this->service->purge(new \DateInterval('P7D'), 500, dryRun: true);

        $this->assertSame(1, $report->candidates);
        $this->assertSame(1, $report->purgedCount());
        $this->assertSame(['stale@example.com'], $report->purgedEmails);
    }

    #[Test]
    public function it_does_not_flush_when_there_is_nothing_to_delete(): void
    {
        $this->userRepository->method('findUnverifiedBefore')->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $report = $this->service->purge(new \DateInterval('P7D'), 500, dryRun: false);

        $this->assertSame(0, $report->candidates);
        $this->assertSame(0, $report->purgedCount());
    }

    #[Test]
    public function it_uses_a_minute_precision_threshold(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findUnverifiedBefore')
            ->with(
                new \DateTimeImmutable('2026-01-15T11:55:00+00:00'),
                500,
            )
            ->willReturn([]);

        $report = $this->service->purge(new \DateInterval('PT5M'), 500, dryRun: true);

        $this->assertSame(
            '2026-01-15T11:55:00+00:00',
            $report->before->format(\DateTimeInterface::ATOM),
        );
    }

    #[Test]
    public function it_rejects_a_non_positive_duration(): void
    {
        $this->userRepository->expects($this->never())->method('findUnverifiedBefore');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->purge(new \DateInterval('PT0S'), 500, dryRun: true);
    }
}
