<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;
use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

class CustomPrettyField extends FormField
{
    protected function getInput(): string
    {
        return '';
    }

    protected function getLabel(): string
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getInt('prettyreviewsAjax') === 1) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);

            try {
                echo new JsonResponse((new PrettyreviewsHelper())->updateGoogleReviewsAjax());
            } catch (\Throwable $e) {
                $code = (int) $e->getCode();

                if ($code >= 400 && $code < 600) {
                    $app->setHeader('status', $code, true);
                }

                echo new JsonResponse(null, $e->getMessage(), true);
            }

            $app->close();
        }

        $attributes = [
            'data-id'    => $input->getInt('id'),
            'data-token' => Session::getFormToken(),
        ];

        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('updateReviews')
            ->icon('fas fa-download')
            ->text(Text::_('MOD_PRETTYREVIEWS_UPDATE_REVIEWS'))
            ->task('')
            ->attributes($attributes)
            ->onclick('updateReviews(this)')
            ->listCheck(false);

        $wa = $app->getDocument()->getWebAssetManager();
        Text::script('MOD_PRETTYREVIEWS_UPDATE_MISSING_MODULE_OR_TOKEN');
        Text::script('MOD_PRETTYREVIEWS_UPDATE_SUCCESS');
        Text::script('MOD_PRETTYREVIEWS_UPDATE_AJAX_ERROR');

        $wa->registerAndUseScript('mod_prettyreviews.admin', 'media/mod_prettyreviews/js/prettyreviews.js', [], ['defer' => true]);

        $endpoint = Uri::base()
            . 'index.php?option=com_modules&view=module&layout=edit&id='
            . $input->getInt('id')
            . '&prettyreviewsAjax=1';
        $inlineScript = 'var prettyReviewsOptions = ' . json_encode(['endpoint' => $endpoint]) . ';';
        $wa->addInlineScript($inlineScript);

        return '';
    }
}
