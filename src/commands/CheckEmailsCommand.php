<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Email\EmailValidator;
use Crm\UsersModule\Repository\UsersRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckEmailsCommand extends Command
{
    private $userRepository;

    private $emailValidator;

    public function __construct(
        UsersRepository $userRepository,
        EmailValidator $emailValidator
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->emailValidator = $emailValidator;
    }

    protected function configure()
    {
        $this->setName('user:check-emails')
            ->setDescription('Validate emails')
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'User email'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('email')) {
            $output->write("Checking email: <comment>{$input->getArgument('email')}</comment> ... ");
            $result = $this->emailValidator->isValid($input->getArgument('email'));
            if ($result) {
                $output->writeln("<info>VALID</info>");
            } else {
                $validator = get_class($this->emailValidator->lastValidator());
                $output->writeln("<error>INVALID</error> - ({$validator})");
            }
            return;
        }

        // todo - doplnit validaciu vsetkych emailov
    }
}
