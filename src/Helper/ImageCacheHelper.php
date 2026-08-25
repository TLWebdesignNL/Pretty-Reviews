<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Helper;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

\defined('_JEXEC') or die;

/**
 * Stores Google reviewer profile photos on this site so they are always served
 * from its own domain, never fetched from Google by the visitor's browser.
 *
 * Photos live in media/mod_prettyreviews/images/{moduleId}/ and are named after a
 * hash of their source URL, so the same avatar is stored once and a rotated Google
 * URL automatically yields a new file. When a photo cannot be downloaded, a locally
 * generated initials avatar is written instead, so there is always something local
 * to render.
 *
 * @since  2.2.0
 */
class ImageCacheHelper
{
    /**
     * Square size requested from Google, in pixels. Twice the largest size any
     * layout renders (128px in the legacy layout), so avatars stay sharp on
     * high-density displays.
     *
     * @since  2.2.0
     */
    private const PHOTO_SIZE = 256;

    /**
     * Largest accepted download, in bytes.
     *
     * @since  2.2.0
     */
    private const MAX_BYTES = 2097152;

    /**
     * Largest accepted image edge, in pixels.
     *
     * @since  2.2.0
     */
    private const MAX_DIMENSION = 4096;

    /**
     * Wall-clock seconds a refresh may spend downloading, bounding the administrator's
     * AJAX call. A fast connection fills a large cache in one run; a slow one leaves the
     * remainder for the next, and the caller reports how many.
     *
     * @since  2.2.0
     */
    private const DOWNLOAD_SECONDS = 15;

    /**
     * Longest HTTP timeout in seconds a photo may be given. A download close to the
     * deadline gets less, so the budget above is a ceiling on the whole run rather than
     * on the moment the last download starts.
     *
     * @since  2.2.0
     */
    private const HTTP_TIMEOUT = 10;

    /**
     * Shortest HTTP timeout in seconds a photo may be given. Joomla hands the value
     * straight to the transport, where zero means "no timeout at all", so the last
     * sliver of the budget still has to round up to a real limit.
     *
     * @since  2.2.0
     */
    private const MIN_TIMEOUT = 1;

    /**
     * Image directory relative to the site root.
     *
     * @since  2.2.0
     */
    private const MEDIA_PATH = 'media/mod_prettyreviews/images';

    /**
     * Hosts Google serves profile photos from. Anything else is refused.
     *
     * @since  2.2.0
     */
    private const ALLOWED_HOSTS = ['googleusercontent.com', 'ggpht.com', 'gstatic.com'];

    /**
     * Image types accepted from Google, mapped to the extension they are stored with.
     * SVG is deliberately absent: it is never accepted from a remote source.
     *
     * @since  2.2.0
     */
    private const ALLOWED_TYPES = [
        \IMAGETYPE_JPEG => 'jpg',
        \IMAGETYPE_PNG  => 'png',
        \IMAGETYPE_GIF  => 'gif',
        \IMAGETYPE_WEBP => 'webp',
    ];

    /**
     * Every filename this class creates. Also used to validate names read back from
     * the cache file, and to make sure pruning only ever removes our own files.
     *
     * @since  2.2.0
     */
    private const FILENAME_PATTERN = '/^(?:[0-9a-f]{32}\.(?:jpg|png|gif|webp)|initials-[0-9a-f]{32}\.svg)$/';

    /**
     * How many photos the last sync left undownloaded because its time ran out.
     *
     * @var    integer
     * @since  2.2.0
     */
    private int $lastRunPending = 0;

