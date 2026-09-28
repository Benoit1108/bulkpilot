<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Barcode;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Nutrition\Application\Barcode\GetScannedProduct;
use App\Nutrition\Application\Barcode\KeepScannedProduct;
use App\Nutrition\Application\Food\CreateCustomFood;
use App\Nutrition\Application\Food\EditCustomFood;
use App\Nutrition\Application\Food\FindOwnFoodByBarcode;
use App\Nutrition\Application\Food\FoodView;
use App\Nutrition\Application\Food\GetFood;
use App\Nutrition\Domain\Exception\InvalidBarcode;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Presentation\Api\Food\CustomFoodInput;
use App\Nutrition\Presentation\Api\Food\Food;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * As proposed, the product joins the shared catalogue; corrected, it is the user's own food and
 * goes through the same use cases (and rules) as any food they create or edit.
 *
 * @implements ProcessorInterface<ConfirmProductInput, Food>
 */
final readonly class ConfirmProductProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private UuidGenerator $uuids,
        private RateLimiterFactoryInterface $barcodeScanLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Food
    {
        $userId = $this->currentUser->id();
        $barcode = new Barcode($data->barcode);

        if (null !== $data->corrections) {
            return Food::fromView($this->keepCorrected($userId, $barcode, $data->corrections));
        }

        // Reads the product database again, like a scan.
        RateLimit::consume($this->barcodeScanLimiter, 'barcode_scan|'.$userId->value);
        $this->commandBus->dispatch(new KeepScannedProduct(new FoodId($this->uuids->generate()), $barcode));

        return Food::fromView($this->queryBus->ask(new GetScannedProduct($userId, $barcode)));
    }

    private function keepCorrected(UserId $userId, Barcode $barcode, CustomFoodInput $corrections): FoodView
    {
        if (null !== $corrections->barcode && !new Barcode($corrections->barcode)->equals($barcode)) {
            throw InvalidBarcode::because('the corrections are for another barcode');
        }
        $corrections->barcode = $barcode->value;
        $details = $corrections->details();

        /** @var FoodView|null $own */
        $own = $this->queryBus->ask(new FindOwnFoodByBarcode($userId, $barcode));
        if (null !== $own) {
            $foodId = new FoodId($own->id);
            $this->commandBus->dispatch(new EditCustomFood($foodId, $userId, $details));
        } else {
            $foodId = new FoodId($this->uuids->generate());
            $this->commandBus->dispatch(new CreateCustomFood($foodId, $userId, $details));
        }

        return $this->queryBus->ask(new GetFood($userId, $foodId));
    }
}
