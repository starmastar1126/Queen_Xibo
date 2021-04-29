<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

namespace Xibo\Report;

use Psr\Container\ContainerInterface;
use Xibo\Entity\ReportForm;
use Xibo\Entity\ReportResult;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Interface ReportInterface
 * @package Xibo\Report
 */
interface ReportInterface
{
    /**
     * Set factories
     * @param ContainerInterface $container
     * @return $this
     */
    public function setFactories(ContainerInterface $container);

    /**
     * Get chart script
     * @param ReportResult $results
     * @return string
     */
    public function getReportChartScript($results);

    /**
     * Return the twig file name of the report email template
     * @return string
     */
    public function getReportEmailTemplate();

    /**
     * Return the twig file name of the saved report template
     * @return string
     */
    public function getSavedReportTemplate();

    /**
     * Return the twig file name of the report form
     * Load the report form
     * @return ReportForm
     */
    public function getReportForm();

    /**
     * Populate form title and hidden fields
     * @param SanitizerInterface $sanitizedParams
     * @return array
     */
    public function getReportScheduleFormData(SanitizerInterface $sanitizedParams);

    /**
     * Set Report Schedule form data
     * @param SanitizerInterface $sanitizedParams
     * @return array
     */
    public function setReportScheduleFormData(SanitizerInterface $sanitizedParams);

    /**
     * Generate saved report name
     * @param SanitizerInterface $sanitizedParams
     * @return string
     */
    public function generateSavedReportName(SanitizerInterface $sanitizedParams);

    /**
     * Resrtucture old saved report's json file to support schema version 2
     * @param $json
     * @return array
     */
    public function restructureSavedReportOldJson($json);

    /**
     * Return data to build chart of saved report
     * @param array $json
     * @param object $savedReport
     * @return ReportResult
     */
    public function getSavedReportResults($json, $savedReport);

    /**
     * Get results
     * @param SanitizerInterface $sanitizedParams
     * @return ReportResult
     */
    public function getResults(SanitizerInterface $sanitizedParams);
}
