<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Coach\Domain\Recommendation\CoachMessage;
use App\Coach\Domain\Rule\Finding;

/**
 * What the user typed never reaches an AI provider (spec §122): exercise names are sent as
 * placeholders (`{exercise1}`) and put back into the message it returns.
 */
final class Placeholders
{
    /**
     * @return array{Finding, array<string, string>} the finding to send, and the names by placeholder
     */
    public static function hide(Finding $finding): array
    {
        $exercises = $finding->figures['exercises'] ?? null;
        if (!\is_array($exercises)) {
            return [$finding, []];
        }
        $names = [];
        $hidden = [];
        foreach ($exercises as $i => $stall) {
            $placeholder = \sprintf('{exercise%d}', $i + 1);
            $names[$placeholder] = $stall['exercise'];
            $hidden[] = ['exercise' => $placeholder] + $stall;
        }

        return [new Finding($finding->kind, $finding->level, ['exercises' => $hidden] + $finding->figures, $finding->proposal), $names];
    }

    /**
     * @param array<string, string> $names by placeholder
     */
    public static function reveal(CoachMessage $message, array $names): CoachMessage
    {
        if ([] === $names) {
            return $message;
        }
        $put = static fn (string $text): string => strtr($text, $names);

        return new CoachMessage($put($message->title), $put($message->body), array_map($put, $message->why), $put($message->action), $message->provider);
    }
}
