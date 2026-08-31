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
check('the folder it made is not browsable', is_file($dir . '/index.html'));
check('the review points at it', preg_match('/^[0-9a-f]{32}\.png$/', $stored['reviews'][100]['profile_photo_local']) === 1);
check('the google url is left untouched', $stored['reviews'][100]['profile_photo_url'] === $source . '1=s128-c-rp-mo');
check('one size is requested for every avatar', str_ends_with(requests()[0]['url'], '=s256-c'));
check(
    'the photo is served from this site',
    $images->publicPhotoUrl(7, $stored['reviews'][100]['profile_photo_local'])
        === 'https://example.test/media/mod_prettyreviews/images/7/' . $stored['reviews'][100]['profile_photo_local']
);
check('no initials avatar is written when the download works', glob($dir . '/initials-*.svg') === []);

respondWith(200, jpegBytes());
$jpeg = $images->syncReviewPhotos(71, ['reviews' => [700 => $review(700, 'Jay Peg', $source . 'j=s1')]]);
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
check('the original url is tried when the resized one fails', count(requests()) === 2);
check('the initials avatar survives the run', is_file($dir . '/' . $failed['reviews'][400]['profile_photo_local']));

resetRequests();
$images->syncReviewPhotos(7, $failed);
check('the next refresh tries the failed photo again', requests() !== []);

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
    $refused = $images->syncReviewPhotos(72, ['reviews' => [500 => $review(500, 'X', $url)]]);

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
    $refused = $images->syncReviewPhotos(73, ['reviews' => [600 => $review(600, 'Y', $source . 'z=s1')]]);

    check("refuses $label", str_starts_with($refused['reviews'][600]['profile_photo_local'], 'initials-'));
}

respondWith(200, pngBytes() . str_repeat('x', 2000));
$justUnder = $images->syncReviewPhotos(73, ['reviews' => [610 => $review(610, 'W', $source . 'w=s1')]]);
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

group('Budgeting time, not photos');

// A clock the test controls: each download costs six "seconds", so the default
// fifteen-second budget fits exactly three of them.
$metered = new class () extends ImageCacheHelper {
    public $clock = 0.0;

    protected function now(): float
    {
        return $this->clock;
    }
};

\Joomla\CMS\Http\TestHttp::$handler = static function ($url) use ($metered) {
    $metered->clock += 6.0;

    return new \Joomla\CMS\Http\TestResponse(200, pngBytes());
};

$many = [];

for ($i = 0; $i < 6; $i++) {
    $many[800 + $i] = $review(800 + $i, "Person $i", $source . "m$i=s128-c");
}

resetRequests();
$partial = $metered->syncReviewPhotos(7, ['reviews' => $many]);
$after   = Joomla\Filesystem\Folder::files($dir);
$wanted  = array_column($partial['reviews'], 'profile_photo_local');

check('downloads stop when the time is spent', count(requests()) === 3);

// Nine seconds left, then three: a download is given what the budget has left rather
// than the full timeout, so the run cannot overshoot by a whole request.
check('each download gets only the time that is left', array_column(requests(), 'timeout') === [10, 9, 3]);

check('a partial run keeps everything the payload points at', array_diff($wanted, $after) === []);

// Running out of time skips a download, not an iteration, so a partial run has still
// seen every review: what none of them points at is an orphan either way.
check('a partial run still prunes what nothing points at', array_diff($after, $wanted, ['index.html']) === []);

check('the reviews it did not reach still show something', count(array_filter(
    $partial['reviews'],
    static fn ($one) => !empty($one['profile_photo_local'])
)) === 6);
check('it reports how many were left for the next run', $metered->pendingDownloads() === 3);

resetRequests();
$partial = $metered->syncReviewPhotos(7, $partial);
check('the next run picks up the remainder', count(requests()) === 3);
check('a run that finishes reports nothing pending', $metered->pendingDownloads() === 0);

// With nothing slowing the clock down, one run swallows a whole backlog: the point
// of a time budget over a count is that a fast connection is not held back.
respondWith(200, pngBytes());
$burst = [];

for ($i = 0; $i < 40; $i++) {
    $burst[2000 + $i] = $review(2000 + $i, "Fast $i", $source . "f$i=s128-c");
}

resetRequests();
$images->syncReviewPhotos(7, ['reviews' => $burst]);
check('a fast connection fills a large cache in one run', count(requests()) === 40);
check('with nothing left pending', $images->pendingDownloads() === 0);

// A photo whose download fails is not "pending": pressing the button again straight
// away would not help it, so it must not keep the message alive.
respondWith(404, '');
$images->syncReviewPhotos(74, ['reviews' => [850 => $review(850, 'Dead Url', $source . 'dead=s1')]]);
check('a failed download is not reported as pending', $images->pendingDownloads() === 0);

// The retry exists in case Google changes its sizing syntax, so it is worth nothing
// when normalising left the url alone -- and an identical second request would spend
// the budget twice over.
resetRequests();
$images->syncReviewPhotos(75, ['reviews' => [900 => $review(900, 'Same Url', $source . 'n=s256-c')]]);
check('a url that normalises to itself is not requested twice', count(requests()) === 1);

resetRequests();
$images->syncReviewPhotos(76, ['reviews' => [910 => $review(910, 'Other Url', $source . 'o=s128-c')]]);
check('a url that normalises to something else is retried as it came', count(requests()) === 2);

respondWith(200, pngBytes());

group('Redirects');

