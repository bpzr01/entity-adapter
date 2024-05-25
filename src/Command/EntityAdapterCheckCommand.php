<?php

declare(strict_types=1);

namespace Bpzr\EntityAdapter\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EntityAdapterCheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('entity-adapter:check')
            ->setDescription('Validates entities')
            ->setHelp('Ensures entities are valid compared to DB tables')
            ->addArgument(
                'project_dir',
                InputArgument::REQUIRED,
                'Project root directory',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directoryPath = $input->getArgument('project_dir');
        $output->writeln($directoryPath);

        return Command::SUCCESS;
    }
}