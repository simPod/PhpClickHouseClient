<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client;

use RuntimeException;
use SimPod\ClickHouseClient\Client\Native\Block;
use SimPod\ClickHouseClient\Client\Native\Column;
use SimPod\ClickHouseClient\Client\Native\Connection;
use SimPod\ClickHouseClient\Client\Native\ConnectionConfig;
use SimPod\ClickHouseClient\Client\Native\DecodeBudget;
use SimPod\ClickHouseClient\Client\Native\Result;
use SimPod\ClickHouseClient\Exception\CannotInsert;
use SimPod\ClickHouseClient\Exception\UnsupportedParamType;
use SimPod\ClickHouseClient\Exception\UnsupportedParamValue;
use SimPod\ClickHouseClient\Logger\SqlLogger;
use SimPod\ClickHouseClient\Param\ParamValueConverterRegistry;
use SimPod\ClickHouseClient\Schema\Table;
use SimPod\ClickHouseClient\Settings\EmptySettingsProvider;
use SimPod\ClickHouseClient\Settings\SettingsProvider;
use SimPod\ClickHouseClient\Sql\Escaper;
use SimPod\ClickHouseClient\Sql\NativeParameterParser;
use SimPod\ClickHouseClient\Sql\SqlFactory;
use SimPod\ClickHouseClient\Sql\Type;
use SimPod\ClickHouseClient\Sql\ValueFormatter;
use Throwable;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function assert;
use function count;
use function implode;
use function in_array;
use function is_bool;
use function is_scalar;
use function is_string;
use function stripcslashes;

/**
 * Synchronous native TCP client. Results are buffered, positional rows in column order.
 * The HTTP ClickHouseClient interface intentionally remains format-based.
 */
final class NativeClickHouseClient
{
    private Connection $connection;

    private SqlFactory $sqlFactory;

    public function __construct(
        ConnectionConfig $config,
        private readonly SqlLogger|null $sqlLogger = null,
        private readonly SettingsProvider $defaultSettings = new EmptySettingsProvider(),
        private readonly ParamValueConverterRegistry $paramValueConverterRegistry = new ParamValueConverterRegistry(),
        private readonly int $insertBlockSize = 10000,
    ) {
        if ($insertBlockSize < 1 || $insertBlockSize > 1000000) {
            throw new RuntimeException('Native insert block size must be between 1 and 1000000 rows.');
        }

        $this->connection = new Connection($config);
        $this->sqlFactory = new SqlFactory(new ValueFormatter());
    }

