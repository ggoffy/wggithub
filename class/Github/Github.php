<?php

declare(strict_types=1);

namespace XoopsModules\Wggithub\Github;

/*
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 * @copyright    XOOPS Project https://xoops.org/
 * @license      GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @since
 * @author       Goffy - XOOPS Development Team
 */

use XoopsModules\Wggithub;
use XoopsModules\Wggithub\{
    Helper,
    Constants,
};

/**
 * Class GitHub
 */
class GitHub
{

    public const BASE_URL = 'https://api.github.com/';
    /**
     * @var string
     */
    public $userAuth = 'myusername';
    /**
     * @var string
     */
    public $tokenAuth = 'mytoken';
    /**
     * Verbose debugging for curl (when putting)
     * @var bool
     */
    public $debug = false;

    private $helper = null;

    public $apiErrorLimit = false;

    public $apiErrorMisc = false;

    /**
     * Constructor
     *
     * @param null
     */
    public function __construct()
    {
        $this->helper = \XoopsModules\Wggithub\Helper::getInstance();
        $this->getSetting();
    }

    /**
     * @static function &getInstance
     *
     * @param null
     * @return GitHub of GitHub
     */
    public static function getInstance()
    {
        static $instance = false;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Get data of all repositories of given user
     *
     * @param string $user
     * @return array
     */
    public function readUserRepositories($user, $per_page = 100, $page = 1)
    {
        $libRepositories = new Repositories();
        $repos = $libRepositories->getUserRepositories($user, $per_page, $page);

        return $repos;
    }


    /**
     * Get data of all repositories of given organisation
     *
     * @param $org
     * @return array
     */
    public function readOrgRepositories($org, $per_page = 100, $page = 1)
    {
        $libRepositories = new Repositories();
        $repos = $libRepositories->getOrgRepositories($org, $per_page, $page);

        return $repos;
    }

    /**
     * update all related tables after reading repositories
     *
     * @param string $user
     * @return boolean
     */
    public function updateTables($user, $repos, $updateAddtionals)
    {

       //deprecated


        $errors = 0;

        // add/update all items from table repositories
        $res = $this->updateTableRepositories($user, $repos);
        if (false === $res) {
            $errors++;
        }



        return (0 == $errors);

//rest sollte obsolet sein




        // add/update table readmes
        $res = $this->helper->getHandler('Readmes')->updateTableReadmes();
        if (false === $res) {
            $errors++;
        }

        // add/update table repositories with release
        $res = $this->helper->getHandler('Releases')->updateTableReleases();
        if (false === $res) {
            $errors++;
        }

        return (0 == $errors);
    }

    /**
     * Update table repositories
     *
     * @return int
     */
    public function updateTableRepositories($user, $repos = [], $updateAddtionals = true)
    {
        $reposNb = 0;
        $repositoriesHandler = $this->helper->getHandler('Repositories');

        $submitter = isset($GLOBALS['xoopsUser']) && \is_object($GLOBALS['xoopsUser']) ? $GLOBALS['xoopsUser']->getVar('uid') : 0;
        // add/update all items from table repositories
        foreach ($repos as $key => $repo) {
            $crRepositories = new \CriteriaCompo();
            $crRepositories->add(new \Criteria('repo_nodeid', $repo['node_id']));
            $repositoriesCount = $repositoriesHandler->getCount($crRepositories);
            $repoId = 0;
            if ($repositoriesCount > 0) {
                $repositoriesAll = $repositoriesHandler->getAll($crRepositories);
                foreach (\array_keys($repositoriesAll) as $i) {
                    $repoId = $repositoriesAll[$i]->getVar('repo_id');
                }
                unset($crRepositories, $repositoriesAll);
                $repositoriesObj = $repositoriesHandler->get($repoId);
                $updatedAtOld = $repositoriesObj->getVar('repo_updatedat');
                $updatedAtNew = 0;
                $status = $repositoriesObj->getVar('repo_status');
                if (is_string($repo['updated_at'])) {
                    $updatedAtNew = \strtotime($repo['updated_at']);
                }
                if ($updatedAtOld != $updatedAtNew) {
                    $status = Constants::STATUS_UPDATED;
                }
            } else {
                $repositoriesObj = $repositoriesHandler->create();
                $updatedAtNew = \strtotime($repo['updated_at']);
                $status = Constants::STATUS_NEW;
            }
            if (Constants::STATUS_UPTODATE !== $status) {
                // Set Vars
                $repositoriesObj->setVar('repo_nodeid', $repo['node_id']);
                $repositoriesObj->setVar('repo_user', $user);
                $repositoriesObj->setVar('repo_name', $repo['name']);
                $repositoriesObj->setVar('repo_fullname', $repo['full_name']);
                if (is_string($repo['created_at'])) {
                    $createdAt = \strtotime($repo['created_at']);
                }
                $repositoriesObj->setVar('repo_createdat', $createdAt);
                $repositoriesObj->setVar('repo_updatedat', $updatedAtNew);
                $repositoriesObj->setVar('repo_htmlurl', $repo['html_url']);
                $repositoriesObj->setVar('repo_status', $status);
                $repositoriesObj->setVar('repo_datecreated', time());
                $repositoriesObj->setVar('repo_submitter', $submitter);
                // Insert Data
                if ($repositoriesHandler->insert($repositoriesObj)) {
                    $newRepoId = $repositoriesObj->getNewInsertedIdRepositories();
                    if (0 == $repoId) {
                        $repoId = $newRepoId;
                    }
                    $reposNb++;
                    if ($updateAddtionals && (Constants::STATUS_NEW == $status || Constants::STATUS_UPDATED == $status)) {
                        // add/update table readmes
                        $res = $this->helper->getHandler('Readmes')->updateReadmes($repoId, $user, $repo['name']);
                        if (false === $res) {
                            return false;
                        }
                        // add/update table release
                        $res = $this->helper->getHandler('Releases')->updateReleases($repoId, $user, $repo['name']);
                        if (false === $res) {
                            return false;
                        }
                        // change status to updated
                        $repositoriesObj = $repositoriesHandler->get($repoId);
                        $repositoriesObj->setVar('repo_status', Constants::STATUS_UPTODATE);
                        $repositoriesHandler->insert($repositoriesObj, true);
                    }

                } else {
                    return false;
                }
            }
        }

        return $reposNb;
    }


    /**
     * Get primary setting
     *
     * @param bool $user
     * @return bool|array
     */
    private function getSetting($user = false)
    {

        $this->userAuth = 'ggoffy';
        //Token_wgGitHub_XoopsModules25x
        $this->tokenAuth = '8a133fddc559d068f';

        return true;

        $settingsHandler = $this->helper->getHandler('Settings');
        if ($user) {
            $setting = $settingsHandler->getRequestSetting();
        } else {
            $setting = $settingsHandler->getPrimarySetting();
        }

        if (0 == \count($setting)) {
            \redirect_header('settings.php', 3, \_AM_WGTRANSIFEX_THEREARENT_SETTINGS);
        }

        return $setting;
    }


}