    /**
     * Make sure every review has a locally stored photo, downloading what is missing.
     *
     * Runs only while the reviews are being refreshed, so $raw always holds every
     * cached review. Each review gains profile_photo_local, the bare filename to
     * serve; the original Google URL in profile_photo_url is left untouched — it is
     * the cache key and the source to re-fetch from.
     *
     * @param   int    $moduleId         Module record id.
     * @param   array  $raw              Raw cache payload.
     * @param   float  $downloadSeconds  Wall-clock seconds the run may spend
     *                                   downloading. Each download's timeout is trimmed
     *                                   to what is left, so the run overshoots by at
     *                                   most MIN_TIMEOUT. Zero disables downloads.
     * @param   int    $timeout          Longest HTTP timeout per download, in seconds.
     *
     * @return  array  The payload with the photo keys applied.
     *
     * @since   2.2.0
     */
    public function syncReviewPhotos(
        int $moduleId,
        array $raw,
        float $downloadSeconds = self::DOWNLOAD_SECONDS,
        int $timeout = self::HTTP_TIMEOUT
    ): array {
        if ($moduleId <= 0 || empty($raw['reviews']) || !\is_array($raw['reviews'])) {
            return $raw;
        }

        $dir      = $this->imageDir($moduleId);
        $deadline = $this->now() + $downloadSeconds;
        $keep     = [];

        $this->lastRunPending = 0;

        foreach ($raw['reviews'] as $key => $review) {
            if (!\is_array($review)) {
                continue;
            }

            $source = trim((string) ($review['profile_photo_url'] ?? ''));

            if ($source === '') {
                unset($raw['reviews'][$key]['profile_photo_local']);

                continue;
            }

            $hash = $this->sourceHash($source);
            $file = $this->existingFile($dir, $hash);

            if ($file === null) {
                if ($this->now() < $deadline) {
                    $file = $this->storePhoto($dir, $source, $hash, $deadline, $timeout);
                } else {
                    // Left for the next run purely because this one's time ran out —
                    // the caller reports this, so whoever pressed the button knows to
                    // press it again.
                    $this->lastRunPending++;
                }

                // Still nothing to show: fall back to an avatar built from the
                // reviewer's initials, so the photo is local either way and the page
                // never reaches out to Google.
                if ($file === null) {
                    $file = $this->writeInitials($dir, (string) ($review['author_name'] ?? ''));
                }
            }

            if ($file === null) {
                unset($raw['reviews'][$key]['profile_photo_local']);

                continue;
            }

            $raw['reviews'][$key]['profile_photo_local'] = $file;
            $keep[$file]                                 = true;
        }

        // Safe after a partial run too. The payload always holds every cached review and
        // the loop above visited all of them — running out of time skips a download, not
        // an iteration — so $keep names every file on disk that is still referenced. A
        // photo the run never reached is not on disk yet, so there is nothing to lose.
        $this->pruneOrphans($dir, $keep);

        return $raw;
    }

    /**
     * How many photos the last syncReviewPhotos() run had to leave undownloaded
     * because its time ran out.
     *
     * Photos whose download was attempted and failed are not counted: running again
     * straight away would not help those, and they are logged instead.
     *
     * @return  integer
     *
     * @since   2.2.0
     */
    public function pendingDownloads(): int
    {
        return $this->lastRunPending;
    }

    /**
     * Build the public URL for a stored photo.
     *
     * The filename is re-validated here rather than trusted, because it is read back
     * from a file on disk that an administrator (or a compromised account) can edit.
     *
     * @param   int     $moduleId  Module record id.
     * @param   string  $file      Filename as stored in profile_photo_local.
     *
     * @return  string  Absolute URL on this site, or an empty string when there is
     *                  nothing valid to serve.
     *
     * @since   2.2.0
     */
    public function publicPhotoUrl(int $moduleId, string $file): string
    {
        if (
            $moduleId <= 0
            || $file === ''
            || preg_match(self::FILENAME_PATTERN, $file) !== 1
            || !is_file($this->imageDir($moduleId) . '/' . $file)
        ) {
            return '';
        }

        return Uri::root() . self::MEDIA_PATH . '/' . $moduleId . '/' . $file;
    }