    public function executeQuery(string $query, SettingsProvider $settings = new EmptySettingsProvider()): void
    {
        $this->select($query, $settings);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public function executeQueryWithParams(
        string $query,
        array $params,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void {
        $this->selectWithParams($query, $params, $settings);
    }

    public function select(string $query, SettingsProvider $settings = new EmptySettingsProvider()): Result
    {
        return $this->runSelect($query, [], $settings);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    public function selectWithParams(
        string $query,
        array $params,
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): Result {
        $sql        = $this->sqlFactory->createWithParameters($query, $params);
        $parameters = $this->prepareParameters($sql, $params);

        return $this->runSelect($sql, $parameters, $settings);
    }

    /**
     * Sends binary column blocks using the schema returned by the server.
     * Values must be positional lists, in the requested column order.
     *
     * @param iterable<list<mixed>> $values
     * @param list<string> $columns
     *
     * @throws CannotInsert
     */
    public function insert(
        Table|string $table,
        iterable $values,
        array $columns = [],
        SettingsProvider $settings = new EmptySettingsProvider(),
    ): void {
        if (! $table instanceof Table) {
            if ($table === '') {
                throw new RuntimeException('Native insert table must not be empty.');
            }

            $table = new Table($table);
        }

        // @phpstan-ignore function.alreadyNarrowedType (Reject HTTP-style column maps from untyped callers.)
        if (! array_is_list($columns)) {
            throw new RuntimeException('Native insert columns must be a list of names.');
        }

        foreach ($columns as $column) {
            // @phpstan-ignore function.alreadyNarrowedType (PHP arrays do not enforce the documented element type.)
            if (! is_string($column) || $column === '') {
                throw new RuntimeException('Native insert column names must be non-empty strings.');
            }
        }

        $columnsSql = $columns === [] ? '' : '(' . implode(
            ',',
            array_map(Escaper::quoteIdentifier(...), $columns),
        ) . ')';
        $sql        = 'INSERT INTO ' . $table->fullName() . ' ' . $columnsSql . ' VALUES';
        $id         = $this->connection->startQuery($sql, $settings->get() + $this->defaultSettings->get(), []);

        try {
            $this->sqlLogger?->startQuery($id, $sql);
            $schema = $this->receiveInsertSchema();
            $batch  = [];
            $sent   = false;
            foreach ($values as $row) {
                // @phpstan-ignore function.alreadyNarrowedType (Never serialize associative caller rows as positional data.)
                if (! array_is_list($row) || count($row) !== count($schema->columns)) {
                    throw new RuntimeException('Native insert row must match the server schema in column order.');
                }

                $batch[] = $row;
                if (count($batch) < $this->insertBlockSize) {
                    continue;
                }

                $this->sendInsertBlock(new Block($schema->columns, $batch));
                $sent  = true;
                $batch = [];
            }

            if ($batch !== []) {
                $this->sendInsertBlock(new Block($schema->columns, $batch));
                $sent = true;
            }

            if (! $sent) {
                throw CannotInsert::noValues();
            }

            $this->sendInsertBlock(new Block());
            do {
                [$type] = $this->connection->receive();
            } while ($type !== 5);
        } catch (Throwable $error) {
            $this->connection->close();

            throw $error;
        } finally {
            $this->sqlLogger?->stopQuery($id);
        }
    }

    public function close(): void
    {
        $this->connection->close();
    }

    /** @param array<string, string> $parameters */
    private function runSelect(string $sql, array $parameters, SettingsProvider $settings): Result
    {
        $id = $this->connection->startQuery($sql, $settings->get() + $this->defaultSettings->get(), $parameters);

        try {
            $this->sqlLogger?->startQuery($id, $sql);
            $columns  = [];
            $rows     = [];
            $totals   = [];
            $extremes = [];
            $budget   = new DecodeBudget();
            while (true) {
                [$type, $block] = $this->connection->receive();
                if ($type === 5) {
                    return new Result($columns, $rows, $totals, $extremes);
                }

                if ($block === null || $block->columns === []) {
                    continue;
                }

                if ($type === 1) {
                    $budget->consume($block->estimatedBytes);
                    if ($columns === []) {
                        $columns = $block->columns;
                    } elseif (! $this->sameSchema($columns, $block->columns)) {
                        throw new RuntimeException('Native result schema changed between blocks.');
                    }

                    foreach ($block->rows as $row) {
                        $rows[] = $row;
                    }
                } elseif ($type === 7) {
                    $budget->consume($block->estimatedBytes);
                    $totals = $block->rows;
                } elseif ($type === 8) {
                    $budget->consume($block->estimatedBytes);
                    $extremes = $block->rows;
                }
            }
        } catch (Throwable $error) {
            $this->connection->close();

            throw $error;
        } finally {
            $this->sqlLogger?->stopQuery($id);
        }
    }

    private function receiveInsertSchema(): Block
    {
        while (true) {
            [$type, $block] = $this->connection->receive();
            if ($type === 1) {
                assert($block !== null);
                if ($block->columns === [] || $block->rows !== []) {
                    throw new RuntimeException('Expected a native insert schema block.');
                }

                return $block;
            }

            if ($type === 5) {
                throw new RuntimeException('Native insert ended without a schema block.');
            }
        }
    }

    private function sendInsertBlock(Block $block): void
    {
        $this->connection->sendBlock($block);
        // Revision 54456+ acknowledges each insert block with ProfileEvents.
        while (true) {
            [$type] = $this->connection->receive();
            if ($type === 14) {
                return;
            }

            if ($type === 5 || $type === 1) {
                throw new RuntimeException('Native insert ended before acknowledging the data block.');
            }
        }
    }

    /**
     * @param list<Column> $expected
     * @param list<Column> $actual
     */
    private function sameSchema(array $expected, array $actual): bool
    {
        if (count($expected) !== count($actual)) {
            return false;
        }

        foreach ($expected as $i => $column) {
            if ($column->name !== $actual[$i]->name || $column->type !== $actual[$i]->type) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     *
     * @throws UnsupportedParamType
     * @throws UnsupportedParamValue
     */
    private function prepareParameters(string $sql, array $params): array
    {
        $parameters = [];
        foreach (NativeParameterParser::parse($sql) as $name => $type) {
            if (! array_key_exists($name, $params)) {
                continue; // The server reports a missing parameter with its normal error code.
            }

            $value = $params[$name];
            if ($value === null) {
                $nullable = $type;
                while ($nullable->name === 'LowCardinality') {
                    $nullable = Type::fromString($nullable->params);
                }

                if ($nullable->name !== 'Nullable') {
                    throw UnsupportedParamValue::type($value);
                }

                $text = '\\N';
            } else {
                // Registry converters are an external value boundary and can return custom values.
                /** @var mixed $converted */
                $converted = $this->paramValueConverterRegistry->get($type)->__invoke($value, $type, false);
                if (! is_scalar($converted)) {
                    throw UnsupportedParamValue::type($converted);
                }

                $text = is_bool($converted) ? ($converted ? '1' : '0') : (string) $converted;
                $base = $type;
                while ($base->name === 'Nullable' || $base->name === 'LowCardinality') {
                    $base = Type::fromString($base->params);
                }

                if ($name === '_request_body' && in_array($base->name, ['String', 'FixedString'], true)) {
                    // The reserved parameter uses whole-text parsing, not escaped-text parsing.
                    $text = stripcslashes($text);
                } elseif ($name !== '_request_body' && in_array($base->name, ['Enum8', 'Enum16'], true)) {
                    // Enum converters return raw labels; scalar String converters already escape text.
                    $text = Escaper::escape($text);
                }
            }

            // Native parameters carry a String Field dump, not an HTTP param_* value.
            $parameters[$name] = "'" . Escaper::escape($text) . "'";
        }

        return $parameters;
    }
}
