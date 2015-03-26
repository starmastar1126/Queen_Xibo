<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2015 Spring Signage Ltd
 *
 * This file (WidgetOption.php) is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace Xibo\Entity;


class WidgetOption
{
    public $widgetId;

    public $type;
    public $option;
    public $value;

    public function __toString()
    {
        if ($this->type == 'cdata') {
            return sprintf('%s WidgetOption %s', $this->type, $this->option);
        }
        else {
            return sprintf('%s WidgetOption %s with value %s', $this->type, $this->option, $this->value);
        }
    }

    public function save()
    {
        \Xibo\Helper\Log::debug('Saving ' . $this);

        $sql = 'INSERT INTO `widgetoption` (`widgetId`, `type`, `option`, `value`) VALUES (:widgetId, :type, :option, :value) ON DUPLICATE KEY UPDATE `value` = :value2';
        \Xibo\Storage\PDOConnect::insert($sql, array(
            'widgetId' => $this->widgetId,
            'type' => $this->type,
            'option' => $this->option,
            'value' => $this->value,
            'value2' => $this->value,
        ));
    }

    public function delete()
    {
        $sql = 'DELETE FROM `widgetoption` WHERE `widgetId` = :widgetId AND `option` = :option';
        \Xibo\Storage\PDOConnect::update($sql, array('widgetId' => $this->widgetId, 'option' => $this->option));
    }
}