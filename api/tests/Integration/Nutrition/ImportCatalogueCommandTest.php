<?php

declare(strict_types=1);

namespace App\Tests\Integration\Nutrition;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportCatalogueCommandTest extends KernelTestCase
{
    public function testTheCommandImportsAndSummarises(): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('nutrition:catalogue:import'));

        $tester->execute(['location' => __DIR__.'/../../Fixtures/ciqual'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('12 foods created, 0 refreshed, 0 skipped.', $tester->getDisplay());

        $tester->execute(['location' => __DIR__.'/../../Fixtures/ciqual']);
        self::assertStringContainsString('0 foods created, 12 refreshed', $tester->getDisplay());
    }
}