// Joomla's transports follow redirects themselves by default, which would put only the
// first url through the host check. The module turns that off and walks the chain here,
// so every hop is checked before it is requested.
$redirect = static function (string $to): callable {
    return static function ($url) use ($to) {
        return str_contains($url, 'hop')
            ? new \Joomla\CMS\Http\TestResponse(200, pngBytes())
            : new \Joomla\CMS\Http\TestResponse(302, '', ['Location' => $to]);
    };
};

\Joomla\CMS\Http\TestHttp::$handler = $redirect('https://lh3.googleusercontent.com/hop.png');
resetRequests();
$followed = $images->syncReviewPhotos(77, ['reviews' => [1000 => $review(1000, 'Redirected', $source . 'r1=s1')]]);

check('a redirect within the allowed hosts is followed', str_ends_with($followed['reviews'][1000]['profile_photo_local'], '.png'));
check('the transport was told not to chase it itself', count(requests()) === 2);
check('and the hop was requested by us', requests()[1]['url'] === 'https://lh3.googleusercontent.com/hop.png');

\Joomla\CMS\Http\TestHttp::$handler = $redirect('https://evil.test/hop.png');
resetRequests();
$offsite = $images->syncReviewPhotos(78, ['reviews' => [1010 => $review(1010, 'Sent Away', $source . 'r2=s1')]]);

check('a redirect off the allowed hosts is refused', str_starts_with($offsite['reviews'][1010]['profile_photo_local'], 'initials-'));
check('and the offsite hop is never requested', array_filter(
    requests(),
    static fn ($one) => str_contains($one['url'], 'evil.test')
) === []);

// Relative forms resolve against the url they came from; the result is checked like
// any other hop.
\Joomla\CMS\Http\TestHttp::$handler = $redirect('/hop.png');
resetRequests();
$relative = $images->syncReviewPhotos(79, ['reviews' => [1020 => $review(1020, 'Root Relative', $source . 'r3=s1')]]);

check('a root-relative redirect is resolved against its host', requests()[1]['url'] === 'https://lh3.googleusercontent.com/hop.png');
check('and it is followed', str_ends_with($relative['reviews'][1020]['profile_photo_local'], '.png'));

\Joomla\CMS\Http\TestHttp::$handler = static fn ($url) => new \Joomla\CMS\Http\TestResponse(302, '', ['Location' => $source . 'loop=s1']);
resetRequests();
$looping = $images->syncReviewPhotos(80, ['reviews' => [1030 => $review(1030, 'Round And Round', $source . 'r4=s1')]]);

check('a redirect loop is given up on', str_starts_with($looping['reviews'][1030]['profile_photo_local'], 'initials-'));
check('after a bounded number of hops', count(requests()) === 8);

respondWith(200, pngBytes());

group('Backing off a photo that keeps failing');

$failing = JPATH_ROOT . '/media/mod_prettyreviews/images/81';
$dead    = ['reviews' => [1100 => $review(1100, 'Never Works', $source . 'x9=s1')]];

respondWith(404, '');
resetRequests();
$images->syncReviewPhotos(81, $dead);
check('the first failure is recorded', count(glob($failing . '/failed-*.txt')) === 1);
check('and it took the requests to find out', count(requests()) === 2);

// One failure is never enough to give up: a refresh that could not reach Google at all
// has to heal the moment someone presses the button again.
resetRequests();
$images->syncReviewPhotos(81, $dead);
check('the second run tries again anyway', count(requests()) === 2);

resetRequests();
$images->syncReviewPhotos(81, $dead);
check('the third run leaves it alone', requests() === []);
check('the reviewer still has an avatar', is_file($failing . '/' . $images->syncReviewPhotos(81, $dead)['reviews'][1100]['profile_photo_local']));

// The marker is not a photo, so it must never be handed to a visitor.
$marker = basename(glob($failing . '/failed-*.txt')[0]);
check('a marker is never served', $images->publicPhotoUrl(81, $marker) === '');

// Once the window is behind it, the photo is worth another go.
touch(glob($failing . '/failed-*.txt')[0], time() - 7200);
respondWith(200, pngBytes());
resetRequests();
$recovered = $images->syncReviewPhotos(81, $dead);

check('once the window passes it is tried again', count(requests()) === 1);
check('and the photo replaces the initials avatar', str_ends_with($recovered['reviews'][1100]['profile_photo_local'], '.png'));
check('the marker is cleaned up with it', glob($failing . '/failed-*.txt') === []);

group('Pruning');
$keeper = $partial['reviews'][800]['profile_photo_local'];
$images->syncReviewPhotos(7, ['reviews' => [800 => $partial['reviews'][800]]]);
check('a full run keeps only what is still referenced', array_values(array_diff(
    Joomla\Filesystem\Folder::files($dir),
    ['index.html']
)) === [$keeper]);

file_put_contents($dir . '/.htaccess', 'x');
file_put_contents($dir . '/index.html', '');
file_put_contents($dir . '/notes.txt', 'something an administrator put here');
$images->syncReviewPhotos(7, ['reviews' => [800 => $partial['reviews'][800]]]);

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
        0
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
$empty = $images->syncReviewPhotos(7, ['reviews' => [1 => $review(1, 'No Photo', '')]]);
check('a review with no photo gets no local file', !isset($empty['reviews'][1]['profile_photo_local']));

$untouched = ['reviews' => [1 => ['profile_photo_url' => $source]]];
check('module 0 is left alone', $images->syncReviewPhotos(0, $untouched) === $untouched);
check('an empty payload is left alone', $images->syncReviewPhotos(7, []) === []);

$odd = $images->syncReviewPhotos(7, ['reviews' => [1 => 'not a review']]);
check('an entry that is not a review is skipped', $odd['reviews'][1] === 'not a review');

finish();
