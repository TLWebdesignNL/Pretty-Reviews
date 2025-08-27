<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

\defined('_JEXEC') or die;

/**
 * Dispatcher class for mod_prettyreviews
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();
	    $moduleId = (isset($data['module']->id)) ? $data['module']->id : "";
	    $moduleId = (isset($data['module']->id)) ? $data['module']->id : "";
        // Decode module params
	    $params = json_decode($data['module']->params, true);

	    // Extract required parameters
	    $limit = $params['limit'] ?? null;
	    $displaySort = $params['displaysort'] ?? "newest";
	    $hideEmpty = $params['hideemptyreviews'] ?? 0;

		// Get Reviews from JSON File
		$data['reviewdata'] = $this->getHelperFactory()->getHelper('PrettyreviewsHelper')->getJsonFile(JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json', $limit, $displaySort, $hideEmpty);

		return $data;
    }
}