<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (DataSetTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboDataSet;
use Xibo\OAuth2\Client\Entity\XiboDataSetColumn;
use Xibo\OAuth2\Client\Entity\XiboDataSetRow;
use Xibo\Tests\LocalWebTestCase;

class DataSetTest extends LocalWebTestCase
{
    protected $startDataSets;
    
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {
        parent::setup();
        $this->startDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all datasets that weren't there initially
        $finalDataSets = (new XiboDataSet($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);

        $difference = array_udiff($finalDataSets, $this->startDataSets, function ($a, $b) {
            /** @var XiboDataSet $a */
            /** @var XiboDataSet $b */
            return $a->dataSetId - $b->dataSetId;
        });

        # Loop over any remaining datasets and nuke them
        foreach ($difference as $dataSet) {
            /** @var XiboDataSet $dataSet */
            try {
                $dataSet->deleteWData();
            } catch (\Exception $e) {
                fwrite(STDERR, 'Unable to delete ' . $dataSet->dataSetId . '. E: ' . $e->getMessage() . PHP_EOL);
            }
        }
        parent::tearDown();
    }

    /*
    * List all datasets
    */
    public function testListAll()
    {
        $this->client->get('/dataset');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * @group add
     * @return int
     */
    public function testAdd()
    {
        # Generate random name
        $name = Random::generateString(8, 'phpunit');
        # Add dataset
        $response = $this->client->post('/dataset', [
            'dataSet' => $name,
            'description' => 'PHP Unit Test'
        ]);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if dataset has the correct name
        $this->assertSame($name, $object->data->dataSet);
    }


    /**
     * Test edit
     * @depends testAdd
     */
    public function testEdit()
    {
        # Create a new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create('phpunit dataset', 'phpunit description');
        # Generate new name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'New description';
        # Edit the name and description
        $this->client->put('/dataset/' . $dataSet->dataSetId, [
            'dataSet' => $name,
            'description' => $description
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        # Check if name and description were correctly changed
        $this->assertSame($name, $object->data->dataSet);
        $this->assertSame($description, $object->data->description);
        # Deeper check by querying for dataset again
        $dataSetCheck = (new XiboDataSet($this->getEntityProvider()))->getById($object->id);
        $this->assertSame($name, $dataSetCheck->dataSet);
        $this->assertSame($description, $dataSetCheck->description);
        # Clean up the dataset as we no longer need it
        $dataSet->delete();
    }

    /**
     * @depends testEdit
     */
    public function testDelete()
    {
        # Generate new random names
        $name1 = Random::generateString(8, 'phpunit');
        $name2 = Random::generateString(8, 'phpunit');
        # Load in a couple of known dataSets
        $data1 = (new XiboDataSet($this->getEntityProvider()))->create($name1, 'phpunit description');
        $data2 = (new XiboDataSet($this->getEntityProvider()))->create($name2, 'phpunit description');
        # Delete the one we created last
        $this->client->delete('/dataset/' . $data2->dataSetId);
        # This should return 204 for success
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Check only one remains
        $dataSets = (new XiboDataSet($this->getEntityProvider()))->get();
        $this->assertEquals(count($this->startDataSets) + 1, count($dataSets));
        $flag = false;
        foreach ($dataSets as $dataSet) {
            if ($dataSet->dataSetId == $data1->dataSetId) {
                $flag = true;
            }
        }
        $this->assertTrue($flag, 'dataSet ID ' . $data1->dataSetId . ' was not found after deleting a different dataset');
    }

    # TO DO /dataset/import/

    /**
     * @dataProvider provideSuccessCases
     */
    public function testAddColumnSuccess($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {
        # Create radom name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add';
        # Create new dataset
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Create new columns with arguments from provideSuccessCases
        $response = $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);
        # Check that call was successful
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        # Check that columns have correct parameters
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        $this->assertSame($columnName, $object->data->heading);
        $this->assertSame($columnListContent, $object->data->listContent);
        $this->assertSame($columnOrd, $object->data->columnOrder);
        $this->assertSame($columnDataTypeId, $object->data->dataTypeId);
        $this->assertSame($columnDataSetColumnTypeId, $object->data->dataSetColumnTypeId);
        $this->assertSame($columnFormula, $object->data->formula);
        # Check that column was correctly added
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->getById($dataSet->dataSetId, $object->id);
        $this->assertSame($columnName, $column->heading);
        # Clean up the dataset as we no longer need it
        $this->assertTrue($dataSet->delete(), 'Unable to delete ' . $dataSet->dataSetId);
    }

    /**
     * Each array is a test run
     * Format ($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
     * @return array
     */

    public function provideSuccessCases()
    {
        # Cases we provide to testAddColumnSucess, you can extend it by simply adding new case here
        return [
            # Value
            'Value String' => ['Test Column Value String', NULL, 2, 1, 1, NULL],
            'List Content' => ['Test Column list content', 'one,two,three', 2, 1, 1, NULL],
            'Value Number' => ['Test Column Value Number', NULL, 2, 2, 1, NULL],
            'Value Date' => ['Test Column Value Date', NULL, 2, 3, 1, NULL],
            'External Image' => ['Test Column Value External Image', NULL, 2, 4, 1, NULL],
            'Library Image' => ['Test Column Value Internal Image', NULL, 2, 5, 1, NULL],
            # Formula
            'Formula' => ['Test Column Formula', NULL, 2, 5, 1, 'Where Name = Dan'],
        ];
    }

    /**
     * @dataProvider provideFailureCases
     */
    public function testAddColumnFailure($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
    {
        # Create random name and description
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column add failure';
        # Create new columns that we expect to fail with arguments from provideFailureCases
        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        $this->client->post('/dataset/' . $dataSet->dataSetId . '/column', [
            'heading' => $columnName,
            'listContent' => $columnListContent,
            'columnOrder' => $columnOrd,
            'dataTypeId' => $columnDataTypeId,
            'dataSetColumnTypeId' => $columnDataSetColumnTypeId,
            'formula' => $columnFormula
        ]);
        # Check if cases are failing as expected
        $this->assertSame(500, $this->client->response->status(), 'Expecting failure, received ' . $this->client->response->status());
    }

    /**
     * Each array is a test run
     * Format ($columnName, $columnListContent, $columnOrd, $columnDataTypeId, $columnDataSetColumnTypeId, $columnFormula)
     * @return array
     */

    public function provideFailureCases()
    {
        # Cases we provide to testAddColumnFailure, you can extend it by simply adding new case here
        return [
            // Value
            'Incorrect dataType' => ['incorrect data type', NULL, 2, 12, 1, NULL],     
            'Incorrect columnType' => ['incorrect column type', NULL, 2, 19, 1, NULL],   
            'Empty Name' => [NULL, NULL, 2, 3, 1, NULL]
        ];
    }

    /**
     * Search columns for DataSet
     */
    public function testListAllColumns()
    {
        # Create new dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column list';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add a new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $dataSet->createColumn($nameCol,'', 2, 1, 1, '');
        # Search for columns
        $this->client->get('/dataset/' . $dataSet->dataSetId . '/column');
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # Clean up as we no longer need it
        $dataSet->delete();
    }

    /**
     * Test edit column
     */
    public function testColumnEdit()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Generate new random name
        $nameNew = Random::generateString(8, 'phpunit');
        # Edit our column and change the name
        $response = $this->client->put('/dataset/' . $dataSet->dataSetId . '/column/' . $column->dataSetColumnId, [
            'heading' => $nameNew,
            'listContent' => '',
            'columnOrder' => $column->columnOrder,
            'dataTypeId' => $column->dataTypeId,
            'dataSetColumnTypeId' => $column->dataSetColumnTypeId,
            'formula' => ''
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Check if our column has updated name
        $this->assertSame($nameNew, $object->data->heading);
        # Clean up as we no longer need it
        $dataSet->delete();
    }

    /**
     * @param $dataSetId
     * @depends testAddColumnSuccess
     */
    public function testDeleteColumn()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit column delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Add new column to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # delete column
        $response = $this->client->delete('/dataset/' . $dataSet->dataSetId . '/column/' . $column->dataSetColumnId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /*
    * GET data
    */

    public function testGetData()
    {
        # Create dataSet
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Call get data
        $this->client->get('/dataset/data/' . $dataSet->dataSetId);
        # Check if call was successful
        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
        # Clean up
        $dataSet->delete();
    }
    
    /**
     * Test add row
     */
    public function testRowAdd()
    {
        # Create a new dataset to use
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row add';
        /** @var XiboDataSet $dataSet */
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Create column and add it to our dataset
        $nameCol = Random::generateString(8, 'phpunit');
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row to our dataset and column
        $response = $this->client->post('/dataset/data/' . $dataSet->dataSetId, [
            'dataSetColumnId_' . $column->dataSetColumnId => 'test',
            ]);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # Get the row id
        $row = $dataSet->getDataByRowId($object->id);
        # Check if data was correctly added to the row
        $this->assertArrayHasKey($nameCol, $row);
        $this->assertSame($row[$nameCol], 'test');
        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet->deleteWData();
    }
    /**
     * Test edit row
     */
    public function testRowEdit()
    {
        # Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row edit';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        # Create new column and add it to our dataset
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row with data to our dataset
        $row = $dataSet->createRow($column->dataSetColumnId, 'test');
        $rowCheck = $dataSet->getDataByRowId($row['id']);
        # Edit row data
        $response = $this->client->put('/dataset/data/' . $dataSet->dataSetId . '/' . $row['id'], [
            'dataSetColumnId_' . $column->dataSetColumnId => 'API EDITED'
            ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $this->assertSame(200, $this->client->response->status(), "Not successful: " . $response);
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertObjectHasAttribute('id', $object);
        # get the row id
        $rowCheck = $dataSet->getDataByRowId($object->id);
        # Check if data was correctly added to the row
        $this->assertArrayHasKey($nameCol, $rowCheck);
        $this->assertSame($rowCheck[$nameCol], 'API EDITED');
        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet -> deleteWData();
    }

    /*
    * delete row data
    */
    public function testRowDelete()
    {
        # Create a new dataset to use
        /** @var XiboDataSet $dataSet */
        $name = Random::generateString(8, 'phpunit');
        $description = 'PHP Unit row delete';
        $dataSet = (new XiboDataSet($this->getEntityProvider()))->create($name, $description);
        # Generate a new name for the new column
        $nameCol = Random::generateString(8, 'phpunit');
        # Create new column and add it to our dataset
        $column = (new XiboDataSetColumn($this->getEntityProvider()))->create($dataSet->dataSetId, $nameCol,'', 2, 1, 1, '');
        # Add new row data
        $row = $dataSet->createRow($column->dataSetColumnId, 'test');
        $rowCheck = $dataSet->getDataByRowId($row['id']);
        # Delete row
        $this->client->delete('/dataset/data/' . $dataSet->dataSetId . '/' . $row['id']);
        $response = json_decode($this->client->response->body());
        $this->assertSame(204, $response->status, $this->client->response->body());
        # Clean up as we no longer need it, deleteWData will delete dataset even if it has data assigned to it
        $dataSet -> deleteWData();
    }
}
