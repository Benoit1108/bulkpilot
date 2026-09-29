<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

/**
 * Rebuilds a stored value object without its constructor: what was valid when saved stays
 * readable if a rule is tightened later (history is never rewritten, nor made unreadable).
 * The way Doctrine itself hydrates entities and embeddables.
 */
final class Reconstitute
{
    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $properties
     *
     * @return T
     */
    public static function of(string $class, array $properties): object
    {
        $reflection = new \ReflectionClass($class);
        $object = $reflection->newInstanceWithoutConstructor();
        foreach ($properties as $name => $value) {
            $reflection->getProperty($name)->setValue($object, $value);
        }

        return $object;
    }
}
