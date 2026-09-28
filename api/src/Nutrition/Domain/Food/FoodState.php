<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

/**
 * 100 g of raw rice and 100 g of cooked rice are different foods (spec §19).
 */
enum FoodState: string
{
    case Raw = 'raw';
    case Cooked = 'cooked';
    case NotApplicable = 'not_applicable';

    private const string RAW = '/\b(crus?|crues?)\b/u';
    private const string COOKED = '/\b(cuits?|cuites?|bouillie?s?|rôtie?s?|grillée?s?|poêlée?s?|sautée?s?|frite?s?|braisée?s?|pochée?s?|brouillée?s?|à la coque|au plat|à la vapeur|au four|à l\'eau)\b/u';
    /** "dur" is a cooking only for eggs: "blé dur" is a variety of wheat. */
    private const string HARD_BOILED_EGG = '/\b(oeufs?|œufs?)\b.*\bdurs?\b/u';

    /**
     * Reads the preparation from a published food name ("Riz blanc, cru", "Poulet, filet, rôti").
     */
    public static function fromName(string $name): self
    {
        $name = mb_strtolower($name);

        return match (true) {
            1 === preg_match(self::COOKED, $name), 1 === preg_match(self::HARD_BOILED_EGG, $name) => self::Cooked,
            1 === preg_match(self::RAW, $name) => self::Raw,
            default => self::NotApplicable,
        };
    }
}
