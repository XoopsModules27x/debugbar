<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;

/**
 * CachegrindCatalog::listFiles() feeds the Analytics page through
 * AnalyticsBuilder::build(), whose return type is array<string, mixed>. That
 * means static analysis cannot verify that the page reads the keys the catalog
 * actually produces, and it did not: the page read 'mtime' and 'bytes' while
 * the catalog returns 'modified' and 'size'. Nothing failed loudly — every
 * profile simply rendered as 1969-12-31 and 0.0 KB.
 *
 * This test pins both ends of that contract until the view model is properly
 * typed. It reads source rather than executing the page, which needs a booted
 * XOOPS.
 */
final class CachegrindCatalogContractTest extends TestCase
{
    private const PRODUCED_KEYS = ['file', 'modified', 'size'];

    private function moduleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function testListFilesProducesTheDocumentedKeys(): void
    {
        $source = file_get_contents($this->moduleRoot() . '/class/Analysis/CachegrindCatalog.php');
        self::assertIsString($source);

        // The declared shape on listFiles() is the contract the page relies on.
        self::assertStringContainsString(
            '@return list<array{file: string, modified: int, size: int}>',
            $source,
            'listFiles() shape changed; update the Analytics page and this test together'
        );

        foreach (self::PRODUCED_KEYS as $key) {
            self::assertStringContainsString("'" . $key . "' =>", $source, "listFiles() no longer emits '{$key}'");
        }
    }

    public function testAnalyticsPageOnlyReadsKeysTheCatalogProduces(): void
    {
        $page = file_get_contents($this->moduleRoot() . '/admin/analytics.php');
        self::assertIsString($page);

        self::assertSame(
            1,
            preg_match_all('/\$cgRow\[/', $page) > 0 ? 1 : 0,
            'expected the Analytics page to iterate cachegrind rows'
        );

        preg_match_all("/\\\$cgRow\\['([a-z_]+)'\\]/", $page, $matches);
        $readKeys = array_values(array_unique($matches[1]));
        self::assertNotSame([], $readKeys);

        foreach ($readKeys as $key) {
            self::assertContains(
                $key,
                self::PRODUCED_KEYS,
                "Analytics reads \$cgRow['{$key}'], which CachegrindCatalog::listFiles() does not produce"
            );
        }
    }
}
