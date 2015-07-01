<?php
/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2013 Daniel Garner
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
namespace Xibo\Controller;
use DateInterval;
use DateTime;
use Xibo\Factory\AuditLogFactory;
use Xibo\Helper\Date;
use Xibo\Helper\Help;
use Xibo\Helper\Sanitize;

class AuditLog extends Base
{
    public function displayPage()
    {
        // Construct Filter Form
        if ($this->getSession()->get('auditlog', 'Filter') == 1) {
            $filter_pinned = 1;
            $filterFromDt = $this->getSession()->get('auditlog', 'filterFromDt');
            $filterToDt = $this->getSession()->get('auditlog', 'filterToDt');
            $filterUser = $this->getSession()->get('auditlog', 'filterUser');
            $filterEntity = $this->getSession()->get('auditlog', 'filterEntity');
        } else {
            $filter_pinned = 0;
            $filterFromDt = NULL;
            $filterToDt = NULL;
            $filterUser = NULL;
            $filterEntity = NULL;
        }

        $data = [
            'defaults' => [
                'filterPinned' => $filter_pinned,
                'fromDt' => $filterFromDt,
                'toDt' => $filterToDt,
                'user' => $filterUser,
                'entity' => $filterEntity
            ]
        ];

        $this->getState()->template = 'auditlog-page';
        $this->getState()->setData($data);
    }

    function grid()
    {
        $this->getSession()->set('auditlog', 'Filter', Sanitize::getCheckbox('XiboFilterPinned'));
        $filterFromDt = $this->getSession()->set('auditlog', 'filterFromDt', Sanitize::getString('filterFromDt'));
        $filterToDt = $this->getSession()->set('auditlog', 'filterToDt', Sanitize::getString('filterToDt'));
        $filterUser = $this->getSession()->set('auditlog', 'filterUser', Sanitize::getString('filterUser'));
        $filterEntity = $this->getSession()->set('auditlog', 'filterEntity', Sanitize::getString('filterEntity'));

        $search = [];

        // Get the dates and times
        if ($filterFromDt == '') {
            $fromTimestamp = (new DateTime())->sub(new DateInterval("P1D"));
        } else {
            $fromTimestamp = DateTime::createFromFormat('Y-m-d', $filterFromDt);
            $fromTimestamp->setTime(0, 0, 0);
        }

        if ($filterToDt == '') {
            $toTimestamp = (new DateTime());
        } else {
            $toTimestamp = DateTime::createFromFormat('Y-m-d', $filterToDt);
            $toTimestamp->setTime(0, 0, 0);
        }

        $search[] = ['fromTimeStamp', $fromTimestamp->format('U')];
        $search[] = ['toTimeStamp', $toTimestamp->format('U')];

        if ($filterUser != '')
            $search[] = ['userName', $filterUser];

        if ($filterEntity != '')
            $search[] = ['entity', $filterEntity];

        // Build the search string
        $search = implode(' ', array_map(function ($element) {
            return implode('|', $element);
        }, $search));

        $rows = AuditLogFactory::query($this->gridRenderSort(), $this->gridRenderFilter(['search' => $search]));

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            $row->logDate = Date::getLocalDate($row->logDate);
            $row->objectAfter = json_decode($row->objectAfter);
        }

        $this->getState()->template = 'grid';
        $this->getState()->recordsTotal = AuditLogFactory::countLast();
        $this->getState()->setData($rows);
    }

    /**
     * Output CSV Form
     */
    public function exportForm()
    {
        $this->getState()->template = 'auditlog-form-export';
        $this->getState()->setData([
            'help' => Help::Link('AuditLog', 'Export')
        ]);
    }

    /**
     * Outputs a CSV of audit trail messages
     */
    public function export()
    {
        // We are expecting some parameters
        $filterFromDt = Sanitize::getString('filterFromDt');
        $filterToDt = Sanitize::getString('filterToDt');

        $fromTimestamp = Date::fromString($filterFromDt);
        $fromTimestamp->setTime(0, 0, 0);
        $toTimestamp = Date::fromString($filterToDt);
        $toTimestamp->setTime(0, 0, 0);

        $search = [
            ['fromTimeStamp', $fromTimestamp->format('U')],
            ['toTimeStamp', $toTimestamp->format('U')]
        ];

        // Build the search string
        $search = implode(' ', array_map(function ($element) {
            return implode('|', $element);
        }, $search));

        $rows = AuditLogFactory::query('logId', ['search' => $search]);

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Date', 'User', 'Entity', 'Message', 'Object']);

        // Do some post processing
        foreach ($rows as $row) {
            /* @var \Xibo\Entity\AuditLog $row */
            fputcsv($out, [$row->logId, Date::getLocalDate($row->logDate), $row->userName, $row->entity, $row->message, $row->objectAfter]);
        }

        fclose($out);

        // We want to output a load of stuff to the browser as a text file.
        $app = $this->getApp();
        $app->response()->header('Content-Type', 'text/csv');
        $app->response()->header('Content-Disposition', 'attachment; filename="audittrail.csv"');
        $app->response()->header('Content-Transfer-Encoding', 'binary"');
        $app->response()->header('Accept-Ranges', 'bytes');
        $this->setNoOutput(true);
    }
}
