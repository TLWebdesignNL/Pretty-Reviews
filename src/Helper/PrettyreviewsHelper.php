<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TLWeb\Module\Prettyreviews\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Table\Module;

\defined('_JEXEC') or die;

/**
 * Helper for mod_prettyreviews
 *
 * @since  V1.0.0
 */
class PrettyreviewsHelper
{
    /**
     * Retrieve Google Reviews and update JSON file
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function updateGoogleReviewsAjax(): bool
    {
	    if (!Session::checkToken('get')) {
		    return json_encode(['success' => false, 'message' => 'Invalid Token']);
	    }
        $input = Factory::getApplication()->input;

        // Get the Google reviews
        $moduleId      = $input->getString('moduleId');
        $cid           = $input->getString('cid');
        $apiKey        = $input->getString('apiKey');
        $reviewSort    = $input->getString('reviewSort');
        $googleReviews = $this->getGoogleReviews($cid, $apiKey, $reviewSort);

        $googleReviewsArray = json_decode(json_encode($googleReviews), true);
        $googleReviewsArray['apiUrl'] = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . $cid . "&language=nl&fields=url,rating,reviews,user_ratings_total&reviews_sort=" . $reviewSort . "&key=".$apiKey;

        // for debugging save googlereviews raw data
        $this->saveJsonFile($googleReviewsArray,JPATH_ROOT . '/media/mod_prettyreviews/rawdata.json');

        // Get existing reviews from JSON file
        $data = $this->getJsonFile(JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json');

        // Update data with new reviews
        $data = $this->updateRatingAndReviews($googleReviews, $data);

        // Save and return outcome (bool)
        return $this->saveJsonFile($data, JPATH_ROOT . '/media/mod_prettyreviews/data-' . $moduleId . '.json');
    }

    /**
     * Fetch Google Reviews using cURL
     *
     * @param string $cid
     * @param string $apiKey
     * @return object|null
     *
     * @since 1.0.0
     */
    public function getGoogleReviews(string $cid, string $apiKey, string $reviewSort = "most-relevant"): ?object
    {
        // URL FOR THE MORE EXPENSIVE NEW PLACES API
        //$url = "https://places.googleapis.com/v1/places/" . $cid . "?languageCode=nl&fields=rating,reviews,userRatingCount&key=".$apiKey;
        // URL FOR THE OLD PLACES API (CHEAPER)
        $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id=" . $cid . "&language=nl&fields=url,rating,reviews,user_ratings_total&reviews_sort=" . $reviewSort . "&key=".$apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return null;
        }

        curl_close($ch);
        return json_decode($result);
    }

    /**
     * Update ratings and reviews data
     *
     * @param object $googleReviews
     * @param array|null $data
     * @return array
     *
     * @since 1.0.0
     */
    public function updateRatingAndReviews($googleReviews, ?array $data): array
    {
        // Ensure data is an array
        $data = $data ?? [];

        // Update rating
        if (isset($googleReviews->result->rating)) {
            $data['rating'] = $googleReviews->result->rating;
        }
        if (isset($googleReviews->result->user_ratings_total)) {
            $data['ratingsCount'] = $googleReviews->result->user_ratings_total;
        }
        if (isset($googleReviews->result->url)) {
            $data['url'] = $googleReviews->result->url;
        }

        // Update reviews array
        if (isset($googleReviews->result->reviews)) {
            foreach ($googleReviews->result->reviews as $review) {
                if (!isset($data['reviews']) || !array_key_exists($review->time, $data['reviews'])) {
                    if ($review->rating >= 4) {
                        $data['reviews'][$review->time] = $review;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Get JSON file contents
     *
     * @return array|null
     *
     * @since 1.0.0
     */
    public function getJsonFile($jsonFilePath = JPATH_ROOT . '/media/mod_prettyreviews/data.json'): ?array
    {
        if (File::exists($jsonFilePath)) {
            $jsonContents = file_get_contents($jsonFilePath);
            return json_decode($jsonContents, true);
        }

        return null;
    }

    /**
     * Save data to JSON file
     *
     * @param array $data
     * @return bool
     *
     * @since 1.0.0
     */
    public function saveJsonFile(array $data, $jsonFilePath = JPATH_ROOT . '/media/mod_prettyreviews/data.json'): bool
    {

        $jsonContents = json_encode($data);

        // Save the JSON data to the file
        return File::write($jsonFilePath, $jsonContents);
    }

    /**
     * Convert a timestamp to a human-readable "time ago" format.
     *
     * @param int $timestamp The timestamp to convert.
     * @return string Human-readable time difference.
     */
    public static function timeAgo($timestamp): string
    {
        $time = time() - $timestamp; // Calculate the time difference in seconds

        $units = [
            31536000 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_YEAR', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_YEARS'],
            2592000 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_MONTH', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_MONTHS'],
            604800 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_WEEK', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_WEEKS'],
            86400 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_DAY', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_DAYS'],
            3600 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_HOUR', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_HOURS'],
            60 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_MINUTE', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_MINUTES'],
            1 => ['singular' => 'MOD_PRETTYREVIEWS_TIMEAGO_SECOND', 'plural' => 'MOD_PRETTYREVIEWS_TIMEAGO_SECONDS']
        ];

        foreach ($units as $unitSeconds => $unitNames) {
            if ($time < $unitSeconds) {
                continue;
            }

            $numberOfUnits = floor($time / $unitSeconds);
            $unitName = $numberOfUnits > 1 ? Text::_($unitNames['plural']) : Text::_($unitNames['singular']);
            return $numberOfUnits . ' ' . $unitName . ' ' . Text::_('MOD_PRETTYREVIEWS_TIMEAGO_AGO');
        }

        return Text::_('MOD_PRETTYREVIEWS_TIMEAGO_JUST_NOW');
    }
}
