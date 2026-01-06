<?php

declare(strict_types=1);

namespace App\DBAL\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Maps PostgreSQL TEXT[] to PHP array of strings.
 */
final class TextArrayType extends Type
{
    public const NAME = 'text_array';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT[]';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw ConversionException::conversionFailedInvalidType($value, self::NAME, ['array', 'null']);
        }

        return $this->encodeArray($value);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (!is_string($value)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        return $this->decodeArray($value);
    }

    /**
     * @param list<string> $values
     */
    private function encodeArray(array $values): string
    {
        $escaped = array_map(
            static function ($item): string {
                if (!is_string($item)) {
                    throw ConversionException::conversionFailedInvalidType($item, self::NAME, ['string']);
                }

                $item = str_replace(['\\', '"'], ['\\\\', '\\"'], $item);

                return '"' . $item . '"';
            },
            $values
        );

        return '{' . implode(',', $escaped) . '}';
    }

    /**
     * @return list<string>
     */
    private function decodeArray(string $value): array
    {
        $trimmed = trim($value, '{}');
        if ($trimmed === '') {
            return [];
        }

        $items = str_getcsv($trimmed, ',', '"', '\\');

        return array_map(
            static fn (string $item): string => str_replace(['\\"', '\\\\'], ['"', '\\'], $item),
            $items
        );
    }
}

