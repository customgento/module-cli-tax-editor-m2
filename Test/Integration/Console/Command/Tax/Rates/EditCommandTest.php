<?php

declare(strict_types=1);

namespace CustomGento\CliTaxEditor\Test\Integration\Console\Command\Tax\Rates;

use CustomGento\CliTaxEditor\Console\Command\Tax\Rates\EditCommand;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRateTitleInterface;
use Magento\Tax\Api\Data\TaxRateTitleInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EditCommandTest extends TestCase
{
    private const INITIAL_TAX_RATE = 8;
    private const INITIAL_TAX_CODE = 'Test rate with 8% tax';

    /**
     * @var EditCommand
     */
    private $command;

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    protected function setUp(): void
    {
        $objectManager               = Bootstrap::getObjectManager();
        $this->command               = $objectManager->create(EditCommand::class);
        $this->taxRateRepository     = $objectManager->create(TaxRateRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
    }

    /**
     * @magentoDataFixture createTestTaxRate
     * @throws InputException|NoSuchEntityException
     */
    public function testExecuteWithoutTitles(): void
    {
        $newRate = 42;

        $taxRate = $this->getTaxRateByCode(self::INITIAL_TAX_CODE);
        $this->assertEquals(self::INITIAL_TAX_RATE, $taxRate->getRate(), '', 0.0001);
        $this->assertEquals(self::INITIAL_TAX_CODE, $taxRate->getCode());

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--ids' => $taxRate->getId(), '--rate' => $newRate]);

        $taxRate = $this->getTaxRateById((int)$taxRate->getId());
        $this->assertEquals($newRate, $taxRate->getRate(), '', 0.0001);
        $this->assertEquals(self::INITIAL_TAX_CODE, $taxRate->getCode());
        $titles = $taxRate->getTitles();
        $this->assertCount(1, $titles);
        $title = array_pop($titles);
        $this->assertEquals(self::INITIAL_TAX_CODE, $title->getValue());
    }

    /**
     * @magentoDataFixture createTestTaxRate
     * @throws InputException|NoSuchEntityException
     */
    public function testExecuteWithTitles(): void
    {
        $newRate = 42;
        $newCode = str_replace(self::INITIAL_TAX_RATE, $newRate, self::INITIAL_TAX_CODE);

        $taxRate = $this->getTaxRateByCode(self::INITIAL_TAX_CODE);
        $this->assertEquals(self::INITIAL_TAX_RATE, $taxRate->getRate(), '', 0.0001);
        $this->assertEquals(self::INITIAL_TAX_CODE, $taxRate->getCode());

        $commandTester = new CommandTester($this->command);
        $commandTester->execute(['--ids' => $taxRate->getId(), '--rate' => $newRate, '--update-titles' => true]);

        $taxRate = $this->getTaxRateById((int)$taxRate->getId());
        $this->assertEquals($newRate, $taxRate->getRate(), '', 0.0001);
        $this->assertEquals($newCode, $taxRate->getCode());
        $titles = $taxRate->getTitles();
        $this->assertCount(1, $titles);
        $title = array_pop($titles);
        $this->assertEquals($newCode, $title->getValue());
    }

    /**
     * @param string $taxRateCode
     *
     * @return TaxRateInterface
     * @throws InputException|NoSuchEntityException
     */
    private function getTaxRateByCode(string $taxRateCode): TaxRateInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('code', $taxRateCode)->create();
        $taxRatesList   = $this->taxRateRepository->getList($searchCriteria);
        $this->assertEquals(1, $taxRatesList->getTotalCount());
        $taxRates = $taxRatesList->getItems();
        $taxRate  = array_pop($taxRates);
        // make sure the tax rate is fully loaded
        $taxRate = $this->taxRateRepository->get($taxRate->getId());

        return $this->getTaxRateById((int)$taxRate->getId());
    }

    /**
     * @param int $id
     *
     * @return TaxRateInterface
     * @throws NoSuchEntityException
     */
    private function getTaxRateById(int $id): TaxRateInterface
    {
        return $this->taxRateRepository->get($id);
    }

    /**
     * @throws InputException
     */
    public static function createTestTaxRate(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var TaxRateRepositoryInterface $taxRateRepository */
        $taxRateRepository = $objectManager->create(TaxRateRepositoryInterface::class);
        /** @var TaxRateInterface $taxRate */
        $taxRate = $objectManager->create(TaxRateInterfaceFactory::class)->create();
        $taxRate->setTaxCountryId('DE');
        $taxRate->setTaxPostcode('*');
        $taxRate->setRate(self::INITIAL_TAX_RATE);
        $taxRate->setCode(self::INITIAL_TAX_CODE);

        /** @var TaxRateTitleInterface $taxRateTitle */
        $taxRateTitle = $objectManager->create(TaxRateTitleInterfaceFactory::class)->create();
        $taxRateTitle->setValue(self::INITIAL_TAX_CODE);
        $taxRate->setTitles([$taxRateTitle]);

        $taxRateRepository->save($taxRate);
    }
}
