<?php
namespace In2code\T3AM\Server;

/*
 * Copyright (C) 2018 Oliver Eglseder <php@vxvr.de>, in2code GmbH
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class UserRepository
 */
class UserRepository
{
    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    /**
     * @var array
     */
    protected $fields = [
        'tstamp',
        'username',
        'description',
        'avatar',
        'password',
        'admin',
        'disable',
        'starttime',
        'endtime',
        'lang',
        'email',
        'crdate',
        'realName',
        'disableIPlock',
        'deleted',
    ];

    /**
     * BackendUserRepository constructor.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function __construct()
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string $user
     *
     * @return string
     */
    public function getUserState($user)
    {
        $queryBuilder = $this->getBeUserQueryBuilder();

        $where = $queryBuilder
            ->expr()
            ->eq('username', $this->getWhereForUserName($user));

        $count = $queryBuilder
            ->count('*')
            ->from('be_users')
            ->where($where)
            ->execute()
            ->fetchColumn();

        /** @var DeletedRestriction $restriction */
        $restriction = GeneralUtility::makeInstance(DeletedRestriction::class);

        $queryBuilder = $this->getBeUserQueryBuilder();

        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add($restriction);

        $countActive = $queryBuilder
            ->count('*')
            ->from('be_users')
            ->where($where)
            ->execute()
            ->fetchColumn();

        if ($countActive) {
            return 'okay';
        }

        if ($count) {
            return 'deleted';
        }

        return 'unknown';
    }

    /**
     * @param string $user
     *
     * @return array
     */
    public function getUser($user)
    {
        return $this->getBeUserQueryBuilder()
            ->select(...$this->fields)
            ->from('be_users')
            ->where($this->getWhereForUserName($user))
            ->execute()
            ->fetch();
    }

    /**
     * created a querybuilder where statement
     *
     * @param $userName
     * @return String
     */
    protected function getWhereForUserName($userName)
    {
        $queryBuilder = $this->getBeUserQueryBuilder();

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        return $queryBuilder
            ->expr()
            ->eq('username', $queryBuilder->createNamedParameter($userName));
    }

    /**
     * @param string $user
     *
     * @return null|array
     */
    public function getUserImage($user)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $file = $queryBuilder
            ->select('sys_file.*')
            ->from('sys_file')
            ->rightJoin(
                'sys_file',
                'sys_file_reference',
                'sys_file_reference',
                $queryBuilder
                    ->expr()
                    ->eq('sys_file_reference.uid_local', $queryBuilder->quoteIdentifier('sys_file.uid')))
            ->rightJoin(
                'sys_file',
                'be_users',
                'be_users',
                $queryBuilder
                    ->expr()
                    ->eq('be_users.uid', $queryBuilder->quoteIdentifier('sys_file_reference.uid_foreign')))
            ->where(
                $queryBuilder
                    ->expr()
                    ->eq('be_users.username', $queryBuilder->createNamedParameter($user)))
            ->andWhere(
                $queryBuilder
                    ->expr()
                    ->eq('sys_file_reference.tablenames', $queryBuilder->createNamedParameter('be_users')))
            ->andWhere(
                $queryBuilder
                    ->expr()
                    ->eq('sys_file_reference.fieldname', $queryBuilder->createNamedParameter('avatar')))
            ->execute()
            ->fetch();

        if (!empty($file['uid'])) {
            try {
                $resource = ResourceFactory::getInstance()->getFileObject($file['uid'], $file);

                if ($resource instanceof File && $resource->exists()) {
                    return [
                        'identifier' => $resource->getName(),
                        'b64content' => base64_encode($resource->getContents()),
                    ];
                }
            } catch (FileDoesNotExistException $e) {
            }
        }

        return null;
    }

    /**
     * @return QueryBuilder
     */
    private function getBeUserQueryBuilder() {
        return $this->connectionPool->getQueryBuilderForTable('be_users');
    }
}
