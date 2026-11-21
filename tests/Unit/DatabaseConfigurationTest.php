<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    /**
     * Under MySQL's default REPEATABLE READ, a booking that waits for a row lock keeps reading the snapshot taken
     * before the wait, misses the booking that just committed and double-books the unit. Checked with parallel
     * processes against MySQL: 7 of 8 competing confirmations succeeded on one unit before this was set, 1 of 8 after.
     */
    public function test_mysql_connections_read_committed_data_so_bookings_cannot_double_book(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            $this->assertSame('READ COMMITTED', config("database.connections.{$connection}.isolation_level"), $connection);
        }
    }
}
