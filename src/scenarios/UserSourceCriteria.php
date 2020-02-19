<?php

namespace Crm\UsersModule\Scenarios;

use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\Selection;

class UserSourceCriteria implements ScenariosCriteriaInterface
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public function params(): array
    {
        $sources = $this->usersRepository->getUserSources();

        // Allow entering arbitrary values (free-solo mode)
        return [
            new StringLabeledArrayParam('source', 'Source', $sources, 'or', true),
        ];
    }

    public function addCondition(Selection $selection, $key, $values)
    {
        $selection->where('users.source IN (?)', $values->selection);
    }

    public function label(): string
    {
        return 'Source';
    }
}
