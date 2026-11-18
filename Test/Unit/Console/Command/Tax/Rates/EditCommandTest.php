<?php

declare(strict_types=1);

namespace CustomGento\CliTaxEditor\Test\Unit\Console\Command\Tax\Rates;

use CustomGento\CliTaxEditor\Console\Command\Tax\Rates\EditCommand;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRateTitleInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class EditCommandTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'rate missing'      => [['--ids' => '1,2'], '--rate'],
            'rate empty'        => [['--ids' => '1', '--rate' => ''], '--rate'],
            'rate not numeric'  => [['--ids' => '1', '--rate' => 'abc'], '--rate'],
            'rate negative'     => [['--ids' => '1', '--rate' => '-5'], '--rate'],
            'rate infinite'     => [['--ids' => '1', '--rate' => '1e999'], '--rate'],
            'ids missing'       => [['--rate' => '8'], '--ids'],
            'ids empty'         => [['--ids' => ' , ', '--rate' => '8'], '--ids'],
            'ids not numeric'   => [['--ids' => '1,abc', '--rate' => '8'], '--ids'],
            'ids not positive'  => [['--ids' => '0', '--rate' => '8'], '--ids'],
        ];
    }

    public function testInvalidInputFailsWithoutTouchingTaxRates(): void
    {
        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->expects(self::never())->method('get');
        $repository->expects(self::never())->method('save');
        $commandTester = new CommandTester(new EditCommand($repository));

        foreach (self::invalidInputProvider() as $name => [$input, $expectedOption]) {
            $status = $commandTester->execute($input);

            self::assertSame(Command::FAILURE, $status, $name);
            self::assertStringContainsString($expectedOption, $commandTester->getDisplay(), $name);
        }
    }

    public function testZeroRateIsAccepted(): void
    {
        $taxRate = $this->createTaxRate(8.0, 'Rate 8%');
        $taxRate->expects(self::once())->method('setRate')->with(0.0);
        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->method('get')->with(1)->willReturn($taxRate);
        $repository->expects(self::once())->method('save')->with($taxRate);

        $status = (new CommandTester(new EditCommand($repository)))->execute(['--ids' => '1', '--rate' => '0']);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testNonStringOptionValuesAreAccepted(): void
    {
        $taxRate = $this->createTaxRate(8.0, 'Rate 8%');
        $taxRate->expects(self::exactly(2))->method('setRate')->with(42.0);
        $repository = $this->createMock(TaxRateRepositoryInterface::class);
        $repository->method('get')->with(1)->willReturn($taxRate);
        $repository->expects(self::exactly(2))->method('save')->with($taxRate);
        $commandTester = new CommandTester(new EditCommand($repository));

        self::assertSame(Command::SUCCESS, $commandTester->execute(['--ids' => 1, '--rate' => 42]));
        self::assertSame(Command::SUCCESS, $commandTester->execute(['--ids' => 1, '--rate' => 42.0]));
    }

    public function testUpdateTitlesKeepsDecimalPlaces(): void
    {
        $cases = [
            'integer to decimal' => [23.0, '7.5', 'VAT 23%', 'VAT 7.5%'],
            'decimal to integer' => [7.5, '8', 'VAT 7.5%', 'VAT 8%'],
            'decimal to decimal' => [7.5, '8.25', 'VAT 7.5%', 'VAT 8.25%'],
            'integer to integer' => [23.0, '19', 'VAT 23%', 'VAT 19%'],
        ];

        foreach ($cases as $name => [$oldRate, $newRate, $oldCode, $expectedCode]) {
            $this->assertTitlesUpdated($name, $oldRate, $newRate, $oldCode, $expectedCode);
        }
    }

    private function assertTitlesUpdated(
        string $name,
        float $oldRate,
        string $newRate,
        string $oldCode,
        string $expectedCode
    ): void {
        $repository    = $this->createMock(TaxRateRepositoryInterface::class);
        $commandTester = new CommandTester(new EditCommand($repository));

        $title = $this->createMock(TaxRateTitleInterface::class);
        $title->method('getValue')->willReturn($oldCode);
        $title->expects(self::once())->method('setValue')->with($expectedCode);

        $taxRate = $this->createTaxRate($oldRate, $oldCode);
        $taxRate->method('getTitles')->willReturn([$title]);
        $taxRate->expects(self::once())->method('setCode')->with($expectedCode);
        $taxRate->expects(self::once())->method('setRate')->with((float)$newRate);

        $repository->method('get')->with(1)->willReturn($taxRate);
        $repository->expects(self::once())->method('save')->with($taxRate);

        $status = $commandTester->execute(['--ids' => '1', '--rate' => $newRate, '--update-titles' => true]);

        self::assertSame(Command::SUCCESS, $status, $name);
    }

    /**
     * @return TaxRateInterface&MockObject
     */
    private function createTaxRate(float $rate, string $code)
    {
        $taxRate = $this->createMock(TaxRateInterface::class);
        $taxRate->method('getRate')->willReturn($rate);
        $taxRate->method('getCode')->willReturn($code);
        $taxRate->method('getId')->willReturn(1);

        return $taxRate;
    }
}
