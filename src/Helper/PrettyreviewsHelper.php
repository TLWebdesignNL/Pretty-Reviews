<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Helper;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;

\defined('_JEXEC') or die;

/**
 * Helper for mod_prettyreviews
 *
 * @since  1.0.0
 */
class PrettyreviewsHelper
{
    /**
     * AJAX entry point — guarded by CSRF + per-module ACL, then refreshes from Google.
     *
     * @return  bool
     *
     * @since   1.2.0
     */
    public function updateGoogleReviewsAjax(): bool
    {
        if (!Session::checkToken('post')) {
            throw new NotAllowed(Text::_('JINVALID_TOKEN'), 403);
        }

        $app      = Factory::getApplication();
        $moduleId = $app->getInput()->getInt('moduleId');

        if ($moduleId <= 0) {
            throw new \InvalidArgumentException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 400);
        }

        $user = $app->getIdentity();

        if ($user === null || !$user->authorise('core.edit', 'com_modules.module.' . $moduleId)) {
            throw new NotAllowed(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }

        return $this->refreshFromGoogle($moduleId);
    }

    /**
     * Read raw cached reviews for a module without applying display options.
     *
     * @param   int  $moduleId  Module record id.
     *
     * @return  array
     *
     * @since   1.2.0
     */
    public function loadRaw(int $moduleId): array
    {
        return $this->readJson($this->cachePath($moduleId));
    }

    /**
     * Apply display options (hideEmpty / sort / limit) to a raw review payload.
     *
     * @param   array  $raw   Raw payload as returned by loadRaw().
     * @param   array  $opts  Keys: hideEmpty (int), sort (string), limit (int|null).
     *
     * @return  array
     *
     * @since   1.2.0
     */
    public function present(array $raw, array $opts): array
    {
        if (!isset($raw['reviews']) || !is_array($raw['reviews'])) {
            return $raw;
        }

        $hideEmpty = (int) ($opts['hideEmpty'] ?? 0);
        $sort      = (string) ($opts['sort'] ?? 'newest');
        $limit     = $opts['limit'] ?? null;

        $reviews = $raw['reviews'];

        if ($hideEmpty === 1) {
            $reviews = array_filter($reviews, static function ($r) {
                $txt = is_array($r) && isset($r['text']) ? trim((string) $r['text']) : '';
                return $txt !== '';
            });
        }

        if ($sort === 'random') {
            $keys = array_keys($reviews);
            shuffle($keys);
            $shuffled = [];
            foreach ($keys as $k) {
                $shuffled[$k] = $reviews[$k];
            }
            $reviews = $shuffled;
        } else {
            krsort($reviews, SORT_NUMERIC);
        }

        if ($limit !== null) {
            $limitInt = (int) $limit;
            if ($limitInt > 0) {
                $reviews = array_slice($reviews, 0, $limitInt, true);
            }
        }

        $raw['reviews'] = $reviews;

        return $raw;
    }

    /**
     * Pull reviews from Google for a module, merge with cache, save raw payload.
     *
     * Credentials (cid, apikey, reviewsort) are read server-side from the module
     * record — never accepted from the client.
     *
     * @param   int  $moduleId  Module record id.
     *
     * @return  bool
     *
     * @since   1.2.0
     */
    public function refreshFromGoogle(int $moduleId): bool
    {
        $module = $this->loadModule($moduleId);

        if ($module === null) {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_MODULE_NOT_FOUND'), 404);
        }

        $params     = json_decode((string) $module->params, true) ?? [];
        $cid        = (string) ($params['cid'] ?? '');
        $apiKey     = (string) ($params['apikey'] ?? '');
        $reviewSort = (string) ($params['reviewsort'] ?? 'most_relevant');

        if ($cid === '' || $apiKey === '') {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_MISSING_CREDENTIALS'), 400);
        }

        $googleReviews = $this->fetchFromGoogle($cid, $apiKey, $reviewSort);

        $cachePath = $this->cachePath($moduleId);
        $raw       = $this->readJson($cachePath);
        $merged    = $this->mergeReviews($googleReviews, $raw);

        return $this->writeJson($cachePath, $merged);
    }

