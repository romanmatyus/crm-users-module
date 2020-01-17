<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Device;
use Sinergi\BrowserDetector\Os;
use Sinergi\BrowserDetector\UserAgent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateLoginAttemptsCommand extends Command
{
    private $loginAttemptsRepository;

    public function __construct(LoginAttemptsRepository $loginAttemptsRepository)
    {
        parent::__construct();
        $this->loginAttemptsRepository = $loginAttemptsRepository;
    }

    protected function configure()
    {
        $this->setName('user:fix-login-attempts')
            ->setDescription('Update login attempts browsers')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $limit = 100;
        $updated = 0;
        $lastId = 0;
        while (true) {
            $attempts = $this->loginAttemptsRepository->all()->where(['id > ?' => $lastId])->limit($limit);
            $found = false;
            foreach ($attempts as $attempt) {
                $lastId = $attempt->id;
                $found = true;

                $ua = new UserAgent($attempt->user_agent);
                $o = new Os($ua);
                $d = new Device($ua);
                $b = new Browser($ua);

                $isMobile = $o->isMobile();
                $browser = $b->getName();
                $browserVersion = $b->getVersion();
                $os = $o->getName();
                $device = $d->getName();

                $this->loginAttemptsRepository->update($attempt, [
                    'browser' => $browser,
                    'browser_version' => $browserVersion,
                    'os' => $os,
                    'device' => $device,
                    'is_mobile' => $isMobile,
                ]);
                $updated++;
            }

            $output->writeln("Updated <info>{$updated}</info> attempts");

            if (!$found) {
                break;
            }
        }

        return 0;
    }
}
