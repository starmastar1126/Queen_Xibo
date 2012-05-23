<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2012 Daniel Garner
 *
 * This file is part of Xibo.
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

class Maintenance extends Data
{
    /**
     * Backup the Database
     * @param <string> $saveAs file|string
     */
    public function BackupDatabase($saveAs = "string")
    {
        // Always truncate the log first
        $this->db->query("TRUNCATE TABLE `log` ");

        global $dbhost;
        global $dbuser;
        global $dbpass;
        global $dbname;

        // Run mysqldump with output buffering on
        ob_start();
        
        passthru('mysqldump --opt --host=' . $dbhost . ' --user=' . $dbuser . ' --password=' . $dbpass . ' ' . $dbname);

        $sqlDump = ob_get_contents();

        ob_end_clean();

        return $sqlDump;
    }
}
?>
