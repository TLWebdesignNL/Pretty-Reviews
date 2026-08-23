<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers ImageCacheHelper on its own: storing, naming, de-duplicating, refusing and
 * pruning photos, and the initials avatars it falls back to.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\ImageCacheHelper;

$dir    = JPATH_ROOT . '/media/mod_prettyreviews/images/7';
$source = 'https://lh3.googleusercontent.com/a/AAcHT';
$images = new ImageCacheHelper();

$review = static fn (int $time, string $name, string $url): array => [
    'time'              => $time,
    'author_name'       => $name,
    'profile_photo_url' => $url,
];

group('Storing photos');
respondWith(200, pngBytes());
resetRequests();

$stored = $images->syncReviewPhotos(7, ['reviews' => [
    100 => $review(100, 'Ada Lovelace', $source . '1=s128-c-rp-mo'),
    200 => $review(200, 'Grace Hopper', $source . '2=s50-c'),
]]);

check('a file is stored per reviewer', count(glob($dir . '/*.png')) === 2);
check('the review points at it', preg_match('/^[0-9a-f]{32}\.png$/', $stored['reviews'][100]['profile_photo_local']) === 1);
check('no attempt marker is left behind', !isset($stored['reviews'][100]['profile_photo_attempt']));
check('the google url is left untouched', $stored['reviews'][100]['profile_photo_url'] === $source . '1=s128-c-rp-mo');
check('one size is requested for every avatar', str_ends_with(requests()[0]['url'], '=s256-c'));
check(
    'the photo is served from this site',
    $images->publicPhotoUrl(7, $stored['reviews'][100]['profile_photo_local'])
        === 'https://example.test/media/mod_prettyreviews/images/7/' . $stored['reviews'][100]['profile_photo_local']
);
check('no initials avatar is written when the download works', glob($dir . '/initials-*.svg') === []);

respondWith(200, jpegBytes());
$jpeg = $images->syncReviewPhotos(7, ['reviews' => [700 => $review(700, 'Jay Peg', $source . 'j=s1')]], 25, 10, false);
check('the extension comes from the bytes, not the url', str_ends_with($jpeg['reviews'][700]['profile_photo_local'], '.jpg'));

group('Recognising a photo it already has');
respondWith(200, pngBytes());
resetRequests();
$again = $images->syncReviewPhotos(7, ['reviews' => [
    100 => $stored['reviews'][100],
    200 => $stored['reviews'][200],
    // The same avatar as review 100, offered at a different size by Google.
    300 => $review(300, 'Ada Lovelace', $source . '1=s50-c'),
]]);

check('nothing is downloaded twice', requests() === []);
check('a shared avatar is stored once', count(glob($dir . '/*.png')) === 2);
check('both reviews use the one file', $again['reviews'][300]['profile_photo_local'] === $again['reviews'][100]['profile_photo_local']);

group('Falling back to initials');
respondWith(404, '');
resetRequests();
$failed = $images->syncReviewPhotos(7, ['reviews' => [400 => $review(400, 'Katherine Johnson', $source . '9=s128-c')] + $again['reviews']]);

check('an initials avatar takes over', preg_match('/^initials-[0-9a-f]{32}\.svg$/', $failed['reviews'][400]['profile_photo_local']) === 1);
check('it is written to disk', is_file($dir . '/' . $failed['reviews'][400]['profile_photo_local']));
check('it carries the reviewer initials', str_contains(file_get_contents($dir . '/' . $failed['reviews'][400]['profile_photo_local']), '>KJ<'));
check('the failure is remembered', ($failed['reviews'][400]['profile_photo_attempt'] ?? 0) > 0);
check('the original url is tried when the resized one fails', count(requests()) === 2);

resetRequests();
$render = $images->syncReviewPhotos(7, $failed, 3, 3, false);
check('a page render does not retry a fresh failure', requests() === []);
check('the initials avatar stays while it is in use', is_file($dir . '/' . $render['reviews'][400]['profile_photo_local']));

resetRequests();
$images->syncReviewPhotos(7, $failed);
check('an explicit refresh tries again straight away', requests() !== []);

group('Refusing photos from anywhere but Google');
respondWith(200, pngBytes());

foreach ([
    'plain http'              => 'http://lh3.googleusercontent.com/a/x',
    'another site'            => 'https://example.com/a/x',
    'the machine itself'      => 'https://127.0.0.1/a/x',
    'a lookalike host'        => 'https://evilgoogleusercontent.com/a/x',
    'an explicit port'        => 'https://lh3.googleusercontent.com:8080/a/x',
    'credentials in the url'  => 'https://evil.com@lh3.googleusercontent.com/a/x',
] as $label => $url) {
    resetRequests();
    $refused = $images->syncReviewPhotos(7, ['reviews' => [500 => $review(500, 'X', $url)]], 25, 10, false);

    check(
        "refuses $label",
        requests() === [] && str_starts_with($refused['reviews'][500]['profile_photo_local'], 'initials-')
    );
}

group('Refusing anything that is not an image');
foreach ([
    'a web page'        => '<html>not an image</html>',
    'a pile of bytes'   => str_repeat('x', 2097153),
    'an empty response' => '',
    'an svg'            => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    // A real PNG header with padding after it: this one decodes as an image, so only
    // the size cap can turn it away.
    'an oversized image' => pngBytes() . str_repeat('x', 2100000),
] as $label => $body) {
    respondWith(200, $body);
    $refused = $images->syncReviewPhotos(7, ['reviews' => [600 => $review(600, 'Y', $source . 'z=s1')]], 25, 10, false);

    check("refuses $label", str_starts_with($refused['reviews'][600]['profile_photo_local'], 'initials-'));
}

