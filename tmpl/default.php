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
$autoPlay = $params->get("autoplay", true);

$googleReviews = $reviewdata;
?>

<div class="prettyReviewsWrapper">
    <div class="overal-rating text-center">
        <p class="h5"><?php echo Text::sprintf('MOD_PRETTYREVIEWS_OVERAL_RATING_HEADING', $reviewdata['rating'], $googleReviews['ratingsCount'] ); ?></p>
    </div>
    <hr class="text-muted">
    <div id="prettyReviewsCarousel<?php echo $module->id; ?>"
         class="carousel slide"
         <?php if (!empty($autoPlay)) : ?>
            data-bs-ride="carousel"
         <?php endif; ?>
         style="max-height">
        <div class="carousel-inner">
            <?php
            foreach ($googleReviews['reviews'] as $review) :
                ?>
                <div class="carousel-item <?php
                echo ($slideCounter == 0) ? 'active' : ''; ?>"
                     >
                    <div class="w-100 h-100 d-flex flex-column text-center align-items-center">
                        <img src="<?php
                        echo $review['profile_photo_url']; ?>"
                             class="review-profilephoto rounded-circle shadow-1-strong mb-4"
                             width="128px"
                             height="128px"
                        >
                        <div class="row d-flex justify-content-center">
                            <div class="col-lg-8">
                                <h4 class="mb-3">
                                    <a href="<?php
                                    echo $review['author_url']; ?>" class="review-author-name text-primary">
                                        <?php
                                        echo $review['author_name']; ?>
                                    </a></h4>
                                <p class="review-time">
                                    <?php echo PrettyreviewsHelper::timeAgo($review['time']); ?>
                                </p>
                                <?php if (!empty($review['text'])) : ?>
                                    <p class="text-muted">
                                        <i class="fas fa-quote-left pe-2"></i>
                                        <?php echo $review['text']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="list-unstyled d-flex justify-content-center text-warning mb-4">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                $fontAsomeClass = "far";
                                if ($i <= $review['rating']) {
                                    $fontAsomeClass = "fas";
                                }
                                echo '<li><i class="' . $fontAsomeClass . ' fa-star"></i></li>';
                            }
                            ?>
                        </ul>
                    </div>
                </div>
                <?php
                $slideCounter++; ?>
                <?php
            endforeach; ?>
        </div>
        <button
                class="carousel-control-prev"
                type="button"
                data-bs-target="#prettyReviewsCarousel<?php
                echo $module->id; ?>"
                data-bs-slide="prev"
        >
            <span class="carousel-control-prev-icon bg-light" aria-hidden="true"></span>
            <span class="visually-hidden"><?php
                echo Text::_('JPREVIOUS'); ?></span>
        </button>
        <button class="carousel-control-next"
                type="button"
                data-bs-target="#prettyReviewsCarousel<?php
                echo $module->id; ?>"
                data-bs-slide="next"
        >
            <span class="carousel-control-next-icon  bg-light" aria-hidden="true"></span>
            <span class="visually-hidden"><?php
                echo Text::_('JNEXT'); ?></span>
        </button>
    </div>
    <div class="w-100 text-center">
        <?php if (isset($googleReviews['url']) && $googleReviews['url']) : ?>
            <a href="<?php echo $googleReviews['url']; ?>" target="_blank" class="btn btn-primary"><?php echo Text::_('MOD_PRETTYREVIEW_VIEWALLREVIEWS'); ?></a>
        <?php endif; ?>
    </div>
</div>
