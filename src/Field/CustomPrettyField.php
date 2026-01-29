<?php
/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\Input\Input;
use Joomla\CMS\Uri\Uri;

class CustomPrettyField extends FormField
{
    protected function getInput() {
        return "";
    }
    protected function getLabel() {

        $moduleParams = $this->form->getValue('params');
        $input   = Factory::getApplication()->input;

        $attributes = array(
            "data-id" => $input->getInt('id'),
            "data-cid" => (isset($moduleParams->cid)) ? $moduleParams->cid : null,
            "data-apikey" => (isset($moduleParams->apikey)) ? $moduleParams->apikey : null,
            "data-reviewsort" => (isset($moduleParams->reviewsort)) ? $moduleParams->reviewsort : null,
            "data-secret" => (isset($moduleParams->secret)) ? $moduleParams->secret : null
        );
        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('updateReviews')
            ->icon('fas fa-download')
            ->text('update Reviews')
            ->task('')
            ->attributes($attributes)
            ->onclick('updateReviews(this)')
            ->listCheck(false);

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript('prettyreviews', 'media/mod_prettyreviews/js/prettyreviews.js', [], ['defer' => true]);

        $baseUri = Uri::root();
        $inlineScript = 'var prettyReviewsOptions = ' . json_encode(['baseUrl' => $baseUri]) . ';';
        $wa->addInlineScript($inlineScript);
        return "";
    }
}