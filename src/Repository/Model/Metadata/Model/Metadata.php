<?php

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
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

namespace Ampache\Repository\Model\Metadata\Model;

use Ampache\Repository\Model\DatabaseObject;
use Ampache\Repository\Model\Model;

class Metadata extends DatabaseObject implements Model
{
    /**
     * Database ID
     * @var int
     */
    protected $id;

    /**
     * A library item like song or video //TODO why are there two?
     * @var int
     */
    protected $object_id;

    /**
     * A library item like song or video
     * @var int
     */
    protected $objectId;

    /**
     * Tag Field
     * @var MetadataField
     */
    protected $field;

    /**
     * Tag Data
     * @var string
     */
    protected $data;

    /**
     * @var string
     */
    protected $type;

    /**
     * Stores relation between SQL field name and repository class name so we can initialize objects the right way
     * @var array
     */
    protected $fieldClassRelations = array(
        'field' => \Ampache\Repository\Model\Metadata\Repository\MetadataField::class
    );

    /**
     *
     * getObjectId
     */
    public function getObjectId(): int
    {
        return $this->objectId;
    }

    /**
     *
     * @return MetadataField
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * getData
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * setObjectId
     * @param int $objectId
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
    }

    /**
     *
     * @param MetadataField $field
     */
    public function setField(MetadataField $field)
    {
        $this->field = $field;
    }

    /**
     *
     * @param string $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * getType
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}
