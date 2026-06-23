<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access to this file
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Script file of Prettyreviews module
 */
class mod_prettyreviewsInstallerScript
{
    /**
     * @var string
     */
    protected string $minimumJoomla = '4.0';

    /**
     * @var string
     */
    protected string $minimumPhp;

    /**
     * The version being upgraded from, captured during an update preflight.
     *
     * @var ?string
     */
    protected ?string $fromVersion = null;

    /**
     * Extension script constructor.
     *
     * @return  void
     */
    public function __construct()
    {
        $this->minimumPhp = JOOMLA_MINIMUM_PHP;
    }

    /**
     * Method to install the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function install(InstallerAdapter $parent): bool
    {
        echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_INSTALL');

        return true;
    }

    /**
     * Method to uninstall the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function uninstall(InstallerAdapter $parent): bool
    {
        echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_UNINSTALL');

        return true;
    }

    /**
     * Method to update the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function update(InstallerAdapter $parent): bool
    {
        echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_UPDATE');

        return true;
    }

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param   string            $type    The type of change (install, update or discover_install, not uninstall)
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        // Check for the minimum PHP version before continuing
        if (!empty($this->minimumPhp) && version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPhp), Log::WARNING, 'jerror');

            return false;
        }

        // Check for the minimum Joomla version before continuing
        if (!empty($this->minimumJoomla) && version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Log::add(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla), Log::WARNING, 'jerror');

            return false;
        }

        // Capture the version we are upgrading from. At preflight the extensions
        // table still holds the OLD manifest, so this is the pre-update version.
        if ($type === 'update') {
            $this->fromVersion = $this->getInstalledVersion();
        }

        echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_PREFLIGHT');

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences
     *
     * @param   string            $type    The type of change (install, update or discover_install, not uninstall)
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if ($type == "update") {
            echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_RESAVE_MODULE');

            // Upgrading from a pre-2.0.0 single-carousel release: pin every existing
            // module instance to one column on all breakpoints so its appearance does
            // not change. New 2.0+ modules store their own column settings and are
            // therefore left untouched.
            if ($this->fromVersion !== null && version_compare($this->fromVersion, '2.0.0', '<')) {
                $this->lockLegacyColumnsToSingle();
                echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_COLUMNS_PRESERVED');
            }
        }
        echo Text::_('MOD_PRETTYREVIEWS_INSTALLERSCRIPT_POSTFLIGHT');

        return true;
    }

    /**
     * Reads the currently-installed version from the extensions table. During an
     * update preflight this still reflects the version being replaced.
     *
     * @return  ?string
     */
    private function getInstalledVersion(): ?string
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('mod_prettyreviews'));
            $db->setQuery($query);
            $cache = $db->loadResult();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$cache) {
            return null;
        }

        $decoded = json_decode((string) $cache, true);

        return \is_array($decoded) && isset($decoded['version']) ? (string) $decoded['version'] : null;
    }

    /**
     * Pins every existing module instance to a single column on all breakpoints,
     * preserving pre-2.0.0 behaviour. Only adds the keys when they are absent, so
     * an administrator's saved choices are never overwritten.
     *
     * @return  void
     */
    private function lockLegacyColumnsToSingle(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('params')])
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_prettyreviews'));
        $db->setQuery($query);
        $modules = $db->loadObjectList();

        if (empty($modules)) {
            return;
        }

        $keys = [
            'carousel_columns_mobile',
            'carousel_columns_tablet',
            'carousel_columns_desktop',
            'carousel_columns_wide',
        ];

        foreach ($modules as $module) {
            $params = json_decode((string) $module->params, true);

            if (!\is_array($params)) {
                $params = [];
            }

            $changed = false;

            foreach ($keys as $key) {
                if (!\array_key_exists($key, $params)) {
                    $params[$key] = "1";
                    $changed      = true;
                }
            }

            if (!$changed) {
                continue;
            }

            $encoded = json_encode($params);
            $id      = (int) $module->id;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':params', $encoded)
                ->bind(':id', $id, ParameterType::INTEGER);
            $db->setQuery($update);
            $db->execute();
        }
    }
}
