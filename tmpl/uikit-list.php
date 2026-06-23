<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape  = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$safeUrl = static function ($url) use ($escape): string {
    $url = trim((string) ($url ?? ''));
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return '';
    }
    return $escape($url);
};

$maxChars          = (int) $params->get('review_maxchars', 250);
$showRatingSummary = (bool) $params->get('show_rating_summary', 1);
$showReviewCount   = (bool) $params->get('show_review_count', 1);
$showPhotos        = (bool) $params->get('show_photos', 1);
$showDate          = (bool) $params->get('show_date', 1);
$showViewAll       = (bool) $params->get('show_viewall', 1);
$showWriteReview   = (bool) $params->get('show_write_review', 0);
$rating            = (float) ($reviewdata['rating'] ?? 0);
$ratingsCount      = (int) ($reviewdata['ratingsCount'] ?? 0);
$reviews           = array_values($reviewdata['reviews'] ?? []);
$reviewsUrl        = $safeUrl($reviewdata['url'] ?? '');
$writeReviewUrl    = $safeUrl($writeReviewUrl ?? '');
?>

<div class="prettyreviews prettyreviews-uikit-list">

    <?php if ($showRatingSummary || ($showViewAll && $reviewsUrl !== '') || ($showWriteReview && $writeReviewUrl !== '')) : ?>
    <div class="uk-flex uk-flex-column uk-flex-row@m uk-flex-between@m uk-flex-middle@m uk-grid-small uk-margin-medium-bottom" uk-grid>
        <?php if ($showRatingSummary) : ?>
        <div>
            <div class="uk-flex uk-flex-middle uk-grid-small uk-margin-xsmall-bottom" uk-grid>
                <strong class="uk-text-large"><?php echo $rating; ?></strong>
                <span class="uk-text-warning"
                      aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $rating)); ?>">
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <span aria-hidden="true"><?php echo ($i <= (int) round($rating)) ? '★' : '☆'; ?></span>
                    <?php endfor; ?>
                </span>
            </div>
            <?php if ($showReviewCount && $ratingsCount > 0) : ?>
                <div class="uk-text-small uk-text-muted">
                    <?php echo Text::sprintf('MOD_PRETTYREVIEWS_REVIEWS_COUNT', $ratingsCount); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (($showViewAll && $reviewsUrl !== '') || ($showWriteReview && $writeReviewUrl !== '')) : ?>
            <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
                <?php if ($showViewAll && $reviewsUrl !== '') : ?>
                    <div>
                        <a href="<?php echo $reviewsUrl; ?>" target="_blank" rel="noopener" class="uk-button uk-button-default uk-button-small">
                            <?php echo Text::_('MOD_PRETTYREVIEWS_VIEWALLREVIEWS'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($showWriteReview && $writeReviewUrl !== '') : ?>
                    <div>
                        <a href="<?php echo $writeReviewUrl; ?>" target="_blank" rel="noopener" class="uk-button uk-button-primary uk-button-small">
                            <?php echo Text::_('MOD_PRETTYREVIEWS_WRITE_REVIEW'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($reviews)) : ?>
        <p class="uk-text-muted uk-text-center uk-padding-small">
            <?php echo Text::_('MOD_PRETTYREVIEWS_NO_REVIEWS'); ?>
        </p>
    <?php else : ?>
        <ul class="uk-list uk-list-divider">
            <?php foreach ($reviews as $review) :
                $photoUrl     = $safeUrl($review['profile_photo_url'] ?? '');
                $authorUrl    = $safeUrl($review['author_url'] ?? '');
                $author       = $escape($review['author_name'] ?? '');
                $rawText      = (string) ($review['text'] ?? '');
                if ($maxChars > 0 && mb_strlen($rawText) > $maxChars) {
                    $rawText = mb_substr($rawText, 0, $maxChars) . '…';
                }
                $text         = $escape($rawText);
                $timeAgo      = $escape($review['time_ago'] ?? '');
                $reviewRating = (int) ($review['rating'] ?? 0);
                ?>
                <li>
                    <article class="uk-padding-small uk-padding-remove-horizontal">
                        <div class="uk-flex uk-flex-top uk-grid-small" uk-grid>
                            <?php if ($showPhotos && $photoUrl !== '') : ?>
                                <div class="uk-width-auto">
                                    <img src="<?php echo $photoUrl; ?>"
                                         class="uk-border-circle"
                                         width="48"
                                         height="48"
                                         alt="<?php echo $author; ?>">
                                </div>
                            <?php endif; ?>
                            <div class="uk-width-expand">
                                <div class="uk-flex uk-flex-column uk-flex-row@s uk-flex-between@s uk-grid-small uk-margin-xsmall-bottom" uk-grid>
                                    <div>
                                        <?php if ($authorUrl !== '') : ?>
                                            <a href="<?php echo $authorUrl; ?>"
                                               class="uk-link-heading uk-text-bold">
                                                <?php echo $author; ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="uk-text-bold"><?php echo $author; ?></span>
                                        <?php endif; ?>
                                        <?php if ($showDate && $timeAgo !== '') : ?>
                                            <div class="uk-text-small uk-text-muted">
                                                <?php echo $timeAgo; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="uk-text-warning uk-text-nowrap"
                                         aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $reviewRating)); ?>">
                                        <?php for ($j = 1; $j <= 5; $j++) : ?>
                                            <span aria-hidden="true"><?php echo ($j <= $reviewRating) ? '★' : '☆'; ?></span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if ($text !== '') : ?>
                                    <p class="uk-text-muted uk-margin-remove-bottom"><?php echo $text; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>
