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

namespace Ampache\Repository\Model;

use Ampache\Module\Util\ObjectTypeToClassNameMapper;
use Ampache\Repository\Repository;

/**
 * Description of Model
 *
 * @author raziel
 */
abstract class DatabaseObject
{
    protected $id;
    //private $originalData;

    /**
     * Stores relation between SQL field name and class name so we can initialize objects the right way
     * @var array
     */
    protected $fieldClassRelations = array();

    public function __construct()
    {
        $this->remapCamelcase();
        $this->initializeChildObjects();
        //$this->originalData = get_object_vars($this);
    }

    public function getId(): int
    {
        return (int)($this->id ?? 0);
    }

    //protected function isPropertyDirty($property)
    //{
    //    return $this->originalData->$property !== $this->$property;
    //}

    /**
     * isDirty
     */
    public function isDirty(): bool
    {
        return true;
    }

    /**
     * Get all changed properties
     * TODO: we get all properties for now...need more logic here...
     * @return array
     */
    public function getDirtyProperties(): array
    {
        $properties = get_object_vars($this);
        unset($properties['id']);
        unset($properties['fieldClassRelations']);

        return $this->fromCamelCase($properties);
    }

    /**
     * Convert the object properties to camelCase.
     * This works in constructor because the properties are here from
     * fetch_object before the constructor get called.
     */
    protected function remapCamelcase(): void
    {
        foreach (get_object_vars($this) as $key => $val) {
            if (strpos($key, '_') !== false) {
                $camelCaseKey        = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
                $this->$camelCaseKey = $val;
                unset($this->$key);
            }
        }
    }

    /**
     * @param $properties
     * @return array
     */
    protected function fromCamelCase($properties): array
    {
        $data = array();
        foreach ($properties as $propertie => $value) {
            $newPropertyKey        = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $propertie));
            $data[$newPropertyKey] = $value;
        }

        return $data;
    }

    /**
     * Adds child Objects based of the Model Information
     * TODO: Someday we might need lazy loading, but for now it should be ok.
     */
    public function initializeChildObjects(): void
    {
        foreach ($this->fieldClassRelations as $field => $repositoryName) {
            if (class_exists($repositoryName)) {
                $className = ObjectTypeToClassNameMapper::map($repositoryName);
                /** @var Repository $repository */
                $repository   = new $className();
                $this->$field = $repository->findById($this->$field);
            }
        }
    }
}
