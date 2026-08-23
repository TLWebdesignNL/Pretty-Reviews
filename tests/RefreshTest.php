<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers a refresh: how a Google response is merged into the cache, and how the stored
 * photos follow the reviews they belong to over successive refreshes.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\ImageCacheHelper;
use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$source = 'https://lh3.googleusercontent.com/a/AAcHT';
$dir    = JPATH_ROOT . '/media/mod_prettyreviews/images/5';
$helper = new PrettyreviewsHelper();
$images = new ImageCacheHelper();

mkdir(JPATH_ROOT . '/media/mod_prettyreviews', 0777, true);

$mergeReviews = (new ReflectionClass($helper))->getMethod('mergeReviews');
$mergeReviews->setAccessible(true);

$response = json_decode(json_encode(['result' => [
    'rating'             => 4.8,
    'user_ratings_total' => 12,
    'url'                => 'https://maps.google/x',
    'reviews'            => [
        ['time' => 100, 'author_name' => 'Ada Lovelace', 'rating' => 5, 'text' => 'Great', 'profile_photo_url' => $source . '1=s128-c'],
        ['time' => 200, 'author_name' => 'Grace Hopper', 'rating' => 4, 'text' => '',      'profile_photo_url' => $source . '2=s128-c'],
    ],
]]));

group('Merging a Google response');
$merged = $mergeReviews->invoke($helper, $response, []);

check('reviews are stored as arrays', is_array($merged['reviews'][100]));
check('every field survives', $merged['reviews'][100]['author_name'] === 'Ada Lovelace' && $merged['reviews'][100]['text'] === 'Great');
check('the summary is stored', $merged['rating'] === 4.8 && $merged['ratingsCount'] === 12);

// Storing reviews as arrays must not change a single byte of what is written out,
// or existing cache files would no longer match what the module produces.
$asVersion210 = [];

foreach ($response->result->reviews as $one) {
    $asVersion210[$one->time] = $one;
}

check('the cache file is byte for byte what 2.1.0 wrote', json_encode($merged) === json_encode([
    'rating'       => 4.8,
    'ratingsCount' => 12,
    'url'          => 'https://maps.google/x',
    'reviews'      => $asVersion210,
]));

$merged['reviews'][100]['text'] = 'edited after caching';
$second = $mergeReviews->invoke($helper, $response, $merged);
check('a review already cached is never overwritten', $second['reviews'][100]['text'] === 'edited after caching');

group('A refresh that cannot reach the photos');
respondWith(500, '');
$offline = $images->syncReviewPhotos(5, $merged);

check('every reviewer gets an initials avatar', count(glob($dir . '/initials-*.svg')) === 2);
check('no photo is stored', glob($dir . '/*.png') === []);
check('the failure is remembered', ($offline['reviews'][100]['profile_photo_attempt'] ?? 0) > 0);

resetRequests();
$images->syncReviewPhotos(5, $offline, 3, 3, false);
check('a page render in the meantime does not retry', requests() === []);

group('The refresh after that');
respondWith(200, pngBytes());
$online = $images->syncReviewPhotos(5, $offline);

check('somebody asking for a refresh overrides the wait', count(glob($dir . '/*.png')) === 2);
check('the initials avatars are cleaned up', glob($dir . '/initials-*.svg') === []);
check('the reviews point at the photos', str_ends_with($online['reviews'][100]['profile_photo_local'], '.png'));
check('the attempt marker is dropped', !isset($online['reviews'][100]['profile_photo_attempt']));

group('Reviews and photos going out of the cache together');
$reduced = $online;
unset($reduced['reviews'][200]);
$images->syncReviewPhotos(5, $reduced);

check('the photo of a dropped review goes with it', count(glob($dir . '/*.png')) === 1);
check('the remaining photo stays', is_file($dir . '/' . $online['reviews'][100]['profile_photo_local']));

group('Google handing out a new url for the same reviewer');
$superseded          = $online['reviews'][100]['profile_photo_local'];
$rotated             = $reduced;
$rotated['reviews'][100]['profile_photo_url'] = $source . 'ROTATED=s128-c';
$refreshed           = $images->syncReviewPhotos(5, $rotated);

check('the new photo is stored', $refreshed['reviews'][100]['profile_photo_local'] !== $superseded);
check('the old one is cleaned up', !is_file($dir . '/' . $superseded));
check('leaving one photo behind', count(glob($dir . '/*.png')) === 1);

finish();
