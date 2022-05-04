<?php

declare(strict_types=1);

namespace CustomGento\CliTaxEditor\Console\Command\Tax\Rates;

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EditCommand extends Command
{
    private const OPTION_UPDATE_TITLES = 'update-titles';
    private const OPTION_IDS = 'ids';
    private const OPTION_RATE = 'rate';

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;

    public function __construct(TaxRateRepositoryInterface $taxRateRepository)
    {
        parent::__construct();
        $this->taxRateRepository = $taxRateRepository;
    }

    protected function configure(): void
    {
        $this
            ->setName('tax:rates:edit')
            ->setDescription('Updates the rate of existing tax rates.')
            ->addOption(
                self::OPTION_IDS,
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of tax rate IDs.'
            )
            ->addOption(
                self::OPTION_RATE,
                null,
                InputOption::VALUE_REQUIRED,
                'The new rate.'
            )
            ->addOption(
                self::OPTION_UPDATE_TITLES,
                null,
                InputOption::VALUE_NONE,
                'Update the code and the titles of the tax rate as well.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $taxRateIds   = explode(',', $input->getOption(self::OPTION_IDS));
        $taxRateIds   = array_filter(array_map('trim', $taxRateIds));
        $newRate      = (float)$input->getOption(self::OPTION_RATE);
        $updateTitles = (bool)$input->getOption(self::OPTION_UPDATE_TITLES);

        foreach ($taxRateIds as $taxRateId) {
            try {
                $taxRate = $this->taxRateRepository->get((int)$taxRateId);
            } catch (NoSuchEntityException $e) {
                $warning = '<warning>A tax rate with the ID %d does not exist.</warning>';
                $output->writeln(sprintf($warning, [$taxRateId]));
                continue;
            }
            $oldRate = $taxRate->getRate();
            $success = $this->editTaxRate($output, $taxRate, $newRate, $updateTitles);
            if ($success) {
                $message = '<info>Updated tax rate with ID %d from %f to %f.</info>';
                $output->writeln(sprintf($message, $taxRateId, $oldRate, $newRate));
            }
        }

        return 0;
    }

    private function editTaxRate(
        OutputInterface $output,
        TaxRateInterface $taxRate,
        float $newRate,
        bool $updateTitles = false
    ): bool {
        if ($updateTitles) {
            $this->updateTitlesForTaxRate($taxRate, (int)$newRate);
        }
        $taxRate->setRate($newRate);

        try {
            $this->taxRateRepository->save($taxRate);
        } catch (InputException|Exception $e) {
            $error = '<error>The tax rate with the ID %d could not be saved.</error>';
            $output->writeln(sprintf($error, [$taxRate->getId()]));

            return false;
        }

        return true;
    }

    private function updateTitlesForTaxRate(TaxRateInterface $taxRate, int $newRate): void
    {
        // assumption: everyone writes the tax rate without decimal places in their code / title
        // current shortcut: only integer tax rates are supported - this will not work for decimal tax rates
        $oldRate = (string)$taxRate->getRate();
        $newCode = str_replace($oldRate, (string)$newRate, $taxRate->getCode());
        $taxRate->setCode($newCode);

        foreach ($taxRate->getTitles() as $title) {
            $newValue = str_replace($oldRate, (string)$newRate, $title->getValue());
            $title->setValue($newValue);
        }
    }
}
