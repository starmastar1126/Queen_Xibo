<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetData.php)
 */


namespace Xibo\Controller;


//use Xibo\Entity\DataSetColumn;
use Xibo\Exception\AccessDeniedException;
use Xibo\Factory\DataSetFactory;
use Xibo\Factory\MediaFactory;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Service\DateServiceInterface;
use Xibo\Service\LogServiceInterface;
use Xibo\Service\SanitizerServiceInterface;

/**
 * Class DataSetData
 * @package Xibo\Controller
 */
class DataSetData extends Base
{
    /** @var  DataSetFactory */
    private $dataSetFactory;

    /** @var  MediaFactory */
    private $mediaFactory;

    /**
     * Set common dependencies.
     * @param LogServiceInterface $log
     * @param SanitizerServiceInterface $sanitizerService
     * @param \Xibo\Helper\ApplicationState $state
     * @param \Xibo\Entity\User $user
     * @param \Xibo\Service\HelpServiceInterface $help
     * @param DateServiceInterface $date
     * @param ConfigServiceInterface $config
     * @param DataSetFactory $dataSetFactory
     * @param MediaFactory $mediaFactory
     */
    public function __construct($log, $sanitizerService, $state, $user, $help, $date, $config, $dataSetFactory, $mediaFactory)
    {
        $this->setCommonDependencies($log, $sanitizerService, $state, $user, $help, $date, $config);

        $this->dataSetFactory = $dataSetFactory;
        $this->mediaFactory = $mediaFactory;
    }

    /**
     * Display Page
     * @param $dataSetId
     */
    public function displayPage($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Load data set
        $dataSet->load();

        $this->getState()->template = 'dataset-dataentry-page';
        $this->getState()->setData([
            'dataSet' => $dataSet
        ]);
    }