    /**
     * Remove every stored photo for a module.
     *
     * A missing directory counts as already purged.
     *
     * @param   int  $moduleId  Module record id.
     *
     * @return  bool
     *
     * @since   2.2.0
     */
    public function purgeModuleImages(int $moduleId): bool
    {
        if ($moduleId <= 0) {
            return false;
        }

        $dir = $this->imageDir($moduleId);

        if (!is_dir($dir)) {
            return true;
        }

        try {
            return Folder::delete($dir);
        } catch (\Throwable $e) {
            $this->log('Could not remove the image folder ' . $dir . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
     * The clock the download deadline is measured against.
     *
     * A method of its own so the tests can supply a clock they control; everything
     * else uses it through the deadline only.
     *
     * @return  float  Seconds, as microtime(true) returns them.
     *
     * @since   2.2.0
     */
    protected function now(): float
    {
        return microtime(true);
    }

    /**
     * Absolute path of a module's image directory.
     *
     * @param   int  $moduleId  Module record id.
     *
     * @return  string
     *
     * @since   2.2.0
     */
    private function imageDir(int $moduleId): string
    {
        return JPATH_ROOT . '/' . self::MEDIA_PATH . '/' . $moduleId;
    }

    /**
     * Reduce a Google photo URL to a canonical form requesting our own size.
     *
     * Google appends sizing options such as "=s128-c-rp-mo" to avatar URLs. Stripping
     * them and asking for one size means the same avatar delivered at two sizes maps to
     * a single stored file.
     *
     * @param   string  $url  Photo URL as supplied by Google.
     *
     * @return  string
     *
     * @since   2.2.0
     */
    private function normalizeSourceUrl(string $url): string
    {
        $base = explode('?', $url, 2)[0];
        $base = preg_replace('#=[A-Za-z0-9\-_]*$#', '', $base);

        return $base . '=s' . self::PHOTO_SIZE . '-c';
    }

    /**
     * Cache key for a photo URL.
     *
     * @param   string  $url  Photo URL as supplied by Google.
     *
     * @return  string  32 hexadecimal characters.
     *
     * @since   2.2.0
     */
    private function sourceHash(string $url): string
    {
        return substr(hash('sha256', $this->normalizeSourceUrl($url)), 0, 32);
    }

    /**
     * Find an already stored photo for a cache key.
     *
     * @param   string  $dir   Module image directory.
     * @param   string  $hash  Cache key.
     *
     * @return  string|null  Filename, or null when nothing is stored.
     *
     * @since   2.2.0
     */
    private function existingFile(string $dir, string $hash): ?string
    {
        foreach (self::ALLOWED_TYPES as $extension) {
            if (is_file($dir . '/' . $hash . '.' . $extension)) {
                return $hash . '.' . $extension;
            }
        }

        return null;
    }

    /**
     * Download a photo and store it.
     *
     * @param   string  $dir        Module image directory.
     * @param   string  $sourceUrl  Photo URL as supplied by Google.
     * @param   string  $hash       Cache key.
     * @param   float   $deadline   Point in time the run's download budget expires.
     * @param   int     $timeout    Longest HTTP timeout in seconds.
     *
     * @return  string|null  Filename, or null when the photo could not be stored.
     *
     * @since   2.2.0
     */
    private function storePhoto(string $dir, string $sourceUrl, string $hash, float $deadline, int $timeout): ?string
    {
        $normalized = $this->normalizeSourceUrl($sourceUrl);
        $image      = $this->fetchImage($normalized, $this->remainingTimeout($deadline, $timeout));

        // Retry with the original URL in case Google ever changes its sizing syntax —
        // but only when normalising actually changed it, and only while the budget still
        // leaves room for a second request.
        if ($image === null && $normalized !== $sourceUrl && $this->now() < $deadline) {
            $image = $this->fetchImage($sourceUrl, $this->remainingTimeout($deadline, $timeout));
        }

        if ($image === null) {
            return null;
        }

        $name = $hash . '.' . $image['extension'];

        // File::write() takes its buffer by reference, so it needs a variable.
        $bytes = $image['bytes'];

        if (!$this->createDirectory($dir) || !File::write($dir . '/' . $name, $bytes)) {
            $this->log('Could not store the profile photo ' . $name . ' in ' . $dir . '.');

            return null;
        }

        return $name;
    }

    /**
     * How long a request started now may run without pushing the run past its deadline.
     *
     * Never returns zero, which the HTTP transport would read as "no timeout"; a request
     * started with nothing left still ends after MIN_TIMEOUT.
     *
     * @param   float  $deadline  Point in time the run's download budget expires.
     * @param   int    $timeout   Longest HTTP timeout in seconds.
     *
     * @return  integer
     *
     * @since   2.2.0
     */
    private function remainingTimeout(float $deadline, int $timeout): int
    {
        return max(self::MIN_TIMEOUT, min($timeout, (int) ceil($deadline - $this->now())));
    }

    /**
     * Download an image and verify it really is one.
     *
     * Never throws: a single unusable avatar must not abort a refresh or a page render.
     *
     * @param   string  $url      Absolute image URL.
     * @param   int     $timeout  HTTP timeout in seconds.
     *
     * @return  array|null  ['extension' => string, 'bytes' => string], or null.
     *
     * @since   2.2.0
     */
    private function fetchImage(string $url, int $timeout): ?array
    {
        if (!$this->isAllowedSource($url)) {
            $this->log('Refused a profile photo from an unexpected source: ' . $url);

            return null;
        }

        try {
            $response = HttpFactory::getHttp()->get($url, [], $timeout);
        } catch (\Throwable $e) {
            $this->log('Could not download the profile photo ' . $url . ': ' . $e->getMessage());

            return null;
        }

        if ((int) $response->code !== 200) {
            $this->log('Downloading the profile photo ' . $url . ' returned HTTP status ' . (int) $response->code . '.');

            return null;
        }

        $bytes = (string) $response->body;

        // Joomla's HTTP transports buffer the whole body, so the size is checked here
        // rather than trusting a Content-Length header.
        if ($bytes === '' || \strlen($bytes) > self::MAX_BYTES) {
            $this->log('Rejected the profile photo ' . $url . ': empty or larger than ' . self::MAX_BYTES . ' bytes.');

            return null;
        }

        // The bytes themselves decide the type; the URL and the response headers do not
        // get a say, so a mislabelled or hostile payload cannot pick its own extension.
        $info = @getimagesizefromstring($bytes);

        if (
            $info === false
            || !isset(self::ALLOWED_TYPES[$info[2]])
            || (int) $info[0] < 1 || (int) $info[0] > self::MAX_DIMENSION
            || (int) $info[1] < 1 || (int) $info[1] > self::MAX_DIMENSION
        ) {
            $this->log('Rejected the profile photo ' . $url . ': not an accepted image.');

            return null;
        }

        return ['extension' => self::ALLOWED_TYPES[$info[2]], 'bytes' => $bytes];
    }

    /**
     * Check that a URL points at a Google photo host over https.
     *
     * Photo URLs come from Google but reach this method by way of a writable file on
     * disk, so they are treated as untrusted input.
     *
     * @param   string  $url  URL to check.
     *
     * @return  bool
     *
     * @since   2.2.0
     */
    private function isAllowedSource(string $url): bool
    {
        $parts = parse_url($url);

        if (
            $parts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])
            || empty($parts['host'])
        ) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach (self::ALLOWED_HOSTS as $allowed) {
            $suffix = '.' . $allowed;

            if ($host === $allowed || substr($host, -\strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write an initials avatar for a reviewer, reusing one that already exists.
     *
     * At most two letters or digits are taken from the name, so the generated markup
     * can never contain anything but those characters.
     *
     * @param   string  $dir         Module image directory.
     * @param   string  $authorName  Reviewer name.
     *
     * @return  string|null  Filename, or null when it could not be written.
     *
     * @since   2.2.0
     */
    private function writeInitials(string $dir, string $authorName): ?string
    {
        $authorName = trim($authorName);
        $name       = $dir . '/initials-' . substr(hash('sha256', $authorName), 0, 32) . '.svg';

        if (is_file($name)) {
            return basename($name);
        }

        $initials = '';

        foreach (preg_split('/\s+/u', $authorName, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (preg_match('/[\p{L}\p{N}]/u', $word, $match) === 1) {
                $initials .= $match[0];
            }

            if (mb_strlen($initials) === 2) {
                break;
            }
        }

        $initials = mb_strtoupper($initials);
        $hue      = crc32($authorName) % 360;
        $svg      = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::PHOTO_SIZE . ' ' . self::PHOTO_SIZE . '" role="img">'
            . '<rect width="' . self::PHOTO_SIZE . '" height="' . self::PHOTO_SIZE . '" fill="hsl(' . $hue . ',42%,62%)"/>'
            . ($initials === '' ? '' : '<text x="50%" y="50%" dy="0.35em" text-anchor="middle"'
                . ' font-family="Helvetica,Arial,sans-serif" font-size="' . (int) (self::PHOTO_SIZE * 0.42) . '"'
                . ' fill="#ffffff">' . $initials . '</text>')
            . '</svg>';

        if (!$this->createDirectory($dir) || !File::write($name, $svg)) {
            $this->log('Could not write the initials avatar ' . $name . '.');

            return null;
        }

        return basename($name);
    }

    /**
     * Delete stored files no review references any more.
     *
     * Only files matching the names this class creates are ever removed, so anything
     * else in the folder is left alone.
     *
     * @param   string  $dir   Module image directory.
     * @param   array   $keep  Filenames to keep, as array keys.
     *
     * @return  void
     *
     * @since   2.2.0
     */
    private function pruneOrphans(string $dir, array $keep): void
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $files = Folder::files($dir, '.', false, false) ?: [];
        } catch (\Throwable $e) {
            return;
        }

        foreach ($files as $file) {
            if (isset($keep[$file]) || preg_match(self::FILENAME_PATTERN, $file) !== 1) {
                continue;
            }

            File::delete($dir . '/' . $file);
        }
    }

    /**
     * Create a module image directory when it does not exist yet.
     *
     * The folders under media/ that ship with the module carry an index.html, but these
     * are made while the site runs, so one is written here too — a server with directory
     * listing left on then has nothing to show.
     *
     * @param   string  $dir  Module image directory.
     *
     * @return  bool
     *
     * @since   2.2.0
     */
    private function createDirectory(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }

        try {
            if (!Folder::create($dir)) {
                return false;
            }
        } catch (\Throwable $e) {
            $this->log('Could not create the image folder ' . $dir . ': ' . $e->getMessage());

            return false;
        }

        // Not being able to write it is no reason to refuse the folder: the photos are
        // what matter, and a failure here reappears when the first one is written.
        $blank = '';
        File::write($dir . '/index.html', $blank);

        return true;
    }

    /**
     * Record a problem without interrupting the caller.
     *
     * @param   string  $message  Message to log.
     *
     * @return  void
     *
     * @since   2.2.0
     */
    private function log(string $message): void
    {
        try {
            Log::add($message, Log::WARNING, 'mod_prettyreviews');
        } catch (\Throwable $e) {
            // Logging must never be the reason a page fails to render.
        }
    }
}
