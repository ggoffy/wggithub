<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/

/**
 * @copyright       XOOPS Project (https://xoops.org)
 * @license         GNU GPL 2 (http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 * @author          XOOPS Project <www.xoops.org> <www.xoops.ir>
 */
\defined('XOOPS_ROOT_PATH') || die('Restricted access.');

/**
 * Class WggithubCorePreload
 */
class WggithubCorePreload extends \XoopsPreloadItem
{
    // to add PSR-4 autoloader
    private const AUTOLOADER_PATH = '/vendor/autoload.php';


    /**
     * eventCoreIncludeCommonAuthSuccess
     */
    public static function eventCoreIncludeCommonAuthSuccess(): void
    {
        self::initializeAutoloader();
    }


    /**
     * @return void
     */
    private static function initializeAutoloader(): void
    {
        $autoloader = \dirname(__DIR__) . self::AUTOLOADER_PATH;

        if (!\file_exists($autoloader)) {
            // Throw an exception for better error handling
            throw new \RuntimeException("xwhoops25/vendor/autoload.php not found, was 'composer install' done?");
        }

        require_once $autoloader;
    }

    /**
     * @param $args
     */
    public static function eventCoreIncludeCommonEnd($args)
    {
        include __DIR__ . '/autoloader.php';
    }

}
