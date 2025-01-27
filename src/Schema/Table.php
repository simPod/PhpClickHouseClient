<?php

declare(strict_types=1);

namespace SimPod\ClickHouseClient\Schema;

use SimPod\ClickHouseClient\Sql\Escaper;

final readonly class Table
{
    public function __construct(
        public string $name,
        public string|null $database = null,
    ) {
    }

    public function fullName(): string
    {
        $escapedName = $this->name[0] === '`' && $this->name[-1] === '`'
            ? $this->name
            : Escaper::quoteIdentifier($this->name);

        if ($this->database === null) {
            return $escapedName;
        }

        $escapedDatabase = $this->database[0] === '`' && $this->database[-1] === '`'
            ? $this->database
            : Escaper::quoteIdentifier($this->database);

        return $escapedDatabase . '.' . $escapedName;
    }
}
