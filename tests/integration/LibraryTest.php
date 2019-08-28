<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (LibraryTest.php)
 */

namespace Xibo\Tests\Integration;

use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\Tests\LocalWebTestCase;

class LibraryTest extends LocalWebTestCase
{
    protected $startMedias;
    protected $mediaName;
    protected $mediaType;
    protected $mediaId;
    /**
     * setUp - called before every test automatically
     */
    public function setup()
    {  
        parent::setup();
        $this->startMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
    }
    /**
     * tearDown - called after every test automatically
     */
    public function tearDown()
    {
        // tearDown all media files that weren't there initially
        $finalMedias = (new XiboLibrary($this->getEntityProvider()))->get(['start' => 0, 'length' => 10000]);
        # Loop over any remaining media files and nuke them
        foreach ($finalMedias as $media) {
            /** @var XiboLibrary $media */
            $flag = true;
            foreach ($this->startMedias as $startMedia) {
               if ($startMedia->mediaId == $media->mediaId) {
                   $flag = false;
               }
            }
            if ($flag) {
                try {
                    $media->deleteAssigned();
                } catch (\Exception $e) {
                    fwrite(STDERR, 'Unable to delete ' . $media->mediaId . '. E:' . $e->getMessage());
                }
            }
        }
        parent::tearDown();
    }

    /**
     * List all file in library
     */
    public function testListAll()
    {
        # Get all library items
        $this->client->get('/library');

        $this->assertSame(200, $this->client->response->status());
        $this->assertNotEmpty($this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object, $this->client->response->body());
    }

    /**
     * Add new file to library
     */
    public function testAdd()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video test', PROJECT_ROOT . '/tests/resources/HLH264.mp4');

