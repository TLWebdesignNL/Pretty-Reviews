<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers hiding reviews by reviewer name at display time.
 */

require_once __DIR__ . '/bootstrap.php';

use TLWeb\Module\Prettyreviews\Site\Helper\PrettyreviewsHelper;

$helper = new PrettyreviewsHelper();

$raw = ['rating' => 4.5, 'reviews' => [
    100 => ['time' => 100, 'author_name' => 'Anna Müller', 'rating' => 5, 'text' => 'Super'],
    200 => ['time' => 200, 'author_name' => 'Anna', 'rating' => 5, 'text' => 'Toll'],
    300 => ['time' => 300, 'author_name' => 'Ben  Schmidt', 'rating' => 4, 'text' => 'Gut'],
    400 => ['time' => 400, 'author_name' => 'Clara Weiß', 'rating' => 5, 'text' => 'Prima'],
]];

$shown = fn (string $excludeNames): array => array_keys($helper->present($raw, ['minRating' => 1, 'excludeNames' => $excludeNames])['reviews']);

group('Hiding reviewers by name');
check('an empty list hides nothing', $shown('') === [400, 300, 200, 100]);
check('a listed name is hidden', $shown('Clara Weiß') === [300, 200, 100]);
check('upper and lower case do not matter, umlauts included', $shown('ANNA MÜLLER') === [400, 300, 200]);
check('only the whole name matches', $shown('Anna') === [400, 300, 100]);
check('extra spaces do not matter', $shown("  ben schmidt ") === [400, 200, 100]);
check('one name per line, blank lines ignored', $shown("Anna\r\n\r\nClara Weiß\n") === [300, 100]);
check('the cached payload is left alone', count($raw['reviews']) === 4);

group('Together with the other filters');
$combined = $helper->present($raw, ['minRating' => 5, 'excludeNames' => 'Anna', 'limit' => 1]);
check('name, rating and limit filters all apply', array_keys($combined['reviews']) === [400]);

finish();
