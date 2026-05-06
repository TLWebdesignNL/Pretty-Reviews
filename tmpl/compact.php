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
use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$carouselId = 'prettyReviewsCarousel' . (int) $module->id;

HTMLHelper::_('bootstrap.carousel', $carouselId);

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

<div class="prettyreviews prettyreviews-compact">

    <?php if ($showRatingSummary || ($showViewAll && $reviewsUrl !== '')) : ?>
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <?php if ($showRatingSummary) : ?>
        <div class="d-flex align-items-center gap-2">
            <strong><?php echo $rating; ?></strong>
            <span class="text-warning small"
                  aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $rating)); ?>">
                <?php for ($i = 1; $i <= 5; $i++) : ?>
                    <i class="<?php echo ($i <= (int) round($rating)) ? 'fas' : 'far'; ?> fa-star"
                       aria-hidden="true"></i>
                <?php endfor; ?>
            </span>
            <?php if ($showReviewCount && $ratingsCount > 0) : ?>
                <span class="small text-muted">(<?php echo $ratingsCount; ?>)</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($showViewAll && $reviewsUrl !== '') : ?>
            <a href="<?php echo $reviewsUrl; ?>"
               target="_blank"
               rel="noopener"
               class="small text-primary text-decoration-none text-nowrap">
                <?php echo Text::_('MOD_PRETTYREVIEWS_VIEWALLREVIEWS'); ?>
                <i class="fas fa-arrow-right fa-xs" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($reviews)) : ?>
        <p class="small text-muted">
            <?php echo Text::_('MOD_PRETTYREVIEWS_NO_REVIEWS'); ?>
        </p>
    <?php else : ?>
        <div id="<?php echo $carouselId; ?>"
             class="carousel slide"
             <?php if ($autoPlay) : ?>data-bs-ride="carousel"<?php endif; ?>>
            <div class="carousel-inner">
                <?php foreach ($reviews as $slideIdx => $review) :
                    $photoUrl     = $safeUrl($review['profile_photo_url'] ?? '');
                    $authorUrl    = $safeUrl($review['author_url'] ?? '');
                    $author       = $escape($review['author_name'] ?? '');
                    $rawText      = (string) ($review['text'] ?? '');
                    if ($maxChars > 0 && mb_strlen($rawText) > $maxChars) {
                        $rawText = mb_substr($rawText, 0, $maxChars) . '…';
                    }
                    $text         = $escape($rawText);
                    $time         = (int) ($review['time'] ?? 0);
                    $reviewRating = (int) ($review['rating'] ?? 0);
                    ?>
                    <div class="carousel-item <?php echo ($slideIdx === 0) ? 'active' : ''; ?>">
                        <div class="d-flex align-items-start gap-2">
                            <?php if ($showPhotos && $photoUrl !== '') : ?>
                                <img src="<?php echo $photoUrl; ?>"
                                     class="rounded-circle flex-shrink-0"
                                     width="40"
                                     height="40"
                                     alt="<?php echo $author; ?>">
                            <?php endif; ?>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <?php if ($authorUrl !== '') : ?>
                                        <a href="<?php echo $authorUrl; ?>"
                                           class="fw-semibold small text-body text-decoration-none text-truncate">
                                            <?php echo $author; ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="fw-semibold small text-truncate"><?php echo $author; ?></span>
                                    <?php endif; ?>
                                    <div class="text-warning text-nowrap small"
                                         aria-label="<?php echo $escape(Text::sprintf('MOD_PRETTYREVIEWS_RATING_ARIA', $reviewRating)); ?>">
                                        <?php for ($j = 1; $j <= 5; $j++) : ?>
                                            <i class="<?php echo ($j <= $reviewRating) ? 'fas' : 'far'; ?> fa-star"
                                               aria-hidden="true"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if ($text !== '') : ?>
                                    <p class="small text-muted mb-1"><?php echo $text; ?></p>
                                <?php endif; ?>
                                <?php if ($showDate) : ?>
                                    <div class="small text-muted">
                                        <?php echo $escape(PrettyreviewsHelper::timeAgo($time)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (count($reviews) > 1) : ?>
            <div class="d-flex justify-content-between mt-2">
                <button class="btn btn-link btn-sm p-0 text-muted"
                        type="button"
                        data-bs-target="#<?php echo $carouselId; ?>"
                        data-bs-slide="prev">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    <span class="visually-hidden"><?php echo Text::_('JPREVIOUS'); ?></span>
                </button>
                <button class="btn btn-link btn-sm p-0 text-muted"
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
