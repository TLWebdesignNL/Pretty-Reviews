<?php

namespace TLWeb\Module\Prettyreviews\Site\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Input\Input;
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
            "data-cid" => isset($moduleParams->cid) ?? null,
            "data-apikey" => isset($moduleParams->apikey) ?? null,
            "data-reviewsort" => isset($moduleParams->reviewsort) ?? null
        );
        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('updateReviews')
            ->icon('fa fa-download')
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