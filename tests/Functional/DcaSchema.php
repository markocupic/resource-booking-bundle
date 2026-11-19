<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Restriction;

namespace Markocupic\ResourceBookingBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Creates the tables of the bundle from the "sql" definitions of its DCA files
 * (contao/dca/*.php), e.g. in an SQLite database in memory.
 *
 * Only what the bundle uses is supported: column definitions in the array format
 * of Contao (Doctrine column options) and the strings "(medium)text NULL" and
 * "(medium)blob NULL". Other definitions throw an exception, so the schema of
 * the tests cannot silently deviate from the DCA.
 */
final class DcaSchema
{
    /**
     * Tables of the bundle (contao/dca/<table>.php).
     */
    public const TABLES = [
        'tl_resource_booking',
        'tl_resource_booking_blocked_period',
        'tl_resource_booking_resource',
        'tl_resource_booking_resource_type',
        'tl_resource_booking_time_slot',
        'tl_resource_booking_time_slot_type',
    ];

    /**
     * Doctrine column options that may appear in the array format.
     */
    private const COLUMN_OPTIONS = ['length', 'precision', 'scale', 'unsigned', 'fixed', 'notnull', 'default', 'autoincrement', 'comment'];

    private function __construct()
    {
    }

    /**
     * @param list<string> $tables
     */
    public static function create(Connection $connection, array $tables = self::TABLES): void
    {
        $schemaManager = $connection->createSchemaManager();

        foreach ($tables as $table) {
            $schemaManager->createTable(self::buildTable($table));
        }
    }

    public static function buildTable(string $tableName): Table
    {
        $dca = self::loadDca($tableName);
        $table = new Table($tableName);

        foreach ($dca['fields'] ?? [] as $fieldName => $field) {
            if (!isset($field['sql'])) {
                continue;
            }

            [$type, $options] = self::parseColumn($tableName.'.'.$fieldName, $field['sql']);
            $table->addColumn((string) $fieldName, $type, $options);
        }

        foreach ($dca['config']['sql']['keys'] ?? [] as $columns => $kind) {
            $columnNames = array_map('trim', explode(',', (string) $columns));

            match ($kind) {
                'primary' => $table->setPrimaryKey($columnNames),
                'unique' => $table->addUniqueIndex($columnNames, self::indexName($tableName, $columnNames, 'uniq')),
                'index' => $table->addIndex($columnNames, self::indexName($tableName, $columnNames, 'idx')),
                default => throw new \LogicException(\sprintf('Unsupported key "%s" in %s.', $kind, $tableName)),
            };
        }

        return $table;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadDca(string $tableName): array
    {
        $file = __DIR__.'/../../contao/dca/'.$tableName.'.php';

        if (!is_file($file)) {
            throw new \LogicException(\sprintf('The DCA file "%s" does not exist.', $file));
        }

        // Load the file in isolation and restore the globals afterwards
        $backup = [$GLOBALS['TL_DCA'] ?? null, $GLOBALS['TL_LANG'] ?? null];
        $GLOBALS['TL_DCA'] = [];
        $GLOBALS['TL_LANG'] ??= [];

        try {
            require $file;

            return $GLOBALS['TL_DCA'][$tableName] ?? throw new \LogicException(\sprintf('%s does not define $GLOBALS[\'TL_DCA\'][\'%s\'].', $file, $tableName));
        } finally {
            [$GLOBALS['TL_DCA'], $GLOBALS['TL_LANG']] = $backup;

            if (null === $GLOBALS['TL_DCA']) {
                unset($GLOBALS['TL_DCA']);
            }

            if (null === $GLOBALS['TL_LANG']) {
                unset($GLOBALS['TL_LANG']);
            }
        }
    }

    /**
     * @param array<string, mixed>|string $sql
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function parseColumn(string $name, array|string $sql): array
    {
        if (\is_array($sql)) {
            $unknown = array_diff(array_keys($sql), ['type', ...self::COLUMN_OPTIONS]);

            if ([] !== $unknown) {
                throw new \LogicException(\sprintf('Unsupported column option(s) "%s" of %s.', implode('", "', $unknown), $name));
            }

            return [(string) $sql['type'], array_intersect_key($sql, array_flip(self::COLUMN_OPTIONS))];
        }

        return match (strtolower(trim($sql))) {
            'text null', 'mediumtext null' => [Types::TEXT, ['notnull' => false]],
            'blob null', 'mediumblob null' => [Types::BLOB, ['notnull' => false]],
            default => throw new \LogicException(\sprintf('Unsupported SQL definition "%s" of %s, extend %s::parseColumn().', $sql, $name, self::class)),
        };
    }

    /**
     * @param list<string> $columns
     */
    private static function indexName(string $tableName, array $columns, string $prefix): string
    {
        return $prefix.'_'.substr(md5($tableName.':'.implode(',', $columns)), 0, 16);
    }
}
