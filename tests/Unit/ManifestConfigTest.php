<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards against a preference that exists only in the manifest.
 *
 * A collect_authz toggle shipped in this branch describing an "Authz tab"
 * logging group-permission checks, with no collector behind it anywhere. An
 * administrator who enabled it while debugging permissions would have got
 * nothing and reasonably concluded the module was broken -- a dead toggle is
 * worse than an absent one, because it advertises a capability.
 *
 * It could not simply be implemented, either: XoopsGroupPermHandler::checkRight()
 * fires no preload event and xoops_getHandler() caches handlers in a
 * function-local static, so a module cannot decorate the handler the way
 * PreloadEventSpy decorates the event table. That needs a core-side seam first.
 *
 * This test asserts every functional preference is read by something. Section
 * headers are exempt: they are `line_break` entries that exist purely to label
 * a group in the preferences form, so nothing ever reads their value.
 */
final class ManifestConfigTest extends TestCase
{
    /** @return list<array{name: string, formtype: string}> */
    private function declaredPreferences(): array
    {
        $manifest = file_get_contents(dirname(__DIR__, 2) . '/xoops_version.php');
        self::assertIsString($manifest);

        // Each entry is a $modversion['config'][] = [ ... ]; block.
        $blocks = preg_split("/\\\$modversion\['config'\]\[\]\s*=\s*\[/", $manifest);
        self::assertIsArray($blocks);

        $found = [];
        foreach (array_slice($blocks, 1) as $block) {
            $body = explode('];', $block, 2)[0];
            if (1 !== preg_match("/'name'\s*=>\s*'([A-Za-z0-9_]+)'/", $body, $name)) {
                continue;
            }
            preg_match("/'formtype'\s*=>\s*'([A-Za-z0-9_]+)'/", $body, $formtype);
            $found[] = ['name' => $name[1], 'formtype' => $formtype[1] ?? ''];
        }

        return $found;
    }

    /**
     * Concatenated source of everything that could legitimately read a
     * preference. language/ is excluded because it only defines the _MI_
     * display constants, and xoops_version.php because that is the declaration
     * itself -- a preference referenced only there is exactly the bug.
     *
     * tests/ is excluded for the same reason, and it is not hypothetical: with
     * tests included, this file's own assertion that collect_authz is gone was
     * enough to make collect_authz look implemented, so the guard passed while
     * the dead preference was present.
     */
    private function moduleSource(): string
    {
        $root = dirname(__DIR__, 2);
        $source = '';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (1 === preg_match('#/(vendor|\.git|language|tests)/#', $path)) {
                continue;
            }
            if (str_ends_with($path, '/xoops_version.php')) {
                continue;
            }
            if (1 !== preg_match('/\.(php|js|tpl)$/', $path)) {
                continue;
            }
            $source .= (string) file_get_contents($path);
        }

        return $source;
    }

    public function testEveryDeclaredPreferenceIsReadSomewhere(): void
    {
        $source = $this->moduleSource();
        $dead = [];

        foreach ($this->declaredPreferences() as $preference) {
            if ('line_break' === $preference['formtype']) {
                continue;   // a section label in the form; nothing reads its value
            }
            $name = $preference['name'];
            if (! str_contains($source, "'" . $name . "'") && ! str_contains($source, '"' . $name . '"')) {
                $dead[] = $name;
            }
        }

        self::assertSame(
            [],
            $dead,
            'preferences declared in xoops_version.php but never read: ' . implode(', ', $dead)
        );
    }

    public function testTheRemovedAuthzPreferenceHasNotReturned(): void
    {
        $manifest = file_get_contents(dirname(__DIR__, 2) . '/xoops_version.php');
        self::assertIsString($manifest);

        self::assertStringNotContainsString("'collect_authz'", $manifest);
    }

    /**
     * The parser above is only meaningful if it actually finds the preferences.
     * A regex that silently matched nothing would make the guard vacuous.
     */
    public function testTheManifestParserFindsTheKnownPreferences(): void
    {
        $names = array_column($this->declaredPreferences(), 'name');

        self::assertGreaterThan(20, count($names));
        self::assertContains('debugbar_enable', $names);
        self::assertContains('collect_events', $names);
        self::assertContains('collect_templates', $names);

        // The two entries carrying a nested `options` array. Naming them keeps
        // the block parser honest about nesting: the outer declaration ends at
        // `];` while a nested array ends at `],`, so these parse whole today --
        // but they are the entries that would break first if either the parser
        // or the manifest's formatting changed.
        $byName = array_column($this->declaredPreferences(), 'formtype', 'name');
        self::assertSame('select', $byName['editor_link'] ?? '');
        self::assertSame('select', $byName['monolog_level'] ?? '');
    }

    /**
     * The block parser ends each declaration at the first `];`. Some entries
     * (editor_link, monolog_level) carry a nested `options` array, so a name
     * resolved without its formtype would mean the parser truncated the block
     * early — and the exemption above would then silently misclassify entries.
     * Assert every parsed preference kept its formtype rather than assuming it.
     */
    public function testEveryParsedPreferenceKeepsItsFormtype(): void
    {
        $missing = [];
        foreach ($this->declaredPreferences() as $preference) {
            if ('' === $preference['formtype']) {
                $missing[] = $preference['name'];
            }
        }

        self::assertSame([], $missing, 'parser truncated these blocks: ' . implode(', ', $missing));
    }
}
