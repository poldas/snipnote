<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\NotesSearchParser;
use PHPUnit\Framework\TestCase;

final class NotesSearchParserTest extends TestCase
{
    private NotesSearchParser $parser;

    protected function setUp(): void
    {
        $this->parser = new NotesSearchParser();
    }

    public function testReturnsEmptyResultForNullOrBlankQuery(): void
    {
        self::assertSame(
            ['labels' => [], 'text' => null],
            $this->parser->parse(null)
        );

        self::assertSame(
            ['labels' => [], 'text' => null],
            $this->parser->parse('    ')
        );
    }

    public function testExtractsLabelsAndNormalizesText(): void
    {
        $result = $this->parser->parse('  label:work,label:home   meeting   notes  ');

        self::assertSame(['work', 'home'], $result['labels']);
        self::assertSame('meeting notes', $result['text']);
    }

    public function testDeduplicatesLabelsFromMultipleTokens(): void
    {
        $result = $this->parser->parse('label:work label:home,label:work sprint review');

        self::assertSame(['work', 'home'], $result['labels']);
        self::assertSame('sprint review', $result['text']);
    }

    public function testIgnoresEmptyLabelsInCommaSeparatedList(): void
    {
        $result = $this->parser->parse('label: , ,work,, ,personal, ,');

        self::assertSame(['work', 'personal'], $result['labels']);
        self::assertNull($result['text']);
    }

    public function testHandlesMultiWordAndUnicodeLabelsWithSymbols(): void
    {
        $result = $this->parser->parse('label:"projekt alfa" label:"Łódź#1" label:\'dev ops\' plan sprintu');

        self::assertSame(['projekt alfa', 'Łódź#1', 'dev ops'], $result['labels']);
        self::assertSame('plan sprintu', $result['text']);
    }
}
