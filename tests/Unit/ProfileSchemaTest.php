<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Drift guard for the `debugbar_profiles` table.
 *
 * Unlike the upstream build, this ProfileRepository does not centralize its
 * INSERT column list in a `COLUMNS` constant — the column names are written
 * directly into the `INSERT INTO %s (...)` format string inside insert().
 * So instead of reflecting a constant, this test parses that literal column
 * list straight out of the source (the same technique already used below
 * for the DDL) and asserts every name is an actual DDL column in
 * sql/mysql.sql — adding an INSERT column without a matching DDL column (or
 * renaming one in only one place) fails this test.
 */
final class ProfileSchemaTest extends TestCase
{
    /**
     * @return list<string> column names as declared in the CREATE TABLE
     */
    private function ddlColumns(): array
    {
        $sqlFile = dirname(__DIR__, 2) . '/sql/mysql.sql';
        self::assertFileExists($sqlFile);

        $sql = file_get_contents($sqlFile);
        self::assertIsString($sql);

        if (1 !== preg_match('/CREATE TABLE\s+debugbar_profiles\s*\((.*?)\)\s*ENGINE=/is', $sql, $matches)) {
            self::fail('CREATE TABLE debugbar_profiles not found in sql/mysql.sql');
        }
        $body = $matches[1];

        // This build's DDL packs several column definitions per physical
        // line (e.g. "request_id CHAR(16) ..., created INT ..., "), so
        // definitions are separated on commas OUTSIDE parentheses rather
        // than on newlines.
        $definitions = [];
        $depth = 0;
        $current = '';
        for ($i = 0, $length = strlen($body); $i < $length; ++$i) {
            $char = $body[$i];
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            }
            if (',' === $char && 0 === $depth) {
                $definitions[] = $current;
                $current = '';

                continue;
            }
            $current .= $char;
        }
        if ('' !== trim($current)) {
            $definitions[] = $current;
        }

        $columns = [];
        foreach ($definitions as $definition) {
            $definition = trim(preg_replace('/\s+/', ' ', $definition) ?? $definition);
            if ('' === $definition) {
                continue;
            }
            // Skip constraint/key lines — only real column definitions start
            // with a bare identifier followed by a type.
            if (1 === preg_match('/^(PRIMARY KEY|KEY|UNIQUE|CONSTRAINT|INDEX)\b/i', $definition)) {
                continue;
            }
            if (1 === preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+[A-Za-z]/', $definition, $col)) {
                $columns[] = $col[1];
            }
        }

        return $columns;
    }

    /**
     * @return list<string> the literal INSERT column list parsed out of
     *                       ProfileRepository::insert()'s format string
     */
    private function insertColumns(): array
    {
        $sourceFile = dirname(__DIR__, 2) . '/class/ProfileRepository.php';
        self::assertFileExists($sourceFile);

        $source = file_get_contents($sourceFile);
        self::assertIsString($source);

        if (1 !== preg_match('/INSERT INTO %s\s*\(([^)]+)\)\s*VALUES/', $source, $matches)) {
            self::fail('INSERT INTO %s (...) VALUES not found in class/ProfileRepository.php');
        }

        return explode(',', $matches[1]);
    }

    public function testDdlHasAtLeastTheExpectedColumns(): void
    {
        $ddl = $this->ddlColumns();

        // Sanity: the DDL parser found a realistic column set, not an
        // empty/garbage match (would silently make the subset assertion
        // below vacuously true).
        self::assertGreaterThanOrEqual(15, count($ddl));
        self::assertContains('profile_id', $ddl);
        self::assertContains('slowest_fp', $ddl);
    }

    public function testEveryInsertColumnExistsInTheDdl(): void
    {
        $ddl = $this->ddlColumns();
        $insertColumns = $this->insertColumns();

        self::assertNotEmpty($insertColumns);

        foreach ($insertColumns as $column) {
            self::assertContains(
                $column,
                $ddl,
                "ProfileRepository::insert()'s column list has '{$column}' but sql/mysql.sql's CREATE TABLE does not — schema drift."
            );
        }
    }

    public function testInsertColumnListHasNoDuplicatesOrAutoIncrementColumn(): void
    {
        $insertColumns = $this->insertColumns();

        self::assertSame(
            $insertColumns,
            array_unique($insertColumns),
            "ProfileRepository::insert()'s column list contains a duplicate column name."
        );

        // profile_id is AUTO_INCREMENT and must never be hand-inserted.
        self::assertNotContains('profile_id', $insertColumns);
    }
}
