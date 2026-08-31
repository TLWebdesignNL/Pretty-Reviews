<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Covers the notice the update shows when photos need collecting.
 *
 * It is gated three ways, and a gate that never opens looks exactly like one that
 * always does until someone checks: nothing appears either way on the site being
 * upgraded.
 */

require_once __DIR__ . '/bootstrap.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;

require_once \dirname(__DIR__) . '/script.php';

$key = 'MOD_PRETTYREVIEWS_INSTALLERSCRIPT_REFRESH_PHOTOS';

// The version being upgraded from is read from the extensions table in preflight;
// the tests set it directly so nothing has to reach a database.
$script = new class () extends mod_prettyreviewsInstallerScript {
    public function from(?string $version): self
    {
        $this->fromVersion = $version;

        return $this;
    }
};

$cacheDir = JPATH_ROOT . '/media/mod_prettyreviews';

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$withCache = static function (bool $present) use ($cacheDir): void {
    foreach (glob($cacheDir . '/data-*.json') ?: [] as $file) {
        unlink($file);
    }

    if ($present) {
        file_put_contents($cacheDir . '/data-7.json', '{"reviews":{}}');
    }
};

/**
 * Run postflight and report the notices it raised: the messages it enqueued, and
 * whether it also printed the notice into the installer's own output.
 */
$run = static function (string $type, ?string $from) use ($script, $key): array {
    Factory::$application = new Joomla\CMS\TestInstallerApplication();

    ob_start();
    $script->from($from)->postflight($type, new InstallerAdapter());
    $echoed = (string) ob_get_clean();

    $notices = array_values(array_filter(
        Factory::$application->messages,
        static fn (array $m): bool => str_contains($m['message'], $key)
    ));

    return [
        'notices'   => $notices,
        'shown'     => $notices !== [],
        'alsoEchoed' => str_contains($echoed, $key),
    ];
};

group('Updating from a release without stored photos');
$withCache(true);
$result = $run('update', '2.1.0');

check('the notice is raised', $result['shown']);
check('as a warning', ($result['notices'][0]['type'] ?? '') === 'warning');

// The notice was once both enqueued and printed, which showed it twice on the update
// screen: once as the alert, once beside "the module has been updated".
check('exactly once', count($result['notices']) === 1);
check('and not printed as well', !$result['alsoEchoed']);

// 2.0.0 and up only: below that, postflight also runs the column migration, which is
// a database concern of its own and not what this suite is about.
check('an older release is told too', $run('update', '2.0.0')['shown']);
check('and so is the release just below', $run('update', '2.1.9')['shown']);

group('Not shown when there is nothing to act on');

check('a site already on this version is left alone', !$run('update', '2.2.0')['shown']);
check('nor is a later one told', !$run('update', '2.3.0')['shown']);
check('a first install is not an update', !$run('install', null)['shown']);
check('an unreadable version says nothing', !$run('update', null)['shown']);

$withCache(false);
check('a site with no cached reviews has nothing to refresh', !$run('update', '2.1.0')['shown']);

group('Every language ships the string');

foreach (['en-GB', 'nl-NL', 'de-DE', 'fr-FR', 'it-IT'] as $lang) {
    $file    = \dirname(__DIR__) . "/language/$lang/$lang.mod_prettyreviews.sys.ini";
    $strings = @parse_ini_file($file, false, INI_SCANNER_RAW) ?: [];

    check("$lang defines it", trim((string) ($strings[$key] ?? '')) !== '');
    check("$lang needs no escaping", !str_contains((string) ($strings[$key] ?? ''), '\\'));
}
