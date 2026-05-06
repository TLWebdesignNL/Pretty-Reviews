<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$carouselId = 'prettyReviewsCarousel' . (int) $module->id;

HTMLHelper::_('bootstrap.carousel', $carouselId);

if ((int) $params->get('load_layout_css', 1) === 1) {
    $app->getDocument()->getWebAssetManager()
        ->registerAndUseStyle('mod_prettyreviews.default', 'media/mod_prettyreviews/css/layout-default.css');
}

$escape  = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$safeUrl = static function ($url) use ($escape): string {
    $url = trim((string) ($url ?? ''));
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return '';
    }
    return $escape($url);
};

$autoPlay          = (bool) $params->get('autoplay', 1);
$maxChars          = (int) $params->get('review_maxchars', 250);
$showRatingSummary = (bool) $params->get('show_rating_summary', 1);
$showReviewCount   = (bool) $params->get('show_review_count', 1);
$showPhotos        = (bool) $params->get('show_photos', 1);
$showDate          = (bool) $params->get('show_date', 1);
$showViewAll       = (bool) $params->get('show_viewall', 1);
$rating            = (float) ($reviewdata['rating'] ?? 0);
$ratingsCount      = (int) ($reviewdata['ratingsCount'] ?? 0);
$reviews           = array_values($reviewdata['reviews'] ?? []);
$reviewsUrl        = $safeUrl($reviewdata['url'] ?? '');
?>

<div class="prettyreviews prettyreviews-default">

    <?php if ($showRatingSummary || ($showViewAll && $reviewsUrl !== '')) : ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <?php if ($showRatingSummary) : ?>
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <strong class="fs-4"><?php echo $rating; ?></strong>
                <span class="text-warning"
                      aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $rating)); ?>">
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <i class="<?php echo ($i <= (int) round($rating)) ? 'fas' : 'far'; ?> fa-star"
                           aria-hidden="true"></i>
                    <?php endfor; ?>
                </span>
            </div>
            <?php if ($showReviewCount && $ratingsCount > 0) : ?>
                <div class="small text-muted">
                    <?php echo Text::sprintf('MOD_PRETTYREVIEWS_REVIEWS_COUNT', $ratingsCount); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($showViewAll && $reviewsUrl !== '') : ?>
            <a href="<?php echo $reviewsUrl; ?>"
               target="_blank"
               rel="noopener"
               class="btn btn-outline-primary btn-sm">
                <?php echo Text::_('MOD_PRETTYREVIEWS_VIEWALLREVIEWS'); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($reviews)) : ?>
        <p class="text-muted text-center py-3">
            <?php echo Text::_('MOD_PRETTYREVIEWS_NO_REVIEWS'); ?>
        </p>
    <?php else : ?>
        <div id="<?php echo $carouselId; ?>"
             class="carousel slide"
             <?php if (!empty($autoPlay)) : ?>data-bs-ride="carousel"<?php endif; ?>>
            <div class="carousel-inner">
                <?php foreach ($reviews as $slideIdx => $review) :
                    $photoUrl  = $safeUrl($review['profile_photo_url'] ?? '');
                    $authorUrl = $safeUrl($review['author_url'] ?? '');
                    $author    = $escape($review['author_name'] ?? '');
                    $rawText   = (string) ($review['text'] ?? '');
                    if ($maxChars > 0 && mb_strlen($rawText) > $maxChars) {
                        $rawText = mb_substr($rawText, 0, $maxChars) . '…';
                    }
                    $text         = $escape($rawText);
                    $timeAgo      = $escape($review['time_ago'] ?? '');
                    $reviewRating = (int) ($review['rating'] ?? 0);
                    ?>
                    <div class="carousel-item <?php echo ($slideIdx === 0) ? 'active' : ''; ?>">
                        <article class="card border-0">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3">
                                    <?php if ($showPhotos && $photoUrl !== '') : ?>
                                        <img src="<?php echo $photoUrl; ?>"
                                             class="rounded-circle flex-shrink-0"
                                             width="56"
                                             height="56"
                                             alt="<?php echo $author; ?>">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mb-2">
                                            <div>
                                                <?php if ($authorUrl !== '') : ?>
                                                    <a href="<?php echo $authorUrl; ?>"
                                                       class="h6 mb-0 text-body text-decoration-none d-block">
                                                        <?php echo $author; ?>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="h6 mb-0 d-block"><?php echo $author; ?></span>
                                                <?php endif; ?>
                                                <?php if ($showDate && $timeAgo !== '') : ?>
                                                <div class="small text-muted">
                                                    <?php echo $timeAgo; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-warning text-nowrap"
                                                 aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $reviewRating)); ?>">
                                                <?php for ($j = 1; $j <= 5; $j++) : ?>
                                                    <i class="<?php echo ($j <= $reviewRating) ? 'fas' : 'far'; ?> fa-star"
                                                       aria-hidden="true"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <?php if ($text !== '') : ?>
                                            <p class="mb-0 text-muted small"><?php echo $text; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($reviews) > 1) : ?>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button class="btn btn-outline-secondary btn-sm"
                        type="button"
                        data-bs-target="#<?php echo $carouselId; ?>"
                        data-bs-slide="prev">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo Text::_('JPREVIOUS'); ?></span>
                </button>
                <button class="btn btn-outline-secondary btn-sm"
                        type="button"
                        data-bs-target="#<?php echo $carouselId; ?>"
                        data-bs-slide="next">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo Text::_('JNEXT'); ?></span>
                </button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
