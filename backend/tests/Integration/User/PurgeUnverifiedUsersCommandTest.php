<?php

declare(strict_types=1);

namespace App\Tests\Integration\User;

use App\User\Application\Command\PurgeUnverifiedUsersCommand;
use App\User\Domain\Entity\ResetPasswordRequest;
use App\User\Domain\Entity\User;
use App\User\Domain\Enum\Role;
use App\User\Infrastructure\Doctrine\DoctrineUserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
#[Group('user')]
class PurgeUnverifiedUsersCommandTest extends KernelTestCase
{
    private DoctrineUserRepository $repository;
    private EntityManagerInterface $em;
    private Connection $connection;
    private CommandTester $tester;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var DoctrineUserRepository $repository */
        $repository = static::getContainer()->get(DoctrineUserRepository::class);
        $this->repository = $repository;

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        $this->connection = $em->getConnection();
        $this->connection->beginTransaction();

        /** @var PurgeUnverifiedUsersCommand $command */
        $command = static::getContainer()->get(PurgeUnverifiedUsersCommand::class);
        $this->tester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function it_deletes_only_old_unverified_accounts_and_cascades_related_data(): void
    {
        $stale = $this->createUser('stale@purge.example.com', age: '10 days');
        $staleId = (string) $stale->getId();
        $verified = $this->createUser('verified@purge.example.com', age: '10 days');
        $verified->verify();
        $recent = $this->createUser('recent@purge.example.com', age: '1 day');
        $admin = $this->createUser('admin@purge.example.com', age: '10 days');
        $admin->setRoles([Role::ROLE_ADMIN->value]);

        $this->em->persist(new ResetPasswordRequest($stale, str_repeat('a', 64), new \DateTimeImmutable('+1 hour')));
        $this->em->flush();

        $exitCode = $this->tester->execute(['--older-than' => '7 days', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('account(s) deleted', $this->tester->getDisplay());

        $this->em->clear();
        $this->assertNull($this->repository->findByEmail('stale@purge.example.com'));
        $this->assertNotNull($this->repository->findByEmail('verified@purge.example.com'));
        $this->assertNotNull($this->repository->findByEmail('recent@purge.example.com'));
        $this->assertNotNull($this->repository->findByEmail('admin@purge.example.com'));

        $remainingResetRequests = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM password_reset_requests WHERE user_id = :id',
            ['id' => $staleId],
        );
        $this->assertSame(0, $remainingResetRequests, 'Reset password requests must cascade with the deleted user.');
    }

    #[Test]
    public function it_refuses_to_run_when_email_verification_is_disabled(): void
    {
        $this->createUser('stale@purge.example.com', age: '10 days');

        $exitCode = $this->tester->execute(['--older-than' => '7 days']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('REQUIRE_EMAIL_VERIFICATION=false', $this->tester->getDisplay());

        $this->em->clear();
        $this->assertNotNull($this->repository->findByEmail('stale@purge.example.com'));
    }

    #[Test]
    public function it_defaults_to_a_dry_run_without_deleting(): void
    {
        $this->createUser('stale@purge.example.com', age: '10 days');

        $exitCode = $this->tester->execute(['--older-than' => '7 days', '--dry-run' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Dry run', $this->tester->getDisplay());

        $this->em->clear();
        $this->assertNotNull($this->repository->findByEmail('stale@purge.example.com'));
    }

    #[Test]
    public function it_supports_a_minute_precision_threshold(): void
    {
        $this->createUser('old@purge.example.com', age: '10 minutes');
        $this->createUser('fresh@purge.example.com', age: '1 minute');

        $exitCode = $this->tester->execute(['--older-than' => '5 minutes', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $this->em->clear();
        $this->assertNull($this->repository->findByEmail('old@purge.example.com'));
        $this->assertNotNull(
            $this->repository->findByEmail('fresh@purge.example.com'),
            'Accounts created less than 5 minutes ago must be kept.',
        );
    }

    #[Test]
    public function it_defaults_to_a_five_minute_threshold(): void
    {
        $this->createUser('old@purge.example.com', age: '10 minutes');
        $this->createUser('fresh@purge.example.com', age: '1 minute');

        $exitCode = $this->tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $this->em->clear();
        $this->assertNull($this->repository->findByEmail('old@purge.example.com'));
        $this->assertNotNull($this->repository->findByEmail('fresh@purge.example.com'));
    }

    #[Test]
    public function it_rejects_an_invalid_duration(): void
    {
        $exitCode = $this->tester->execute(['--older-than' => 'not-a-duration', '--force' => true]);

        $this->assertSame(Command::INVALID, $exitCode);
    }

    private function createUser(string $email, string $age): User
    {
        $user = User::register($email, 'hashed', 'John', 'Doe');
        $this->repository->save($user);
        $this->em->flush();

        $property = new \ReflectionProperty(User::class, 'createdAt');
        $property->setValue($user, new \DateTimeImmutable(sprintf('-%s', $age)));
        $this->em->flush();

        return $user;
    }
}
