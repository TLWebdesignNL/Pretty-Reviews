<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers the helper as the dispatcher and the AJAX endpoint use it: turning stored
 * filenames into own-domain URLs for the layouts, what an old cache shows before its
 * first refresh, and purging.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\ImageCacheHelper;
use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$source = 'https://lh3.googleusercontent.com/a/AAcHT';
$helper = new PrettyreviewsHelper();
$images = new ImageCacheHelper();

$cache = static fn (int $moduleId): string => JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json';
$dir   = static fn (int $moduleId): string => JPATH_ROOT . '/media/mod_prettyreviews/images/' . $moduleId;

mkdir(JPATH_ROOT . '/media/mod_prettyreviews', 0777, true);

// A cache file as version 2.1.0 left it: Google URLs, and no photo keys at all.
$legacy = [
    'rating'       => 4.7,
    'ratingsCount' => 33,
    'url'          => 'https://maps.google/x',
    'reviews'      => [
        100 => ['time' => 100, 'author_name' => 'Ada Lovelace', 'text' => 'Great', 'rating' => 5, 'profile_photo_url' => $source . '1=s128-c'],
        200 => ['time' => 200, 'author_name' => 'Grace Hopper', 'text' => 'Good',  'rating' => 4, 'profile_photo_url' => $source . '2=s128-c'],
    ],
];

group('A cache from before photos were stored locally');
file_put_contents($cache(7), json_encode($legacy));
respondWith(200, pngBytes());
resetRequests();

// What the dispatcher does on a page view: filter for display, then swap in local URLs.
$reviews = $helper->present($helper->loadRaw(7), ['minRating' => 4, 'sort' => 'newest'])['reviews'];
$urls    = array_map(static fn ($review) => $helper->localPhotoUrl(7, $review), $reviews);

check(
    'every reviewer gets an initials avatar to stand in',
    count($urls) === 2
        && count(array_filter($urls, static fn ($url) => str_starts_with($url, 'data:image/svg+xml;base64,'))) === 2
);
check(
    'built from the reviewer\'s own name',
    str_contains(base64_decode(substr($urls[200], 26)), '>' . strtoupper(substr($legacy['reviews'][200]['author_name'], 0, 1)))
);
check('and a page view downloads nothing', requests() === []);
check('nor writes anything', !is_dir($dir(7)));

group('The first refresh fills it in');

// What refreshFromGoogle() does after merging: sync the photos, write the payload.
$synced = $images->syncReviewPhotos(7, $helper->loadRaw(7));
file_put_contents($cache(7), json_encode($synced));

check('the photos are downloaded', count(glob($dir(7) . '/*.png')) === 2);

$reviews = $helper->present($helper->loadRaw(7), ['minRating' => 4, 'sort' => 'newest'])['reviews'];

foreach ($reviews as $key => $review) {
    $reviews[$key]['profile_photo_url'] = $helper->localPhotoUrl(7, $review);
}

check('the rendered url points at this site', str_starts_with($reviews[100]['profile_photo_url'], 'https://example.test/media/mod_prettyreviews/images/7/'));

// Every layout, and every template override already in the wild, drops a photo url
// that is not http(s) -- so an absolute one is the only kind that renders.
check('and it is absolute, so the layouts do not discard it', preg_match('#^https?://#i', $reviews[200]['profile_photo_url']) === 1);
check('the initials avatar is gone now that there is a real photo', !str_contains($reviews[200]['profile_photo_url'], 'data:'));

$onDisk = json_decode(file_get_contents($cache(7)), true);
check('the google url is still there to re-fetch from', $onDisk['reviews'][100]['profile_photo_url'] === $source . '1=s128-c');
check('the rest of the payload is untouched', $onDisk['rating'] === 4.7 && $onDisk['ratingsCount'] === 33);

group('Refusing what a tampered cache file offers');
check('a filename the module did not write yields no url', $helper->localPhotoUrl(7, ['profile_photo_local' => 'evil.php']) === '');
check('a missing key yields no url', $helper->localPhotoUrl(7, ['author_name' => 'X']) === '');

group('Purging');
check('reviews and photos both go', $helper->purgeReviews(7) && !is_file($cache(7)) && !is_dir($dir(7)));

mkdir($dir(7), 0777, true);
file_put_contents($dir(7) . '/leftover.png', 'x');
check('photos go even when the cache file is already gone', $helper->purgeReviews(7) && !is_dir($dir(7)));
check('purging twice is harmless', $helper->purgeReviews(7));

finish();
