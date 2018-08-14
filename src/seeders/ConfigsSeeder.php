<?php

namespace Crm\UsersModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;
    
    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->configCategoriesRepository->loadByName('Všeobecne');
        if (!$category) {
            $category = $this->configCategoriesRepository->add('Všeobecne', 'fa fa-globe', 100);
            $output->writeln('  <comment>* config category <info>Všeobecne</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Všeobecne</info> exists');
        }

        $name = 'not_logged_in_route';
        $value = ':Users:Sign:In';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Stránka pre neprihlásených')
                ->setDescription('Nette routa, na ktorú má byť používateľ presmerovaný pri návšteve URL, ktorá je dostupná len pre prihlásených používateľov')
                ->setValue($value)
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(262)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } elseif ($config->has_default_value && $config->value !== $value) {
            $this->configsRepository->update($config, ['value' => $value, 'has_default_value' => true]);
            $output->writeln("  <comment>* config item <info>$name</info> updated</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }
    }
}
