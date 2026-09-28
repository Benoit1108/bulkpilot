<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Barcode\ScanBarcode;
use App\Nutrition\Domain\Food\Barcode;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<BarcodeInput, BarcodeScan>
 */
final readonly class BarcodeScanProcessor implements ProcessorInterface
{
    public function __construct(
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $barcodeScanLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BarcodeScan
    {
        $userId = $this->currentUser->id();
        $barcode = new Barcode($data->barcode);
        RateLimit::consume($this->barcodeScanLimiter, 'barcode_scan|'.$userId->value);

        return BarcodeScan::fromView($this->queryBus->ask(new ScanBarcode($userId, $barcode)));
    }
}