respondWith(200, pngBytes() . str_repeat('x', 2000));
$justUnder = $images->syncReviewPhotos(7, ['reviews' => [610 => $review(610, 'W', $source . 'w=s1')]], 25, 10, false);
check('accepts an image comfortably under the cap', str_ends_with($justUnder['reviews'][610]['profile_photo_local'], '.png'));

group('Refusing a tampered filename');
foreach (['../../../configuration.php', 'evil.php', '/etc/passwd', 'a1.jpg', '', 'initials-zz.svg'] as $name) {
    check("refuses to serve '$name'", $images->publicPhotoUrl(7, $name) === '');
}

check('refuses to serve a name with no file behind it', $images->publicPhotoUrl(7, str_repeat('a', 32) . '.jpg') === '');

// The names above are refused on sight. Put real files where a tampered cache file
// could point, so the refusal cannot be coming from the file simply not being there.
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($dir . '/evil.php', '<?php echo 1;');
file_put_contents(\dirname($dir) . '/escaped.png', 'x');
check('refuses a real file with a name it did not write', $images->publicPhotoUrl(7, 'evil.php') === '');
check('refuses to climb out of the module folder', $images->publicPhotoUrl(7, '../escaped.png') === '');
unlink($dir . '/evil.php');
unlink(\dirname($dir) . '/escaped.png');

group('Budgeting downloads');
respondWith(200, pngBytes());
$many = [];

for ($i = 0; $i < 6; $i++) {
    $many[800 + $i] = $review(800 + $i, "Person $i", $source . "m$i=s128-c");
}

resetRequests();
$before  = Joomla\Filesystem\Folder::files($dir);
$partial = $images->syncReviewPhotos(7, ['reviews' => $many], 3, 10);
$after   = Joomla\Filesystem\Folder::files($dir);

check('only the allowed number are downloaded', count(requests()) === 3);
check('a partial run deletes nothing', array_diff($before, $after) === []);
check('the reviews it did not reach still show something', count(array_filter(
    $partial['reviews'],
    static fn ($one) => !empty($one['profile_photo_local'])
)) === 6);
check('it reports how many were left for the next run', $images->pendingDownloads() === 3);

$images->syncReviewPhotos(7, $partial, 25, 10);
check('a run that finishes reports nothing pending', $images->pendingDownloads() === 0);

// A photo whose download fails is not "pending": pressing the button again straight
// away would not help it, so it must not keep the message alive.
respondWith(404, '');
$images->syncReviewPhotos(7, ['reviews' => [850 => $review(850, 'Dead Url', $source . 'dead=s1')]], 25, 10);
check('a failed download is not reported as pending', $images->pendingDownloads() === 0);
respondWith(200, pngBytes());

group('Pruning');
$keeper = $partial['reviews'][800]['profile_photo_local'];
$images->syncReviewPhotos(7, ['reviews' => [800 => $partial['reviews'][800]]], 25, 10);
check('a full run keeps only what is still referenced', Joomla\Filesystem\Folder::files($dir) === [$keeper]);

file_put_contents($dir . '/.htaccess', 'x');
file_put_contents($dir . '/index.html', '');
file_put_contents($dir . '/notes.txt', 'something an administrator put here');
$images->syncReviewPhotos(7, ['reviews' => [800 => $partial['reviews'][800]]], 25, 10);

check('index.html survives', is_file($dir . '/index.html'));
check('.htaccess survives', is_file($dir . '/.htaccess'));
check('files the module did not write survive', is_file($dir . '/notes.txt'));

group('Purging');
check('the module folder goes', $images->purgeModuleImages(7) && !is_dir($dir));
check('purging an already empty module succeeds', $images->purgeModuleImages(7));
check('there is nothing to purge for module 0', !$images->purgeModuleImages(0));

group('Initials');
$initialsFor = static function (string $name) use ($images): string {
    $result = $images->syncReviewPhotos(
        9,
        ['reviews' => [1 => ['time' => 1, 'author_name' => $name, 'profile_photo_url' => 'https://example.com/x']]],
        0,
        10,
        false
    );

    return file_get_contents(JPATH_ROOT . '/media/mod_prettyreviews/images/9/' . $result['reviews'][1]['profile_photo_local']);
};

foreach ([
    'Ada Lovelace'     => '>AL<',
    'Ada'              => '>A<',
    '  Renée  Dupont ' => '>RD<',
    '李 明'             => '>李明<',
] as $name => $expected) {
    check("'$name' becomes $expected", str_contains($initialsFor($name), $expected));
}

$punctuation = $initialsFor('!!! ###');
check('a name with no letters gets a plain avatar', !str_contains($punctuation, '<text'));

$markup = $initialsFor('<script>alert(1)</script>');
check('markup in a name cannot reach the avatar', !str_contains($markup, 'script') && !str_contains($markup, 'alert'));
check('the avatar is well formed', simplexml_load_string($markup) !== false);

group('Payloads it should leave alone');
$empty = $images->syncReviewPhotos(7, ['reviews' => [1 => $review(1, 'No Photo', '')]], 25, 10, false);
check('a review with no photo gets no local file', !isset($empty['reviews'][1]['profile_photo_local']));

$untouched = ['reviews' => [1 => ['profile_photo_url' => $source]]];
check('module 0 is left alone', $images->syncReviewPhotos(0, $untouched) === $untouched);
check('an empty payload is left alone', $images->syncReviewPhotos(7, []) === []);

$odd = $images->syncReviewPhotos(7, ['reviews' => [1 => 'not a review']], 25, 10, false);
check('an entry that is not a review is skipped', $odd['reviews'][1] === 'not a review');

finish();
