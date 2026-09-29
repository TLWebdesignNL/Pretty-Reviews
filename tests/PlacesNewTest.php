<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers fetching from Places API (New): the request it sends, how its answer is
 * turned into the legacy shape the cache and layouts expect, and how errors surface.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$helper     = new PrettyreviewsHelper();
$reflection = new ReflectionClass($helper);

$fetch = $reflection->getMethod('fetchFromPlacesNew');
$fetch->setAccessible(true);

$mergeReviews = $reflection->getMethod('mergeReviews');
$mergeReviews->setAccessible(true);

$place = [
    'rating'          => 4.7,
    'userRatingCount' => 23,
    'googleMapsUri'   => 'https://maps.google.com/?cid=123',
    'reviews'         => [
        [
            'name'                           => 'places/ChIJabc/reviews/1',
            'relativePublishTimeDescription' => 'vor einem Monat',
            'rating'                         => 5,
            'text'                           => ['text' => 'Sehr gut', 'languageCode' => 'de'],
            'originalText'                   => ['text' => 'Very good', 'languageCode' => 'en'],
            'authorAttribution'              => [
                'displayName' => 'Ada Lovelace',
                'uri'         => 'https://www.google.com/maps/contrib/1',
                'photoUri'    => 'https://lh3.googleusercontent.com/a/AAcHT1=s128-c0x00000000-cc-rp-mo',
            ],
            'publishTime'                    => '2026-08-01T10:20:30.123456789Z',
        ],
        [
            'rating'            => 4,
            'text'              => ['text' => 'Gut', 'languageCode' => 'de'],
            'authorAttribution' => ['displayName' => 'Grace Hopper'],
            'publishTime'       => '2026-07-01T08:00:00Z',
        ],
        [
            'rating' => 3,
            'text'   => ['text' => 'No time', 'languageCode' => 'de'],
        ],
    ],
];

group('The request');
resetRequests();
respondWith(200, json_encode($place));
$response = $fetch->invoke($helper, 'places/ChIJabc ', 'secret-key', 'de');
$request  = requests()[0];

check('it goes to the Places API (New) endpoint', $request['url'] === 'https://places.googleapis.com/v1/places/ChIJabc?languageCode=de');
check('the key travels in a header, not the url', $request['headers']['X-Goog-Api-Key'] === 'secret-key' && !str_contains($request['url'], 'secret-key'));
check('only the fields the module uses are requested', $request['headers']['X-Goog-FieldMask'] === 'rating,userRatingCount,googleMapsUri,reviews');

group('Converting the answer');
$result = $response->result;

check('the status reads OK like a legacy answer', $response->status === 'OK');
check('the summary uses the legacy names', $result->rating === 4.7 && $result->user_ratings_total === 23 && $result->url === 'https://maps.google.com/?cid=123');
check('a review without a publish time is left out', count($result->reviews) === 2);

$first = $result->reviews[0];

check('the publish time becomes the unix timestamp', $first->time === gmmktime(10, 20, 30, 8, 1, 2026));
check('the author fields use the legacy names', $first->author_name === 'Ada Lovelace' && $first->author_url === 'https://www.google.com/maps/contrib/1');
check('the photo url is kept for the photo cache', $first->profile_photo_url === 'https://lh3.googleusercontent.com/a/AAcHT1=s128-c0x00000000-cc-rp-mo');
check('the text is the one in the requested language', $first->text === 'Sehr gut' && $first->language === 'de');
check('a translation is marked as such', $first->translated === true && $first->original_language === 'en');
check('the rating and relative time survive', $first->rating === 5 && $first->relative_time_description === 'vor einem Monat');

$second = $result->reviews[1];

check('missing author details become empty strings', $second->author_url === '' && $second->profile_photo_url === '');
check('an untranslated review is not marked as translated', $second->translated === false);

group('Merging into the cache');
$merged = $mergeReviews->invoke($helper, $response, []);

check('reviews are keyed by their timestamp', array_keys($merged['reviews']) === [gmmktime(10, 20, 30, 8, 1, 2026), gmmktime(8, 0, 0, 7, 1, 2026)]);
check('the summary is stored', $merged['rating'] === 4.7 && $merged['ratingsCount'] === 23);

group('Errors');
// The message of the exception a fetch ends in, or an empty string when it succeeds.
$fetchError = function () use ($fetch, $helper): string {
    try {
        $fetch->invoke($helper, 'ChIJabc', 'key', 'de');
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    return '';
};

respondWith(403, json_encode(['error' => ['code' => 403, 'message' => 'Requests to this API are blocked.', 'status' => 'PERMISSION_DENIED']]));
check(
    'a google error names its status and message',
    $fetchError() === 'MOD_PRETTYREVIEWS_ERROR_GOOGLE_STATUS(PERMISSION_DENIED,Requests to this API are blocked.)'
);

respondWith(500, 'oops');
check('a non-json error falls back to the http status', $fetchError() === 'MOD_PRETTYREVIEWS_ERROR_GOOGLE_HTTP_STATUS(500)');

respondWith(200, 'not json');
check('an unreadable answer is rejected', $fetchError() === 'MOD_PRETTYREVIEWS_ERROR_GOOGLE_INVALID_RESPONSE');

respondWith(200, '{}');
check('an empty place is rejected', $fetchError() === 'MOD_PRETTYREVIEWS_ERROR_GOOGLE_EMPTY_RESULT');

respondWithFailure();
check('a network failure is reported', $fetchError() === 'MOD_PRETTYREVIEWS_ERROR_GOOGLE_REQUEST_FAILED');

finish();
