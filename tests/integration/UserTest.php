<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (UserTest.php)
 */

namespace Xibo\Tests\Integration;
use Xibo\Helper\Random;
use Xibo\OAuth2\Client\Entity\XiboUser;
use Xibo\Tests\LocalWebTestCase;

/**
 * Class UserTest
 * @package Xibo\Tests
 */
class UserTest extends LocalWebTestCase
{
    /**
     * Show me
     */
    public function testGetMe()
    {
        $response = $this->sendRequest('GET','/user/me');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object);
        $this->assertSame('phpunit', $object->data->userName);
    }

	/**
	* Show all users
	*/
    public function testGetUsers()
    {
        $response = $this->sendRequest('GET','/user');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object);
    }

    /**
	* Add new user
	*/
    public function testAdd()
    {
        $group = $this->getEntityProvider()->get('/group', ['userGroup' => 'Users'])[0];
        $userName = Random::generateString();

        $response = $this->sendRequest('POST','/user', [
            'userName' => $userName,
            'userTypeId' => 3,
            'homePageId' => 'icondashboard.view',
            'password' => 'newUserPassword',
            'groupId' => $group['groupId'],
            'libraryQuota' => 0
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getBody());

        $object = json_decode($response->getBody());

        $this->assertObjectHasAttribute('data', $object, $response->getBody());
        $this->assertObjectHasAttribute('id', $object, $response->getBody());

        $this->assertSame($userName, $object->data->userName);
        $this->assertSame(3, $object->data->userTypeId);
        $this->assertSame('icondashboard.view', $object->data->homePageId);

        $userCheck = (new XiboUser($this->getEntityProvider()))->getById($object->id);
        $userCheck->delete();
    }

    public function testAddEmptyPassword()
    {
        $group = $this->getEntityProvider()->get('/group', ['userGroup' => 'Users'])[0];

        $response = $this->sendRequest('POST', '/user', [
            'userName' => Random::generateString(),
            'userTypeId' => 3,
            'homePageId' => 'icondashboard.view',
            'password' => null,
            'groupId' => $group['groupId'],
            'libraryQuota' => 0
        ]);

        $this->assertSame(422, $response->getStatusCode(), $response->getBody());
    }
}