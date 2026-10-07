<?php

declare(strict_types=1);

namespace App\User\Application\Command;

use App\User\Application\Service\PurgeUnverifiedUsersService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:users:purge-unverified',
    description: 'Delete user accounts that never verified their email address',
)]
final class PurgeUnverifiedUsersCommand extends Command
{
    public function __construct(
        private readonly PurgeUnverifiedUsersService $purgeService,
        #[Autowire(param: 'app.require_email_verification')]
        private readonly bool $requireEmailVerification,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Minimum account age as a duration (e.g. "5 minutes", "7 days")', '5 minutes')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of accounts to process', '500')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete the accounts (default is a dry run)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only, never delete (overrides --force)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $olderThan = (string) $input->getOption('older-than');
        $limit = (int) $input->getOption('limit');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run') || !$force;

        try {
            $minAge = \DateInterval::createFromDateString($olderThan);
        } catch (\DateMalformedIntervalStringException) {
            $minAge = false;
        }

        if (false === $minAge) {
            $io->error(sprintf('Invalid --older-than duration "%s".', $olderThan));

            return Command::INVALID;
        }

        if ($limit <= 0) {
            $io->error('The --limit option must be a positive integer.');

            return Command::INVALID;
        }

        if (!$this->requireEmailVerification && !$force) {
            $io->error(
                'Email verification is disabled in this environment (REQUIRE_EMAIL_VERIFICATION=false). '
                . 'Purging unverified accounts here would delete every regular user. Use --force to override.',
            );

            return Command::FAILURE;
        }

        try {
            $report = $this->purgeService->purge($minAge, $limit, $dryRun);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $io->table(
            ['Metric', 'Value'],
            [
                ['Threshold (created before)', $report->before->format(\DateTimeInterface::ATOM)],
                ['Candidates found', (string) $report->candidates],
                [$dryRun ? 'Would be deleted' : 'Deleted', (string) $report->purgedCount()],
                ['Skipped (protected admins)', (string) $report->skippedCount()],
            ],
        );

        if ([] !== $report->skippedEmails) {
            $io->note(sprintf(
                'Skipped protected accounts: %s',
                implode(', ', $report->skippedEmails),
            ));
        }

        if ($dryRun) {
            $io->warning('Dry run: nothing was deleted. Re-run with --force to apply.');
        } else {
            $io->success(sprintf('%d account(s) deleted.', $report->purgedCount()));
        }

        return Command::SUCCESS;
    }
}
