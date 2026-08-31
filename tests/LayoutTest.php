<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Renders every shipped layout.
 *
 * The helper suites check the URL a photo gets; this one checks that a layout actually
 * puts it in an <img>. They are not the same question: each layout runs a photo through
 * a guard of its own, and a URL shape that guard turns away renders as no photo at all
 * with every helper test still green.
 */

require_once __DIR__ . '/bootstrap.php';

use Joomla\CMS\Application\TestApplication;
use Joomla\Registry\TestRegistry;
use TLWeb\Module\Prettyreviews\Site\Helper\ImageCacheHelper;
use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$layouts = glob(\dirname(__DIR__) . '/tmpl/*.php');

/**
 * Render one layout with one review and hand back the markup.
 */
$render = static function (string $layout, array $review): string {
    $app       = new TestApplication();
    $module    = (object) ['id' => 7];
    $params    = new TestRegistry(['show_photos' => 1]);
    $reviewdata = [
        'rating'       => 4.7,
        'ratingsCount' => 33,
        'url'          => 'https://maps.google.com/place',
        'reviews'      => [100 => $review],
    ];
    $writeReviewUrl   = '';
    $carouselColumns  = ['mobile' => 1, 'tablet' => 2, 'desktop' => 4, 'wide' => 4];
    $autoplayInterval = 5;

    ob_start();
    include $layout;

    return (string) ob_get_clean();
};

/**
 * The src of the first <img> in some markup, or '' when there is none.
 */
$firstImage = static function (string $html): string {
    return preg_match('/<img[^>]*\ssrc="([^"]*)"/i', $html, $m) === 1 ? $m[1] : '';
};

$base = [
    'time'        => 100,
    'author_name' => 'Ada Lovelace',
    'author_url'  => 'https://www.google.com/maps/contrib/1',
    'rating'      => 5,
    'text'        => 'Excellent service.',
    'time_ago'    => '2 months ago',
];

$helper = new PrettyreviewsHelper();
$images = new ImageCacheHelper();

// The URLs below come from the helper rather than being written out here, so the test
// fails if the helper starts handing the layouts a shape they drop.
$photoUrlFor = static fn (array $review): string => $helper->localPhotoUrl(7, $review);

group('A photo stored on this site reaches the markup');

// A downloaded photo, as syncReviewPhotos() leaves it: a file on disk and its bare
// name in the cache.
$file = str_repeat('a', 32) . '.png';
$dir  = JPATH_ROOT . '/media/mod_prettyreviews/images/7';

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($dir . '/' . $file, pngBytes());

$stored = $base + [
    'profile_photo_url'   => 'https://lh3.googleusercontent.com/a/AAcHT1=s128-c',
    'profile_photo_local' => $file,
];

$storedUrl = $photoUrlFor($stored);
$stored['profile_photo_url'] = $storedUrl;

check('the helper points at the stored file', str_ends_with($storedUrl, '/media/mod_prettyreviews/images/7/' . $file));

foreach ($layouts as $layout) {
    $name = basename($layout);
    $src  = $firstImage($render($layout, $stored));

    check("$name renders the photo", $src === $storedUrl);
}

group('A cache from before 2.2.0 falls back to initials');

// Same review, minus the key a 2.1.0 cache never had.
$old      = $stored;
$old['profile_photo_url'] = 'https://lh3.googleusercontent.com/a/AAcHT1=s128-c';
unset($old['profile_photo_local']);

$initialsUrl = $photoUrlFor($old);
$old['profile_photo_url'] = $initialsUrl;

check('the helper falls back to an inline avatar', str_starts_with($initialsUrl, 'data:image/svg+xml;base64,'));
check(
    'and the avatar carries the reviewer initials',
    str_contains(base64_decode(substr($initialsUrl, 26)), '>AL<')
);

foreach ($layouts as $layout) {
    $name = basename($layout);
    $src  = $firstImage($render($layout, $old));

    check("$name renders the initials avatar", $src === $initialsUrl);
}

group('A review with no photo at all shows none');

$none = $base + ['profile_photo_url' => ''];

check('the helper offers nothing to render', $photoUrlFor($none) === '');

foreach ($layouts as $layout) {
    $name = basename($layout);

    check("$name renders no photo", $firstImage($render($layout, $none)) === '');
}

group('The photo guard still turns away what is not ours');

foreach ([
    'a javascript url'       => 'javascript:alert(1)',
    'an html data uri'       => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    'an unencoded svg uri'   => 'data:image/svg+xml,<svg onload="alert(1)"/>',
    'a data uri with markup' => 'data:image/svg+xml;base64,abc"onerror="alert(1)',
] as $label => $url) {
    $offenders = [];

    foreach ($layouts as $layout) {
        if ($firstImage($render($layout, $base + ['profile_photo_url' => $url])) !== '') {
            $offenders[] = basename($layout);
        }
    }

    check("refuses $label" . ($offenders === [] ? '' : ' (rendered by ' . implode(', ', $offenders) . ')'), $offenders === []);
}
