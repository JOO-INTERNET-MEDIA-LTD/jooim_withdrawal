<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Command;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MaintenanceCommand extends Command
{
    protected static $defaultName = 'jooim:withdrawal:maintenance';

    protected function configure(): void
    {
        $this
            ->setName('jooim:withdrawal:maintenance')
            ->setDescription('Run JOOIM Withdrawal technical cleanup and acknowledgement retry jobs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = \Module::getInstanceByName('jooim_withdrawal');
        if (!$module || !\Validate::isLoadedObject($module)) {
            $output->writeln('<error>jooim_withdrawal is not installed or cannot be loaded.</error>');
            return 1;
        }

        try {
            $result = ServiceContainer::get($module)->maintenance()->run();
            $output->writeln('<info>JOOIM Withdrawal maintenance completed.</info>');
            foreach ($result as $key => $value) {
                $output->writeln(sprintf('%s: %s', (string) $key, (string) $value));
            }
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</error>');
            return 1;
        }
    }
}
