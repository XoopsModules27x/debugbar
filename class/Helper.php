<?php
declare(strict_types=1);

namespace XoopsModules\Debugbar;

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
 * @copyright    XOOPS Project (https://xoops.org)
 * @license      GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @package      debugbar
 * @since        1.0
 * @author       XOOPS Development Team
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Class Helper
 */
final class Helper extends \Xmf\Module\Helper
{
    /** @var bool */
    public $debug;

    /**
     * @param bool $debug
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $moduleDirName = \basename(\dirname(__DIR__));
        parent::__construct($moduleDirName);
    }

    /**
     * @param bool $debug
     *
     * @return \XoopsModules\Debugbar\Helper
     */
    public static function getInstance(bool $debug = false): self
    {
        static $instance;
        if (null === $instance) {
            $instance = new self($debug);
        }

        return $instance;
    }

    /**
     * @return string
     */
    public function getDirname(): string
    {
        return $this->dirname;
    }

    /**
     * Get an Object Handler
     *
     * @param mixed $name name of handler to load
     *
     * @return object
     */
    public function getHandler(mixed $name): object
    {
        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Handler name must be a non-empty string');
        }

        $class = __NAMESPACE__ . '\\' . \ucfirst($name) . 'Handler';
        if (! \class_exists($class)) {
            throw new \RuntimeException("Class '$class' not found");
        }
        /** @var \XoopsMySQLDatabase $db */
        $db = \XoopsDatabaseFactory::getDatabaseConnection();
        $helper = self::getInstance();
        $handler = new $class($db, $helper);
        $this->addLog("Getting handler '$name'");

        return $handler;
    }
}
