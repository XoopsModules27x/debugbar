<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every admin page that renders collected data must sit behind AccessPolicy.
 *
 * Being a module admin is not the bar: AccessPolicy additionally requires XOOPS
 * debug mode to be on and the module itself to be enabled, which is what stops
 * a site that has finished debugging from still exposing statements, paths and
 * log contents to anyone with the admin bit.
 *
 * This exists because logs.php shipped without that gate while analytics.php
 * and diagnostics.php had it. Nothing pinned the set, so the omission was
 * invisible. Enumerating the pages here means adding a new data-exposing page
 * without a gate fails the suite.
 */
final class AdminPageGatingTest extends TestCase
{
    /**
     * Pages that display collected data and therefore require the full gate.
     *
     * @return list<array{0: string}>
     */
    public static function gatedPages(): array
    {
        return [['analytics.php'], ['diagnostics.php'], ['logs.php']];
    }

    /**
     * Pages that legitimately need no gate: navigation, layout, and the module
     * home, none of which render captured request data.
     *
     * @return list<array{0: string}>
     */
    public static function ungatedPages(): array
    {
        return [['about.php'], ['admin_footer.php'], ['admin_header.php'], ['menu.php']];
    }

    private function source(string $page): string
    {
        $path = dirname(__DIR__, 3) . '/admin/' . $page;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    #[DataProvider('gatedPages')]
    public function testDataExposingPagesCheckAccessPolicy(string $page): void
    {
        $source = $this->source($page);

        self::assertStringContainsString(
            'AccessPolicy::isAllowed()',
            $source,
            $page . ' renders collected data and must call AccessPolicy::isAllowed()'
        );
        // A gate that does not stop execution is not a gate.
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*!\s*AccessPolicy::isAllowed\(\)\s*\)\s*\{/',
            $source,
            $page . ' must refuse when the policy denies, not merely consult it'
        );
        self::assertStringContainsString(
            'return;',
            $source,
            $page . ' must return after refusing, so the page body never runs'
        );
    }

    #[DataProvider('gatedPages')]
    public function testTheRefusalIsEscapedAndTranslatable(string $page): void
    {
        $source = $this->source($page);

        self::assertMatchesRegularExpression(
            '/_AM_DEBUGBAR_[A-Z_]*FORBIDDEN/',
            $source,
            $page . ' should report refusal through a language constant'
        );
        self::assertStringContainsString('htmlspecialchars', $source, $page . ' must escape the refusal message');
    }

    #[DataProvider('ungatedPages')]
    public function testNavigationAndLayoutPagesAreNotExpectedToGate(string $page): void
    {
        // Documents the deliberate other half of the split, so a future reader
        // does not "fix" these by adding a gate that would break the CP menu.
        $source = $this->source($page);
        self::assertStringNotContainsString(
            'AccessPolicy::isAllowed()',
            $source,
            $page . ' is navigation or layout; gating it would break the control panel'
        );
    }

    public function testEveryForbiddenConstantUsedByAdminPagesIsDefined(): void
    {
        $language = (string) file_get_contents(dirname(__DIR__, 3) . '/language/english/admin.php');

        foreach (self::gatedPages() as [$page]) {
            preg_match_all('/_AM_DEBUGBAR_[A-Z_]*FORBIDDEN/', $this->source($page), $matches);
            self::assertNotEmpty($matches[0], $page . ' declares no forbidden constant');
            foreach (array_unique($matches[0]) as $constant) {
                // An undefined constant is a fatal on PHP 8, so the refusal path
                // would crash instead of refusing — worse than no gate at all.
                self::assertStringContainsString(
                    "'" . $constant . "'",
                    $language,
                    $constant . ' is used by ' . $page . ' but never defined'
                );
            }
        }
    }
}
