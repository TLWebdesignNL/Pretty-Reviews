<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers what happens while a page is rendered: collecting the photos a cache written
 * before this feature existed never stored, without slowing the page down and without
 * writing display data back into the cache.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$source = 'https://lh3.googleusercontent.com/a/AAcHT';
$helper = new PrettyreviewsHelper();

$cache = static fn (int $moduleId): string => JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json';
$dir   = static fn (int $moduleId): string => JPATH_ROOT . '/media/mod_prettyreviews/images/' . $moduleId;
$read  = static fn (int $moduleId): array => json_decode(file_get_contents($cache($moduleId)), true)['reviews'];

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

group('Collecting the photos an older cache never stored');
file_put_contents($cache(7), json_encode($legacy));
respondWith(200, pngBytes());

// What the dispatcher does: filter for display, collect the photos, swap in the URLs.
$reviews = $helper->present($helper->loadRaw(7), ['minRating' => 4, 'sort' => 'newest'])['reviews'];
$reviews = $helper->ensureLocalPhotos(7, $reviews);

check('the photos are downloaded', count(glob($dir(7) . '/*.png')) === 2);
check('the reviews being rendered know about them', isset($reviews[100]['profile_photo_local']));

$onDisk = json_decode(file_get_contents($cache(7)), true);
check('and so does the cache file', isset($onDisk['reviews'][100]['profile_photo_local']));
check('the google url is still there to re-fetch from', $onDisk['reviews'][100]['profile_photo_url'] === $source . '1=s128-c');
check('the rest of the payload is untouched', $onDisk['rating'] === 4.7 && $onDisk['ratingsCount'] === 33);
check('the rest of each review is untouched', $onDisk['reviews'][100]['text'] === 'Great');

foreach ($reviews as $key => $review) {
    $reviews[$key]['profile_photo_url'] = $helper->localPhotoUrl(7, $review);
}

check('the rendered url points at this site', str_starts_with($reviews[100]['profile_photo_url'], 'https://example.test/media/mod_prettyreviews/images/7/'));
check('and the layouts will accept it', preg_match('#^https?://#i', $reviews[200]['profile_photo_url']) === 1);

// Feed the rewritten reviews back in, as a second render would.
$helper->ensureLocalPhotos(7, $reviews);
$onDisk = json_decode(file_get_contents($cache(7)), true);

check('a second render leaves the google url alone', $onDisk['reviews'][100]['profile_photo_url'] === $source . '1=s128-c');
check('and never writes a local url into the cache', !str_contains(file_get_contents($cache(7)), 'example.test'));

group('Keeping the page quick');
$many = [];

for ($i = 0; $i < 6; $i++) {
    $many[300 + $i] = ['time' => 300 + $i, 'author_name' => "Person $i", 'rating' => 5, 'profile_photo_url' => $source . "m$i=s128-c"];
}

file_put_contents($cache(8), json_encode(['reviews' => $many]));
resetRequests();
$rendered = $helper->ensureLocalPhotos(8, $many);

check('only a few photos are fetched per render', count(requests()) === 3);
check('a short timeout is used', requests()[0]['timeout'] === 3);
check('every review still has something to show', count(array_filter($rendered, static fn ($r) => !empty($r['profile_photo_local']))) === 6);
check('the rest fall back to initials', count(array_filter($rendered, static fn ($r) => str_starts_with($r['profile_photo_local'], 'initials-'))) === 3);

resetRequests();
$helper->ensureLocalPhotos(8, $read(8));
check('the next render picks up where it left off', count(requests()) === 3);
check('after which every photo is stored', count(glob($dir(8) . '/*.png')) === 6);
check('and every review uses one', count(array_filter($read(8), static fn ($r) => str_ends_with($r['profile_photo_local'], '.png'))) === 6);

resetRequests();
$helper->ensureLocalPhotos(8, $read(8));
check('a settled cache downloads nothing at all', requests() === []);

group('Leaving the cleaning up to a refresh');
check('the superseded initials are still there', count(glob($dir(8) . '/initials-*.svg')) === 3);

// A render only ever sees the reviews that survived present(), so it must not decide
// that the others' photos are unused.
$helper->ensureLocalPhotos(8, array_slice($read(8), 0, 2, true));
check('photos of reviews not being rendered survive', count(glob($dir(8) . '/*.png')) === 6);

group('Coping with things going wrong');
respondWithFailure();
file_put_contents($cache(9), json_encode(['reviews' => [
    900 => ['time' => 900, 'author_name' => 'Net Down', 'profile_photo_url' => $source . 'n=s1'],
]]));
$offline = $helper->ensureLocalPhotos(9, $read(9));
check('a broken connection does not break the page', str_starts_with($offline[900]['profile_photo_local'], 'initials-'));

unlink($cache(9));
$noCache = $helper->ensureLocalPhotos(9, [900 => ['time' => 900, 'author_name' => 'X', 'profile_photo_url' => $source . 'n=s1']]);
check('a missing cache file does not break the page', is_array($noCache));
check('and no cache file is invented', !is_file($cache(9)));

check('there is nothing to do for module 0', $helper->ensureLocalPhotos(0, [1 => ['profile_photo_url' => $source]]) === [1 => ['profile_photo_url' => $source]]);
check('there is nothing to do without reviews', $helper->ensureLocalPhotos(7, []) === []);

group('Purging');
file_put_contents($cache(7), json_encode($legacy));
check('reviews and photos both go', $helper->purgeReviews(7) && !is_file($cache(7)) && !is_dir($dir(7)));

mkdir($dir(7), 0777, true);
file_put_contents($dir(7) . '/leftover.png', 'x');
check('photos go even when the cache file is already gone', $helper->purgeReviews(7) && !is_dir($dir(7)));
check('purging twice is harmless', $helper->purgeReviews(7));

finish();
