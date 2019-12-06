<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    private $segmentGroupsRepository;

    private $segmentsRepository;

    public function __construct(
        SegmentGroupsRepository $segmentGroupsRepository,
        SegmentsRepository $segmentsRepository
    ) {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $userFields = 'users.id,users.email,users.first_name,users.last_name';

        $defaultGroup = $this->segmentGroupsRepository->load('Default group');

        $code = 'active_registered_users';
        if ($this->segmentsRepository->exists($code)) {
            $output->writeln("  * segment <info>$code</info> exists");
        } else {
            $query = 'SELECT %fields% FROM %table% WHERE %where% AND `active` = 1 AND deleted_at IS NULL GROUP BY %table%.id';
            $this->segmentsRepository->add('Počet aktívnych registrácií', 1, $code, 'users', $userFields, $query, $defaultGroup);
            $output->writeln("  <comment>* segment <info>$code</info> created</comment>");
        }
    }
}