    /**
     * Resolve a module record by id.
     *
     * @param   int  $moduleId  Module record id.
     *
     * @return  object|null
     *
     * @since   1.2.0
     */
    private function loadModule(int $moduleId): ?object
    {
        $module = ModuleHelper::getModuleById((string) $moduleId);

        if ($module && !empty($module->id)) {
            return $module;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'module', 'params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $moduleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * Call the Google Places API via Joomla's HTTP client.
     *
     * @param   string  $cid         Google place id.
     * @param   string  $apiKey      Google API key.
     * @param   string  $reviewSort  Google reviews_sort value.
     *
     * @return  object
     *
     * @since   1.2.0
     */
    private function fetchFromGoogle(string $cid, string $apiKey, string $reviewSort): object
    {
        $url = 'https://maps.googleapis.com/maps/api/place/details/json'
            . '?place_id=' . urlencode($cid)
            . '&language=nl'
            . '&fields=url,rating,reviews,user_ratings_total'
            . '&reviews_sort=' . urlencode($reviewSort)
            . '&key=' . urlencode($apiKey);

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->get($url, [], 60);
        } catch (\Throwable $e) {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_GOOGLE_REQUEST_FAILED'), 502, $e);
        }

        if ((int) $response->code !== 200) {
            throw new \RuntimeException(
                Text::sprintf('MOD_PRETTYREVIEWS_ERROR_GOOGLE_HTTP_STATUS', (int) $response->code),
                502
            );
        }

        $decoded = json_decode((string) $response->body);

        if (!$decoded instanceof \stdClass) {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_GOOGLE_INVALID_RESPONSE'), 502);
        }

        $status = (string) ($decoded->status ?? '');

        if ($status !== '' && $status !== 'OK') {
            $message = trim((string) ($decoded->error_message ?? ''));

            throw new \RuntimeException(
                Text::sprintf(
                    'MOD_PRETTYREVIEWS_ERROR_GOOGLE_STATUS',
                    $status,
                    $message !== '' ? $message : $status
                ),
                502
            );
        }

        if (!isset($decoded->result) || !is_object($decoded->result)) {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_GOOGLE_EMPTY_RESULT'), 502);
        }

        if (
            !isset($decoded->result->rating)
            && !isset($decoded->result->user_ratings_total)
            && !isset($decoded->result->url)
            && !isset($decoded->result->reviews)
        ) {
            throw new \RuntimeException(Text::_('MOD_PRETTYREVIEWS_ERROR_GOOGLE_EMPTY_RESULT'), 502);
        }

        return $decoded;
    }

    /**
     * Merge a fresh Google response into the existing raw cache payload.
     *
     * Only adds reviews not already cached (keyed by their unix timestamp) and
     * with a rating of 4 or higher. Top-level rating / count / url are overwritten.
     *
     * @param   object  $googleReviews  Decoded API response.
     * @param   array   $raw            Existing raw cache (may be empty).
     *
     * @return  array
     *
     * @since   1.2.0
     */
    private function mergeReviews(object $googleReviews, array $raw): array
    {
        if (isset($googleReviews->result->rating)) {
            $raw['rating'] = $googleReviews->result->rating;
        }

        if (isset($googleReviews->result->user_ratings_total)) {
            $raw['ratingsCount'] = $googleReviews->result->user_ratings_total;
        }

        if (isset($googleReviews->result->url)) {
            $raw['url'] = $googleReviews->result->url;
        }

        if (isset($googleReviews->result->reviews)) {
            foreach ($googleReviews->result->reviews as $review) {
                if (!isset($raw['reviews']) || !array_key_exists($review->time, $raw['reviews'])) {
                    if ($review->rating >= 4) {
                        $raw['reviews'][$review->time] = $review;
                    }
                }
            }
        }

        return $raw;
    }

    /**
     * Build the cache file path for a module.
     */
    private function cachePath(int $moduleId): string
    {
        return JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json';
    }

    /**
     * Read a JSON file as an array. Returns [] when missing or unreadable.
     */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Write a JSON file from an array.
     */
    private function writeJson(string $path, array $data): bool
    {
        $encoded = json_encode($data);

        if ($encoded === false) {
            return false;
        }

        return File::write($path, $encoded);
    }

    /**
     * Convert a timestamp to a human-readable "time ago" format.
     *
     * @param   int  $timestamp  The timestamp to convert.
     *
     * @return  string
     */
    public function timeAgo(int $timestamp): string
    {
        $time = time() - $timestamp;

        $units = [
            31536000 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_YEAR', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_YEARS'],
            2592000  => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_MONTH', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_MONTHS'],
            604800   => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_WEEK', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_WEEKS'],
            86400    => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_DAY', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_DAYS'],
            3600     => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_HOUR', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_HOURS'],
            60       => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_MINUTE', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_MINUTES'],
            1        => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_SECOND', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_SECONDS'],
        ];

        foreach ($units as $unitSeconds => $unitNames) {
            if ($time < $unitSeconds) {
                continue;
            }

            $numberOfUnits = floor($time / $unitSeconds);
            $unitName      = $numberOfUnits > 1 ? Text::_($unitNames['plural']) : Text::_($unitNames['singular']);

            return $numberOfUnits . ' ' . $unitName . ' ' . Text::_('MOD_PRETTYREVIEWS_TIMEAGO_AGO');
        }

        return Text::_('MOD_PRETTYREVIEWS_TIMEAGO_JUST_NOW');
    }
}
