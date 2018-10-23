<?php

namespace SimPod\ClickHouseClient\Tests\Client;

use PHPUnit\Framework\TestCase;
use SimPod\ClickHouseClient\Query\WhereInFile;
use SimPod\ClickHouseClient\Tests\WithClient;

/**
 * @group client
 * @group integration
 */
class InsertTest extends TestCase
{
    use WithClient;

    public function setUp()
    {
        date_default_timezone_set('Europe/Moscow');

        $this->client->ping();
    }

    public function testInsertNullable() : void
    {
        $this->client->write('DROP TABLE IF EXISTS `test`');
        $this->client->write('CREATE TABLE `test` (
                event_date Date DEFAULT toDate(event_time),
                event_time DateTime,
                url_hash Nullable(String)
        ) ENGINE = TinyLog()');

        $this->client->insert(
            'test',
            [
                [strtotime('2010-10-10 00:00:00'), null],
            ],
            ['event_time', 'url_hash']
        );

        $statement = $this->client->select('SELECT url_hash FROM `test`');

        self::assertSame(1, $statement->getRowsCount());
        self::assertCount(1, $statement->getRows());
        self::assertNull($statement->fetchFirstRow('url_hash'));
    }
}
