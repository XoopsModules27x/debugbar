<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Collector;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Collector\TemplateOrigin;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

/**
 * The origin resolver is the part of the Templates collector that can lie
 * convincingly: a wrong answer to "is my override live?" is worse than no
 * answer, because the whole point is to settle that question. So these tests
 * build real files on disk with controlled sizes and timestamps and check the
 * verdict against them.
 *
 * XOOPS_ROOT_PATH points at the module root during unit runs, so the fixtures
 * are created under it and removed afterwards.
 */
final class TemplateOriginTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    /** @var list<string> */
    private array $createdDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $file) {
            @unlink($file);
        }
        // Deepest first, so a directory is empty by the time we reach it.
        foreach (array_reverse($this->createdDirs) as $dir) {
            @rmdir($dir);
        }
        $this->created = [];
        $this->createdDirs = [];
    }

    private function writeFixture(string $relativePath, string $contents, int $mtime): string
    {
        $path = rtrim(XOOPS_ROOT_PATH, '/\\') . '/' . $relativePath;
        $dir = dirname($path);

        $missing = [];
        for ($probe = $dir; ! is_dir($probe); $probe = dirname($probe)) {
            $missing[] = $probe;
        }
        foreach (array_reverse($missing) as $create) {
            @mkdir($create);
            $this->createdDirs[] = $create;
        }

        file_put_contents($path, $contents);
        touch($path, $mtime);
        $this->created[] = $path;

        return $path;
    }

    public function testIdentifiesAModuleTemplate(): void
    {
        $body = '<{$content}>';
        $mtime = 1700000000;
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), 'default');

        self::assertSame(TemplateOrigin::MODULE_FILE, $result['source']);
        self::assertSame('modules/dbtestmod/templates/dbtestmod_index.tpl', $result['path']);
    }

    public function testAThemeOverrideWinsOverTheModuleFile(): void
    {
        // Both exist with identical bytes and timestamp — which is exactly the
        // situation a webmaster is asking about — and core resolves the theme
        // override first, so the report must say the same.
        $body = '<{$content}>';
        $mtime = 1700000001;
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', $body, $mtime);
        $this->writeFixture('themes/dbtesttheme/modules/dbtestmod/dbtestmod_index.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), 'dbtesttheme');

        self::assertSame(TemplateOrigin::THEME_OVERRIDE, $result['source']);
        self::assertStringContainsString('themes/dbtesttheme', $result['path']);
    }

    public function testAnOverrideForADifferentThemeIsNotClaimed(): void
    {
        // The file exists but belongs to a theme that is not in use, so it is
        // not what served the request.
        $body = '<{$content}>';
        $mtime = 1700000002;
        $this->writeFixture('themes/othertheme/modules/dbtestmod/dbtestmod_index.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), 'dbtesttheme');

        self::assertSame(TemplateOrigin::DATABASE, $result['source']);
    }

    public function testContentThatMatchesNoFileIsReportedAsDatabase(): void
    {
        $origin = new TemplateOrigin();
        $result = $origin->resolve('nothing_on_disk.tpl', 1700000003, 42, 'default');

        self::assertSame(TemplateOrigin::DATABASE, $result['source']);
        self::assertSame('', $result['path']);
    }

    public function testAFileWhoseSizeDisagreesIsNotClaimed(): void
    {
        // Same name and timestamp, different bytes: the file is stale relative
        // to what was actually rendered, so claiming it would be a lie.
        $mtime = 1700000004;
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', 'short', $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', $mtime, 9999, 'default');

        self::assertSame(TemplateOrigin::DATABASE, $result['source']);
    }

    public function testAFileWhoseTimestampDisagreesIsNotClaimed(): void
    {
        $body = '<{$content}>';
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', $body, 1700000005);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', 1699999999, strlen($body), 'default');

        self::assertSame(TemplateOrigin::DATABASE, $result['source']);
    }

    public function testTwoIndistinguishableCandidatesAreReportedAsAmbiguous(): void
    {
        // Same name, size and timestamp in two modules. Picking one would be a
        // coin toss presented as a fact.
        $body = '<{$content}>';
        $mtime = 1700000006;
        $this->writeFixture('modules/dbtestmod/templates/shared_name.tpl', $body, $mtime);
        $this->writeFixture('modules/dbtestmod2/templates/shared_name.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('shared_name.tpl', $mtime, strlen($body), 'default');

        self::assertSame(TemplateOrigin::AMBIGUOUS, $result['source']);
        self::assertStringContainsString('dbtestmod/', $result['path']);
        self::assertStringContainsString('dbtestmod2/', $result['path']);
    }

    public function testBlockTemplatesAreFound(): void
    {
        $body = '<{$block}>';
        $mtime = 1700000007;
        $this->writeFixture('modules/dbtestmod/templates/blocks/dbtestmod_block.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_block.tpl', $mtime, strlen($body), 'default');

        self::assertSame(TemplateOrigin::MODULE_FILE, $result['source']);
        self::assertStringContainsString('templates/blocks/', $result['path']);
    }

    public function testTraversalAndGlobMetacharactersAreRejected(): void
    {
        // Names that must never reach glob(): traversal, backslashes, and the
        // metacharacters that would make the pattern match files the template
        // never was.
        $origin = new TemplateOrigin();

        $bad = ['../../secrets.tpl', 'back\\slash.tpl', 'foo*.tpl', 'a[bc].tpl', '{a,b}.tpl', 'sys?em.tpl', "nul\0.tpl", '', 'a//b.tpl'];
        foreach ($bad as $name) {
            $result = $origin->resolve($name, 1700000008, 10, 'default');
            self::assertSame(TemplateOrigin::DATABASE, $result['source'], "name '{$name}' must not be probed");
            self::assertSame('', $result['path']);
        }
    }

    public function testASubdirectoryNameIsResolvedNotRejected(): void
    {
        // Regression: names carrying a subdirectory are real. `admin/x.tpl`
        // rows exist in a stock tplfile table, and rejecting every name with a
        // separator reported them as `database` even when a file served them.
        $body = '<{$admin}>';
        $mtime = 1700000011;
        $this->writeFixture('modules/dbtestmod/templates/admin/dbtestmod_admin.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('admin/dbtestmod_admin.tpl', $mtime, strlen($body), 'default', $body);

        self::assertSame(TemplateOrigin::MODULE_FILE, $result['source']);
        self::assertStringContainsString('templates/admin/dbtestmod_admin.tpl', $result['path']);
    }

    public function testAdminThemeOverridesWithoutAnAdminSegmentAreFound(): void
    {
        // Under the control panel, core rewrites a normal module template to
        // the admin theme directory and keeps the module segment, with no
        // `admin/` component. Probing only for an `admin/` form missed every
        // real admin override and reported it as database.
        $body = '<{$pagenav}>';
        $mtime = 1700000012;
        $this->writeFixture('modules/system/themes/dbtesttheme/modules/system/dbtest_pagenav.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtest_pagenav.tpl', $mtime, strlen($body), 'default', $body);

        self::assertSame(TemplateOrigin::THEME_OVERRIDE, $result['source']);
        self::assertStringContainsString('modules/system/themes/dbtesttheme', $result['path']);
    }

    public function testAFileMatchingOnSizeAndTimeButNotBytesIsNotClaimed(): void
    {
        // The collision Codex built: identical name, size and mtime, different
        // content. Size and mtime are a filter; only the bytes are proof.
        $mtime = 1700000013;
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_collide.tpl', 'FILE', $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_collide.tpl', $mtime, 4, 'default', 'DATA');

        self::assertSame(TemplateOrigin::DATABASE, $result['source'], 'different bytes must not be claimed as the file');
    }

    public function testAThemeNameWithSeparatorsIsNotInterpolatedIntoTheGlob(): void
    {
        // A theme_set is config, not user input, but it lands in a glob pattern,
        // so a traversal-shaped value must degrade to a wildcard rather than
        // escaping the themes directory.
        $body = '<{$content}>';
        $mtime = 1700000009;
        $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $result = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), '../../etc');

        // Falls through to the module file, having found no theme override.
        self::assertSame(TemplateOrigin::MODULE_FILE, $result['source']);
    }

    public function testRepeatedResolutionIsCached(): void
    {
        $body = '<{$content}>';
        $mtime = 1700000010;
        $path = $this->writeFixture('modules/dbtestmod/templates/dbtestmod_index.tpl', $body, $mtime);

        $origin = new TemplateOrigin();
        $first = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), 'default');

        // Remove the file: a cached answer must not go back to disk.
        unlink($path);
        $second = $origin->resolve('dbtestmod_index.tpl', $mtime, strlen($body), 'default');

        self::assertSame($first, $second);
        self::assertSame(TemplateOrigin::MODULE_FILE, $second['source']);
    }
}
