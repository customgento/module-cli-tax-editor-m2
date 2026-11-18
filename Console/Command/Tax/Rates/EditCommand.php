<?php

declare(strict_types=1);

namespace CustomGento\CliTaxEditor\Console\Command\Tax\Rates;

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
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
        try {
            $taxRateIds = $this->parseTaxRateIds($input->getOption(self::OPTION_IDS));
            $newRate = $this->parseRate($input->getOption(self::OPTION_RATE));
        } catch (InvalidOptionException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $updateTitles = (bool)$input->getOption(self::OPTION_UPDATE_TITLES);

        foreach ($taxRateIds as $taxRateId) {
            try {
                $taxRate = $this->taxRateRepository->get((int)$taxRateId);
            } catch (NoSuchEntityException $e) {
                $warning = '<warning>A tax rate with the ID %d does not exist.</warning>';
                $output->writeln(sprintf($warning, $taxRateId));
                continue;
            }
            $oldRate = $taxRate->getRate();
            $success = $this->editTaxRate($output, $taxRate, $newRate, $updateTitles);
            if ($success) {
                $message = '<info>Updated tax rate with ID %d from %f to %f.</info>';
                $output->writeln(sprintf($message, $taxRateId, $oldRate, $newRate));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param mixed $ids
     * @return string[]
     * @throws InvalidOptionException
     */
    private function parseTaxRateIds($ids): array
    {
        $error = 'The option --ids requires a comma-separated list of tax rate IDs.';
        if (!is_string($ids)) {
            throw new InvalidOptionException($error);
        }

        $taxRateIds = array_filter(array_map('trim', explode(',', $ids)), static function (string $id): bool {
            return $id !== '';
        });
        if ($taxRateIds === []) {
            throw new InvalidOptionException($error);
        }

        foreach ($taxRateIds as $taxRateId) {
            if (!ctype_digit($taxRateId) || (int)$taxRateId < 1) {
                throw new InvalidOptionException($error);
            }
        }

        return $taxRateIds;
    }

    /**
     * @param mixed $rate
     * @throws InvalidOptionException
     */
    private function parseRate($rate): float
    {
        if (is_string($rate) && is_numeric($rate)) {
            $rate = (float)$rate;
            if (is_finite($rate) && $rate >= 0) {
                return $rate;
            }
        }

        throw new InvalidOptionException('The option --rate requires a numeric value of 0 or greater.');
    }

    private function editTaxRate(
        OutputInterface $output,
        TaxRateInterface $taxRate,
        float $newRate,
        bool $updateTitles = false
    ): bool {
        if ($updateTitles) {
            $this->updateTitlesForTaxRate($taxRate, $newRate);
        }
        $taxRate->setRate($newRate);

        try {
            $this->taxRateRepository->save($taxRate);
        } catch (InputException|Exception $e) {
            $error = '<error>The tax rate with the ID %d could not be saved.</error>';
            $output->writeln(sprintf($error, $taxRate->getId()));

            return false;
        }

        return true;
    }

    private function updateTitlesForTaxRate(TaxRateInterface $taxRate, float $newRate): void
    {
        $oldRate = (string)(float)$taxRate->getRate();
        $newRateText = (string)$newRate;
        $newCode = str_replace($oldRate, $newRateText, $taxRate->getCode());
        $taxRate->setCode($newCode);

        foreach ($taxRate->getTitles() ?? [] as $title) {
            $newValue = str_replace($oldRate, $newRateText, $title->getValue());
            $title->setValue($newValue);
        }
    }
}