    /**
     * Grid
     * @param $dataSetId
     *
     * @SWG\Get(
     *  path="/dataset/data/{dataSetId}",
     *  operationId="dataSetData",
     *  tags={"dataset"},
     *  summary="DataSet Data",
     *  description="Get Data for DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function grid($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $sorting = $this->gridRenderSort();

        if ($sorting != null)
            $sorting = implode(',', $sorting);

        // Work out the limits
        $filter = $this->gridRenderFilter(['filter' => $this->getSanitizer()->getParam('filter', null)]);

        $this->getState()->template = 'grid';
        $this->getState()->setData($dataSet->getData([
            'order' => $sorting,
            'start' => $filter['start'],
            'size' => $filter['length'],
            'filter' => $filter['filter']
        ]));

        // Output the count of records for paging purposes
        if ($dataSet->countLast() != 0)
            $this->getState()->recordsTotal = $dataSet->countLast();
    }

    /**
     * Add Form
     * @param int $dataSetId
     */
    public function addForm($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-add';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'images' => $this->mediaFactory->query(null, ['type' => 'image'])
        ]);
    }

    /**
     * Add
     * @param int $dataSetId
     *
     * @SWG\Post(
     *  path="/dataset/data/{dataSetId}",
     *  operationId="dataSetDataAdd",
     *  tags={"dataset"},
     *  summary="Add Row",
     *  description="Add a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId_ID",
     *      in="formData",
     *      description="Parameter for each dataSetColumnId in the DataSet",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=201,
     *      description="successful operation",
     *      @SWG\Header(
     *          header="Location",
     *          description="Location of the new record",
     *          type="string"
     *      )
     *  )
     * )
     */
    public function add($dataSetId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */
            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = $this->getSanitizer()->getDouble('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = $this->getDate()->getLocalDate($this->getSanitizer()->getDate('dataSetColumnId_' . $column->dataSetColumnId));
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = $this->getSanitizer()->getInt('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else {
                    // String
                    $value = $this->getSanitizer()->getString('dataSetColumnId_' . $column->dataSetColumnId);
                }

                $row[$column->heading] = $value;
            }
        }

        // Use the data set object to add a row
        $rowId = $dataSet->addRow($row);


        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 201,
            'message' => __('Added Row'),
            'id' => $rowId
        ]);
    }

    /**
     * Edit Form
     * @param $dataSetId
     * @param $rowId
     */
    public function editForm($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-edit';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $dataSet->getData(['id' => $rowId])[0],
            'images' => $this->mediaFactory->query(null, ['type' => 'image'])
        ]);
    }

    /**
     * Edit Row
     * @param int $dataSetId
     * @param int $rowId
     *
     * @SWG\Put(
     *  path="/dataset/data/{dataSetId}/{rowId}",
     *  operationId="dataSetDataEdit",
     *  tags={"dataset"},
     *  summary="Edit Row",
     *  description="Edit a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rowId",
     *      in="path",
     *      description="The Row ID of the Data to Edit",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="dataSetColumnId_ID",
     *      in="formData",
     *      description="Parameter for each dataSetColumnId in the DataSet",
     *      type="string",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=200,
     *      description="successful operation"
     *  )
     * )
     */
    public function edit($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $existingRow = $dataSet->getData(['id' => $rowId])[0];
        $row = [];

        // Expect input for each value-column
        foreach ($dataSet->getColumn() as $column) {
            /* @var \Xibo\Entity\DataSetColumn $column */

            $existingValue = $this->getSanitizer()->getParam($column->heading, null, $existingRow);

            if ($column->dataSetColumnTypeId == 1) {

                // Sanitize accordingly
                if ($column->dataTypeId == 2) {
                    // Number
                    $value = $this->getSanitizer()->getDouble('dataSetColumnId_' . $column->dataSetColumnId, $existingValue);
                }
                else if ($column->dataTypeId == 3) {
                    // Date
                    $value = $this->getDate()->getLocalDate($this->getSanitizer()->getDate('dataSetColumnId_' . $column->dataSetColumnId, $existingValue));
                }
                else if ($column->dataTypeId == 5) {
                    // Media Id
                    $value = $this->getSanitizer()->getInt('dataSetColumnId_' . $column->dataSetColumnId);
                }
                else {
                    // String
                    $value = $this->getSanitizer()->getString('dataSetColumnId_' . $column->dataSetColumnId, $existingValue);
                }

                $row[$column->heading] = $value;
            }
        }

        // Use the data set object to add a row
        $dataSet->editRow($rowId, $row);

        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'message' => __('Edited Row'),
            'id' => $rowId
        ]);
    }

    /**
     * Delete Form
     * @param int $dataSetId
     * @param int $rowId
     */
    public function deleteForm($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        $dataSet->load();

        $this->getState()->template = 'dataset-data-form-delete';
        $this->getState()->setData([
            'dataSet' => $dataSet,
            'row' => $dataSet->getData(['id' => $rowId])[0]
        ]);
    }

    /**
     * Delete Row
     * @param $dataSetId
     * @param $rowId
     *
     * @SWG\Delete(
     *  path="/dataset/data/{dataSetId}/{rowId}",
     *  operationId="dataSetDataDelete",
     *  tags={"dataset"},
     *  summary="Delete Row",
     *  description="Delete a row of Data to a DataSet",
     *  @SWG\Parameter(
     *      name="dataSetId",
     *      in="path",
     *      description="The DataSet ID",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Parameter(
     *      name="rowId",
     *      in="path",
     *      description="The Row ID of the Data to Delete",
     *      type="integer",
     *      required=true
     *   ),
     *  @SWG\Response(
     *      response=204,
     *      description="successful operation"
     *  )
     * )
     */
    public function delete($dataSetId, $rowId)
    {
        $dataSet = $this->dataSetFactory->getById($dataSetId);

        if (!$this->getUser()->checkEditable($dataSet))
            throw new AccessDeniedException();

        // Delete the row
        $dataSet->deleteRow($rowId);

        // Save the dataSet
        $dataSet->save(['validate' => false, 'saveColumns' => false]);

        // Return
        $this->getState()->hydrate([
            'httpStatus' => 204,
            'message' => __('Deleted Row'),
            'id' => $rowId
        ]);
    }
}