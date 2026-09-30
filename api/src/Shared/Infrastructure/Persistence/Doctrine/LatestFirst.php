<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\Uuid;
use Doctrine\ORM\QueryBuilder;

/**
 * A history read a page at a time, the latest first: by creation time, then by id, since what
 * one operation creates shares its time (to the second). The cursor is the last item received.
 */
final class LatestFirst
{
    /**
     * @param string $alias  of the entity, which has `createdAt` and `id`
     * @param string $idType the Doctrine type of its id
     */
    public static function page(QueryBuilder $query, string $alias, int $limit, ?\DateTimeImmutable $before, ?Uuid $beforeId, string $idType): QueryBuilder
    {
        $query->orderBy($alias.'.createdAt', 'DESC')->addOrderBy($alias.'.id', 'DESC')->setMaxResults($limit);
        if (null === $before) {
            return $query;
        }
        if (null === $beforeId) {
            $query->andWhere(\sprintf('%s.createdAt < :before', $alias));
        } else {
            $query->andWhere(\sprintf('%1$s.createdAt < :before OR (%1$s.createdAt = :before AND %1$s.id < :beforeId)', $alias))
                ->setParameter('beforeId', $beforeId, $idType);
        }

        return $query->setParameter('before', $before, 'datetimetz_immutable');
    }
}
