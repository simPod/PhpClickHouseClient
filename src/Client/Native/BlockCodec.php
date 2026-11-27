<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Client\Native;

use RuntimeException;

use function array_is_list;
use function count;
use function ord;
use function strlen;

/** @internal */
final readonly class BlockCodec
{
    private TypeCodec $types;

    public function __construct(private int $revision)
    {
        $this->types = new TypeCodec();
    }

    public function read(BinaryStream $stream): Block
    {
        if ($this->revision > 0) {
            $this->readInfo($stream);
        }

        $columnCount = $stream->readVarUInt();
        $rowCount    = $stream->readVarUInt();
        $this->validateSize($columnCount, $rowCount);
        $budget = new DecodeBudget();
        $budget->consume($rowCount * 256 + $columnCount * 256);

        $columns = [];
        $rows    = [];
        for ($row = 0; $row < $rowCount; $row++) {
            $rows[] = [];
        }

        for ($column = 0; $column < $columnCount; $column++) {
            $name = $stream->readString(65536);
            $type = $stream->readString(4096);
            $budget->consume(strlen($name) + strlen($type));
            if ($this->revision >= 54454 && ord($stream->read(1)) !== 0) {
                throw new RuntimeException('Native custom serialization is not supported for type ' . $type . '.');
            }

            $columns[] = new Column($name, $type);
            foreach ($this->types->read($stream, $type, $rowCount, $budget) as $row => $value) {
                $rows[$row][] = $value;
            }
        }

        return new Block($columns, $rows, $budget->used);
    }

    public function write(BinaryStream $stream, Block $block): void
    {
        $columnCount = count($block->columns);
        $rowCount    = count($block->rows);
        $this->validateSize($columnCount, $rowCount);
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType, booleanOr.alwaysFalse (Validate caller arrays before writing protocol bytes.)
        if (! array_is_list($block->columns) || ! array_is_list($block->rows)) {
            throw new RuntimeException('Native block columns and rows must be lists.');
        }

        foreach ($block->rows as $row) {
            // @phpstan-ignore function.alreadyNarrowedType (PHP does not enforce list PHPDoc types at runtime.)
            if (! array_is_list($row) || count($row) !== $columnCount) {
                throw new RuntimeException('Native block row does not match its column count.');
            }
        }

        if ($this->revision > 0) {
            $stream->write("\x01\x00\x02\xff\xff\xff\xff\x00");
        }

        $stream->writeVarUInt($columnCount);
        $stream->writeVarUInt($rowCount);
        foreach ($block->columns as $index => $column) {
            $stream->writeString($column->name);
            $stream->writeString($column->type);
            if ($this->revision >= 54454) {
                $stream->write("\x00");
            }

            $values = [];
            foreach ($block->rows as $row) {
                $values[] = $row[$index];
            }

            $this->types->write($stream, $column->type, $values);
        }
    }

    private function readInfo(BinaryStream $stream): void
    {
        $seen = [];
        while (($field = $stream->readVarUInt()) !== 0) {
            if (isset($seen[$field])) {
                throw new RuntimeException('Duplicate native BlockInfo field.');
            }

            $seen[$field] = true;
            switch ($field) {
                case 1:
                    if (ord($stream->read(1)) > 1) {
                        throw new RuntimeException('Invalid native BlockInfo overflow flag.');
                    }

                    break;
                case 2:
                    $stream->read(4);

                    break;
                default:
                    throw new RuntimeException('Unsupported native BlockInfo field ' . $field . '.');
            }
        }
    }

    private function validateSize(int $columns, int $rows): void
    {
        if ($columns > 1024 || $rows > 1000000 || ($columns === 0 && $rows !== 0)) {
            throw new RuntimeException('Native block exceeds column or row limits.');
        }
    }
}
