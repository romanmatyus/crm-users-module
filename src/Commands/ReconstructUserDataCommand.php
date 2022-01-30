<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UserData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReconstructUserDataCommand extends Command
{
    private $usersRepository;

    private $userData;

    public function __construct(
        UsersRepository $usersRepository,
        UserData $userData
    ) {
        parent::__construct();
        $this->usersRepository = $usersRepository;
        $this->userData = $userData;
    }

    protected function configure()
    {
        $this->setName('user:reconstruct_user_data')
            ->setDescription('Reconstruct user data (Redis) cache based on the actual data')
            ->addOption(
                'user_ids',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'User IDs to refresh. If not provided, all users are refreshed.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Reconstructing user data');
        $output->writeln('');

        $usersQuery = $this->usersRepository->getTable()
            ->where(['active' => true])
            ->where(':access_tokens.id IS NOT NULL')
            ->order('id ASC');

        $userIdsFilter = $input->getOption('user_ids');
        if (count($userIdsFilter)) {
            $usersQuery->where('users.id IN (?)', $userIdsFilter);
        }

        $totalUsers = (clone $usersQuery)->count('*');
        $progress = new ProgressBar($output, $totalUsers);
        $progress->setFormat('debug');
        $progress->start();

        $step = 100;
        $lastId = 0;

        do {
            $processed = 0;
            $users = (clone $usersQuery)
                ->where('users.id > ?', $lastId)
                ->limit($step);

            foreach ($users as $user) {
                $this->userData->refreshUserTokens($user->id);
                $processed++;
                $lastId = $user->id;

                $progress->advance();
            }
        } while ($processed > 0);

        $progress->finish();
        $output->writeln('');
        return Command::SUCCESS;
    }
}
