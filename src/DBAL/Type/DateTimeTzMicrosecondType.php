<?php

declare(strict_types=1);

namespace App\DBAL\Type;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Same as Doctrine's datetimetz_immutable but accepts microseconds from PostgreSQL.
 */
final class DateTimeTzMicrosecondType extends DateTimeTzImmutableType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.uO', (string)$value);
        if ($dateTime !== false) {
            return $dateTime;
        }

        try {
            return parent::convertToPHPValue($value, $platform);
        } catch (InvalidFormat $exception) {
            throw InvalidFormat::new($value, self::class, 'Y-m-d H:i:s.uO', $exception);
        }
    }
}
