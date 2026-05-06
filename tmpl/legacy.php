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

HTMLHelper::_('bootstrap.carousel', 'prettyReviewsCarousel' . $module->id);

$slideCounter = 0;
$autoPlay          = (bool) $params->get('autoplay', 1);
$showRatingSummary = (bool) $params->get('show_rating_summary', 1);
$showPhotos        = (bool) $params->get('show_photos', 1);
$showDate          = (bool) $params->get('show_date', 1);
$showViewAll       = (bool) $params->get('show_viewall', 1);

$googleReviews = $reviewdata;

$escape  = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$safeUrl = static function ($url) use ($escape): string {
    $url = trim((string) ($url ?? ''));
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return '';
    }
    return $escape($url);
};

$rating       = (float) ($reviewdata['rating'] ?? 0);
$ratingsCount = (int) ($googleReviews['ratingsCount'] ?? 0);
$reviews      = $googleReviews['reviews'] ?? [];
?>

<div class="prettyReviewsWrapper">
    <?php if ($showRatingSummary) : ?>
    <div class="overal-rating text-center">
        <p class="h5"><?php echo Text::sprintf('MOD_PRETTYREVIEWS_OVERAL_RATING_HEADING', $rating, $ratingsCount); ?></p>
    </div>
    <hr class="text-muted">
    <?php endif; ?>
    <?php if (empty($reviews)) : ?>
        <p class="text-muted text-center"><?php echo Text::_('MOD_PRETTYREVIEWS_NO_REVIEWS'); ?></p>
    <?php else : ?>
        <div id="prettyReviewsCarousel<?php echo (int) $module->id; ?>"
             class="carousel slide"
             <?php if (!empty($autoPlay)) : ?>
                data-bs-ride="carousel"
             <?php endif; ?>>
            <div class="carousel-inner">
                <?php
                foreach ($reviews as $review) :
                    $photoUrl     = $safeUrl($review['profile_photo_url'] ?? '');
                    $authorUrl    = $safeUrl($review['author_url'] ?? '');
                    $author       = $escape($review['author_name'] ?? '');
                    $text         = $escape($review['text'] ?? '');
                    $time         = (int) ($review['time'] ?? 0);
                    $reviewRating = (int) ($review['rating'] ?? 0);
                    ?>
                    <div class="carousel-item <?php echo ($slideCounter == 0) ? 'active' : ''; ?>">
                        <div class="w-100 h-100 d-flex flex-column text-center align-items-center">
                            <?php if ($showPhotos && $photoUrl !== '') : ?>
                                <img src="<?php echo $photoUrl; ?>"
                                     class="review-profilephoto rounded-circle shadow-1-strong mb-4"
                                     width="128px"
                                     height="128px"
                                     alt="">
                            <?php endif; ?>
                            <div class="row d-flex justify-content-center">
                                <div class="col-lg-8">
                                    <h4 class="mb-3">
                                        <?php if ($authorUrl !== '') : ?>
                                            <a href="<?php echo $authorUrl; ?>" class="review-author-name text-primary">
                                                <?php echo $author; ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="review-author-name text-primary"><?php echo $author; ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <?php if ($showDate) : ?>
                                    <p class="review-time">
                                        <?php echo $escape(PrettyreviewsHelper::timeAgo($time)); ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php if ($text !== '') : ?>
                                        <p class="text-muted">
                                            <i class="fas fa-quote-left pe-2"></i>
                                            <?php echo $text; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <ul class="list-unstyled d-flex justify-content-center text-warning mb-4">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    $fontAsomeClass = ($i <= $reviewRating) ? 'fas' : 'far';
                                    echo '<li><i class="' . $fontAsomeClass . ' fa-star"></i></li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                    <?php $slideCounter++; ?>
                <?php endforeach; ?>
            </div>
            <button
                    class="carousel-control-prev"
                    type="button"
                    data-bs-target="#prettyReviewsCarousel<?php echo (int) $module->id; ?>"
                    data-bs-slide="prev"
            >
                <span class="carousel-control-prev-icon bg-light" aria-hidden="true"></span>
                <span class="visually-hidden"><?php echo Text::_('JPREVIOUS'); ?></span>
            </button>
            <button class="carousel-control-next"
                    type="button"
                    data-bs-target="#prettyReviewsCarousel<?php echo (int) $module->id; ?>"
                    data-bs-slide="next"
            >
                <span class="carousel-control-next-icon bg-light" aria-hidden="true"></span>
                <span class="visually-hidden"><?php echo Text::_('JNEXT'); ?></span>
            </button>
        </div>
        <div class="w-100 text-center">
            <?php
            $reviewsUrl = $safeUrl($googleReviews['url'] ?? '');
            if ($showViewAll && $reviewsUrl !== '') : ?>
                <a href="<?php echo $reviewsUrl; ?>" target="_blank" rel="noopener" class="btn btn-primary"><?php echo Text::_('MOD_PRETTYREVIEW_VIEWALLREVIEWS'); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>