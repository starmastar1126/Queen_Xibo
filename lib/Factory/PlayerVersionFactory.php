<?php
/**
 * Copyright (C) 2018 Xibo Signage Ltd
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

namespace Xibo\Factory;

use Xibo\Entity\PlayerVersion;
use Xibo\Entity\User;
use Xibo\Service\ConfigServiceInterface;
use Xibo\Support\Exception\NotFoundException;

/**
 * Class PlayerVersionFactory
 * @package Xibo\Factory
 */
class PlayerVersionFactory extends BaseFactory
{
    /**
     * @var ConfigServiceInterface
     */
    private $config;

    /**
     * @var MediaFactory
     */
    private $mediaFactory;

    /**
     * Construct a factory
     * @param User $user
     * @param UserFactory $userFactory
     * @param ConfigServiceInterface $config
     * @param MediaFactory $mediaFactory
     */
    public function __construct($user, $userFactory, $config, $mediaFactory)
    {
        $this->setAclDependencies($user, $userFactory);

        $this->config = $config;
        $this->mediaFactory = $mediaFactory;

    }

    /**
     * Create Empty
     * @return PlayerVersion
     */
    public function createEmpty()
    {
        return new PlayerVersion($this->getStore(), $this->getLog(), $this->config, $this->mediaFactory, $this);
    }

    /**
     * Populate Player Version table
     * @param string $type
     * @param int $version
     * @param int $code
     * @param int $mediaId
     * @param string $playerShowVersion
     * @return PlayerVersion
     */
    public function create($type, $version, $code, $mediaId, $playerShowVersion)
    {
        $playerVersion = $this->createEmpty();
        $playerVersion->type = $type;
        $playerVersion->version = $version;
        $playerVersion->code = $code;
        $playerVersion->mediaId = $mediaId;
        $playerVersion->playerShowVersion = $playerShowVersion;
        $playerVersion->save();

        return $playerVersion;
    }

    /**
     * Get by Media Id
     * @param int $mediaId
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getByMediaId($mediaId)
    {
        $versions = $this->query(null, ['disableUserCheck' => 1, 'mediaId' => $mediaId]);

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find media'));

        return $versions[0];
    }

    /**
     * Get by Version Id
     * @param int $versionId
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getById($versionId)
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'versionId' => $versionId));

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find version'));

        return $versions[0];
    }

    /**
     * Get by Type
     * @param string $type
     * @return PlayerVersion
     * @throws NotFoundException
     */
    public function getByType($type)
    {
        $versions = $this->query(null, array('disableUserCheck' => 1, 'playerType' => $type));

        if (count($versions) <= 0)
            throw new NotFoundException(__('Cannot find Player Version'));

        return $versions[0];
    }

    /**
     * @param null $sortOrder
     * @param array $filterBy
     * @return PlayerVersion[]
     * @throws NotFoundException
     */
    public function query($sortOrder = null, $filterBy = [])
    {
        if ($sortOrder === null) {
            $sortOrder = ['code DESC'];
        }
        
        $sanitizedFilter = $this->getSanitizer($filterBy);
        
        $params = [];
        $entries = [];

        $select = '
            SELECT  player_software.versionId,
               player_software.player_type AS type,
               player_software.player_version AS version,
               player_software.player_code AS code,
               player_software.playerShowVersion,
               media.mediaId,
               media.originalFileName,
               media.storedAs,
            ';

        $select .= " (SELECT GROUP_CONCAT(DISTINCT `group`.group)
                              FROM `permission`
                                INNER JOIN `permissionentity`
                                ON `permissionentity`.entityId = permission.entityId
                                INNER JOIN `group`
                                ON `group`.groupId = `permission`.groupId
                             WHERE entity = :entity
                                AND objectId = media.mediaId
                                AND view = 1
                            ) AS groupsWithPermissions ";
        $params['entity'] = 'Xibo\\Entity\\Media';

        $body = ' FROM player_software 
                    INNER JOIN media
                    ON  player_software.mediaId = media.mediaId
                  WHERE 1 = 1 
            ';

        // by media ID
        if ($sanitizedFilter->getInt('mediaId', ['default' => -1]) != -1) {
            $body .= " AND media.mediaId = :mediaId ";
            $params['mediaId'] = $sanitizedFilter->getInt('mediaId');
        }

        if ($sanitizedFilter->getInt('versionId', ['default' => -1]) != -1) {
            $body .= " AND player_software.versionId = :versionId ";
            $params['versionId'] = $sanitizedFilter->getInt('versionId');
        }

        if ($sanitizedFilter->getString('playerType') != '') {
            $body .= " AND player_software.player_type = :playerType ";
            $params['playerType'] = $sanitizedFilter->getString('playerType');
        }

        if ($sanitizedFilter->getString('playerVersion') != '') {
            $body .= " AND player_software.player_version = :playerVersion ";
            $params['playerVersion'] = $sanitizedFilter->getString('playerVersion');
        }

        if ($sanitizedFilter->getInt('playerCode') != '') {
            $body .= " AND player_software.player_code = :playerCode ";
            $params['playerCode'] = $sanitizedFilter->getInt('playerCode');
        }

        if ($sanitizedFilter->getString('playerShowVersion') !== null) {
            $terms = explode(',', $sanitizedFilter->getString('playerShowVersion'));
            $this->nameFilter('player_software', 'playerShowVersion', $terms, $body, $params, ($sanitizedFilter->getCheckbox('useRegexForName') == 1));
        }

        // View Permissions
        $this->viewPermissionSql('Xibo\Entity\Media', $body, $params, '`media`.mediaId', '`media`.userId', $filterBy);

        // Sorting?
        $order = '';
        if (is_array($sortOrder)) {
            $order .= 'ORDER BY ' . implode(',', $sortOrder);
        }

        $limit = '';
        // Paging
        if ($filterBy !== null && $sanitizedFilter->getInt('start') !== null && $sanitizedFilter->getInt('length') !== null) {
            $limit = ' LIMIT ' . intval($sanitizedFilter->getInt('start'), 0) . ', ' . $sanitizedFilter->getInt('length', ['default' => 10]);
        }

        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row, [
                'intProperties' => [
                    'mediaId', 'code'
                ]
            ]);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            unset($params['entity']);
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;


    }

    public function getDistinctType()
    {
        $params = [];
        $entries = [];
        $sql = '
        SELECT DISTINCT player_software.player_type AS type 
        FROM player_software
        ORDER BY type ASC
        ';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }

    public function getDistinctVersion()
    {
        $params = [];
        $entries = [];
        $sql = '
        SELECT DISTINCT player_software.player_version AS version 
        FROM player_software
        ORDER BY version ASC
        ';

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = $version = $this->createEmpty()->hydrate($row);
        }

        return $entries;
    }
}