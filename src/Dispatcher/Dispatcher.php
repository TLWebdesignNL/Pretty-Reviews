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
        $data     = parent::getLayoutData();
        $moduleId = (int) ($data['module']->id ?? 0);
        $params   = json_decode($data['module']->params, true) ?? [];

        $helper = $this->getHelperFactory()->getHelper('PrettyreviewsHelper');
        $raw    = $helper->loadRaw($moduleId);

        $data['reviewdata'] = $helper->present($raw, [
            'limit'     => $params['limit'] ?? null,
            'sort'      => $params['displaysort'] ?? 'newest',
            'hideEmpty' => $params['hideemptyreviews'] ?? 0,
        ]);

        if (isset($data['reviewdata']['reviews']) && is_array($data['reviewdata']['reviews'])) {
            foreach ($data['reviewdata']['reviews'] as &$review) {
                if (!is_array($review)) {
                    continue;
                }

                $timestamp          = (int) ($review['time'] ?? 0);
                $review['time_ago'] = $timestamp > 0 ? $helper->timeAgo($timestamp) : '';
            }

            unset($review);
        }

        return $data;
    }
}
