<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Crm\UsersModule\Auth\Repository\AdminGroupsRepository;
use Crm\UsersModule\Builder\UserBuilder;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Output\OutputInterface;

class UsersSeeder implements ISeeder
{
    private $userBuilder;

    private $usersRepository;

    private $adminGroupsRepository;

    private $adminAccessRepository;

    public function __construct(
        UserBuilder $userBuilder,
        UsersRepository $usersRepository,
        AdminGroupsRepository $adminGroupsRepository,
        AdminAccessRepository $adminAccessRepository
    ) {
        $this->userBuilder = $userBuilder;
        $this->usersRepository = $usersRepository;
        $this->adminGroupsRepository = $adminGroupsRepository;
        $this->adminAccessRepository = $adminAccessRepository;
    }

    public function seed(OutputInterface $output)
    {
        $name = 'superadmin';

        $superGroup = $this->adminGroupsRepository->findByName($name);
        if (!$superGroup) {
            $superGroup = $this->adminGroupsRepository->add($name);
            $output->writeln("  <comment>* admin group <info>{$name}</info> created</comment>");
        } else {
            $output->writeln("  * admin group <info>{$name}</info> exists");
        }

        $accesses = $this->adminAccessRepository->all();
        foreach ($accesses as $access) {
            if ($superGroup->related('admin_groups_access')->where(['admin_group_id' => $superGroup->id, 'admin_access_id' => $access->id])->count('*') == 0) {
                $superGroup->related('admin_groups_access')->insert([
                    'admin_group_id' => $superGroup->id,
                    'admin_access_id' => $access->id,
                    'created_at' => new DateTime(),
                ]);
            }
        }

        $user = $this->userBuilder->createNew()
            ->setEmail('admin@admin.sk')
            ->setPassword('password')
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setPublicName('admin@admin.sk')
            ->setAddTokenOption(false)
            ->setRole(UsersRepository::ROLE_ADMIN)
            ->save();

        if (!$user) {
            $output->writeln('  * user <info>admin@admin.sk</info> exists');
            $user = $this->usersRepository->getByEmail('admin@admin.sk');
        } else {
            $output->writeln('  <comment>* user <info>admin@admin.sk</info> created</comment>');
        }

        if (!$user->related('admin_user_groups')->where(['admin_group_id' => $superGroup->id])->count('*')) {
            $user->related('admin_user_groups')->insert([
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
                'admin_group_id' => $superGroup->id,
                'user_id' => $user->id,
            ]);
        }

        $user = $this->userBuilder->createNew()
            ->setEmail('user@user.sk')
            ->setPassword('password')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPublicName('admin@admin.sk')
            ->setAddTokenOption(false)
            ->save();
        if (!$user) {
            $output->writeln('  * user <info>user@user.sk</info> exists');
        } else {
            $output->writeln('  <comment>* user <info>user@user.sk</info> created</comment>');
        }
    }
}