        $media->delete();
    }

    /**
     * Add new file to library and replace old one in all layouts
     */
    public function testReplace()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('flowers', PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');
        # Replace the image and update it in all layouts (name, file location, old media id, replace in all layouts flag, delete old revision flag)
        $media2 = (new XiboLibrary($this->getEntityProvider()))->create('API replace image', PROJECT_ROOT . '/tests/resources/xts-flowers-002.jpg',  $media->mediaId, 1, 1);
    }

    /**
     * try to add not allowed filetype
     */
    public function testAddEmpty()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $this->setExpectedException('\Xibo\OAuth2\Client\Exception\XiboApiException');

        $media = (new XiboLibrary($this->getEntityProvider()))->create('API incorrect file 2', PROJECT_ROOT . '/tests/resources/empty.txt');
    }

    /**
     * Add tags to media
     */
    public function testAddTag()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('flowers 2', PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');

        $this->client->post('/library/' . $media->mediaId . '/tag', [
            'tag' => ['API']
            ]);
        $media = (new XiboLibrary($this->getEntityProvider()))->getById($media->mediaId);
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame('API', $media->tags);
        $media->delete();
    }

    /**
     * Delete tags from media
     * @group broken
     */
    public function testDeleteTag()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('flowers', PROJECT_ROOT . '/tests/resources/xts-flowers-001.jpg');
        $media->AddTag('API');
        $media = (new XiboLibrary($this->getEntityProvider()))->getById($media->mediaId);
        $this->assertSame('API', $media->tags);
         print_r($media->tags);
        $this->client->delete('/library/' . $media->mediaId . '/untag', [
            'tag' => ['API']
            ]);
        $media = (new XiboLibrary($this->getEntityProvider()))->getById($media->mediaId);
         print_r($media->tags);
        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $media->delete();
    }

    /**
     * Edit media file
     */
    public function testEdit()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video 4', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Generate new random name
        $name = Random::generateString(8, 'phpunit');
        # Edit media file, change the name
        $this->client->put('/library/' . $media->mediaId, [
            'name' => $name,
            'duration' => 50,
            'retired' => $media->retired,
            'tags' => $media->tags,
            'updateInLayouts' => 1
        ], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $this->assertSame(200, $this->client->response->status(), 'Not successful: ' . $this->client->response->body());
        $object = json_decode($this->client->response->body());
        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame($name, $object->data->name);
        $media = (new XiboLibrary($this->getEntityProvider()))->getById($media->mediaId);
        $this->assertSame($name, $media->name);
        $media->delete();
    }

    /**
     * Test delete added media
     */
    public function testDelete()
    {
        # Using XiboLibrary wrapper to upload new file to the CMS, need to provide (name, file location)
        $media = (new XiboLibrary($this->getEntityProvider()))->create('API video 4', PROJECT_ROOT . '/tests/resources/HLH264.mp4');
        # Delete added media file
        $this->client->delete('/library/' . $media->mediaId);
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    /**
    * Library tidy
    */
    public function testTidy()
    {
        $this->client->delete('/library/tidy');
        $this->assertSame(200, $this->client->response->status(), $this->client->response->body());
    }

    public function testUploadFromUrl()
    {
        shell_exec('cp -r ' . PROJECT_ROOT . '/tests/resources/rss/image1.jpg ' . PROJECT_ROOT . '/web');

        $response = $this->getEntityProvider()->post('/library/uploadUrl?envelope=1', [
            'url' => 'http://localhost/image1.jpg'
        ]);

        $this->assertSame(201, $response['status'], json_encode($response));
        $this->assertNotEmpty($response['data'], 'Empty Response');
        $this->assertSame('image', $response['data']['mediaType']);
        $this->assertSame(0, $response['data']['expires']);
        $this->assertSame('image1', $response['data']['name']);
        $this->assertNotEmpty($response['data']['mediaId'], 'Not successful, MediaId is empty');

        $module = $this->getEntityProvider()->get('/module', ['name' => 'Image']);
        $moduleDefaultDuration = $module[0]['defaultDuration'];

        $this->assertSame($response['data']['duration'], $moduleDefaultDuration);

        shell_exec('rm -r ' . PROJECT_ROOT . '/web/image1.jpg');
    }

    public function testUploadFromUrlWithType()
    {
        shell_exec('cp -r ' . PROJECT_ROOT . '/tests/resources/rss/image2.jpg ' . PROJECT_ROOT . '/web');

        $response = $this->getEntityProvider()->post('/library/uploadUrl?envelope=1', [
            'url' =>  'http://localhost/image2.jpg',
            'type' => 'image'
        ]);

        $this->assertSame(201, $response['status'], json_encode($response));
        $this->assertNotEmpty($response['data'], 'Empty Response');
        $this->assertSame('image', $response['data']['mediaType']);
        $this->assertSame(0, $response['data']['expires']);
        $this->assertSame('image2', $response['data']['name']);
        $this->assertNotEmpty($response['data']['mediaId'], 'Not successful, MediaId is empty');

        $module = $this->getEntityProvider()->get('/module', ['name' => 'Image']);
        $moduleDefaultDuration = $module[0]['defaultDuration'];

        $this->assertSame($response['data']['duration'], $moduleDefaultDuration);

        shell_exec('rm -r ' . PROJECT_ROOT . '/web/image2.jpg');
    }

    public function testUploadFromUrlWithTypeAndName()
    {
        shell_exec('cp -r ' . PROJECT_ROOT . '/tests/resources/HLH264.mp4 ' . PROJECT_ROOT . '/web');

        $response = $this->getEntityProvider()->post('/library/uploadUrl?envelope=1', [
            'url' =>  'http://localhost/HLH264.mp4',
            'type' => 'video',
            'optionalName' => 'PHPUNIT URL upload video'
        ]);

        $this->assertSame(201, $response['status'], json_encode($response));
        $this->assertNotEmpty($response['data'], 'Empty Response');
        $this->assertSame('video', $response['data']['mediaType']);
        $this->assertSame(0, $response['data']['expires']);
        $this->assertSame('PHPUNIT URL upload video', $response['data']['name']);
        $this->assertNotEmpty($response['data']['mediaId'], 'Not successful, MediaId is empty');

        $module = $this->getEntityProvider()->get('/module', ['name' => 'Video']);
        $moduleDefaultDuration = $module[0]['defaultDuration'];

        $this->assertSame($response['data']['duration'], $moduleDefaultDuration);

        shell_exec('rm -r ' . PROJECT_ROOT . '/web/HLH264.mp4');
    }
}
