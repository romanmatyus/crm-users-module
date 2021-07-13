<?php

namespace Crm\UsersModule\Commands;

use Crm\UsersModule\Auth\Repository\AdminAccessRepository;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateAccessCommand extends Command
{
    private $adminAccessRepository;

    private $container;

    public function __construct(
        AdminAccessRepository $adminAccessRepository,
        Container $container
    ) {
        parent::__construct();
        $this->adminAccessRepository = $adminAccessRepository;
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setName('user:generate_access')
            ->setDescription('Generate all access data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->container->findByType('Crm\AdminModule\Presenters\AdminPresenter') as $adminServiceName) {
            try {
                $adminPresenterName = get_class($this->container->getService($adminServiceName));
                $output->writeln("Processing class <info>{$adminPresenterName}</info>");
                $this->processPresenterClass($adminPresenterName, $output);
            } catch (MissingServiceException $e) {
                $output->writeln("<error>Service {$adminServiceName} missing</error>");
            } catch (\Exception $e) {
                if (isset($adminPresenterName)) {
                    $output->writeln("<error>Unable to process presenter {$adminPresenterName}</error>");
                } else {
                    $output->writeln("<error>Unable to process service {$adminServiceName}</error>");
                }
                $output->writeln("<comment> - {$e->getMessage()}</comment>");
            }
        }

        return 0;
    }

    private function processPresenterClass($presenterClass, OutputInterface $output)
    {
        $parts = explode('\\', $presenterClass);
        $presenter = str_replace('Presenter', '', array_pop($parts));
        $next = array_pop($parts);
        if ($next === 'Presenters') {
            $next = array_pop($parts);
        }
        $module = str_replace('Module', '', $next);
        $resource = "{$module}:{$presenter}";

        // select which prefixes
        $methodPrefixes = ['render', 'action', 'handle'];
        foreach (get_class_methods($presenterClass) as $methodName) {
            foreach ($methodPrefixes as $methodPrefix) {
                if (substr($methodName, 0, strlen($methodPrefix)) !== $methodPrefix) {
                    continue;
                }

                // parse resource & load access level from annotation
                $action = str_replace($methodPrefix, '', $methodName);
                $action = lcfirst($action);
                $accessLevel = \Nette\Reflection\Method::from($presenterClass, $methodName)
                    ->getAnnotation('admin-access-level');
                if (!in_array($accessLevel, [null, 'write', 'read'], true)) {
                    $output->writeln(
                        " * <error>ACL resource </error><fg=red;bg=white;options=bold> {$resource}:{$action} </><error>" .
                        " has incorrect access level <fg=red;bg=white;options=bold>[{$accessLevel}]</>.</error>\n" .
                        "   <error>Only read, write and null are allowed. Null will be used instead.</error>"
                    );
                    $accessLevel = null;
                }

                // add / update access resource
                $adminAccess = $this->adminAccessRepository->findByResourceAndAction($resource, $action);
                if (!$adminAccess) {
                    $this->adminAccessRepository->add($resource, $action, $methodPrefix, $accessLevel);
                    $output->writeln(" <comment>* ACL resource <info>{$resource}:{$action}</info> was created</comment>");
                } else {
                    $updateData = [];
                    if ($adminAccess['type'] !== $methodPrefix) {
                        $updateData['type'] = $methodPrefix;
                    }
                    if ($adminAccess['level'] !== $accessLevel) {
                        $updateData['level'] = $accessLevel;
                    }

                    if (!empty($updateData)) {
                        $this->adminAccessRepository->update($adminAccess, $updateData);
                        $output->writeln(" <comment>* ACL resource <info>{$resource}:{$action}</info> updated. Changes:</comment>");
                        foreach ($updateData as $key => $value) {
                            $output->writeln("\t- <comment>[{$key}]</comment> => <info>{$value}</info>");
                        }
                    }
                }
            }
        }
    }
}
