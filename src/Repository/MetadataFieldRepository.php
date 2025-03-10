<?php

declare(strict_types=1);

/**
 * vim:set softtabstop=3 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2023
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace Ampache\Repository;

use Ampache\Module\Database\DatabaseConnectionInterface;
use Generator;
use PDO;

/**
 * Manages song metadata-fields related database access
 *
 * Tables: `metadata_field`
 */
final class MetadataFieldRepository implements MetadataFieldRepositoryInterface
{
    private DatabaseConnectionInterface $connection;

    public function __construct(
        DatabaseConnectionInterface $connection
    ) {
        $this->connection = $connection;
    }

    /**
     * Remove metadata for songs which don't exist anymore
     */
    public function collectGarbage(): void
    {
        $this->connection->query('DELETE FROM `metadata_field` USING `metadata_field` LEFT JOIN `metadata` ON `metadata`.`field` = `metadata_field`.`id` WHERE `metadata`.`id` IS NULL;');
    }

    /**
     * Returns the list of available fields
     *
     * Key is the primary key, value the name
     *
     * @return Generator<int, string>
     */
    public function getPropertyList(): Generator
    {
        $result = $this->connection->query('SELECT `id`, `name` from metadata_field');

        while ($data = $result->fetch(PDO::FETCH_ASSOC)) {
            yield (int) $data['id'] => $data['name'];
        }
    }
}
