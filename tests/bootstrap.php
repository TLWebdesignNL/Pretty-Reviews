<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Test bootstrap.
 *
 * The module is a Joomla extension with no Composer dependencies, so rather than
 * pulling in a test framework these tests stand up just enough of the Joomla classes
 * the helpers touch: the filesystem wrappers, the HTTP client, the URI root and the
 * logger. Each test file runs in its own process with its own temporary site root, so
 * the fixtures of one cannot reach another.
 *
 * Run them all with: php tests/run.php
 */

namespace {
    \define('_JEXEC', 1);
    \define('JPATH_ROOT', getenv('TEST_ROOT') ?: sys_get_temp_dir() . '/prettyreviews-test');

    if (!is_dir(JPATH_ROOT)) {
        mkdir(JPATH_ROOT, 0777, true);
    }

    /**
     * Load the module classes under test.
     */
    require_once __DIR__ . '/../src/Helper/ImageCacheHelper.php';
    require_once __DIR__ . '/../src/Helper/PrettyreviewsHelper.php';

    /**
     * The module targets Joomla 4, so its source stays clear of anything newer than
     * PHP 7.4. These tests are written with the PHP 8 string helpers for readability;
     * defining them where they are missing keeps the suite runnable on 7.4 without
     * making the module itself look like it depends on them.
     */
    if (!function_exists('str_starts_with')) {
        function str_starts_with(string $haystack, string $needle): bool
        {
            return $needle === '' || strncmp($haystack, $needle, \strlen($needle)) === 0;
        }
    }

    if (!function_exists('str_ends_with')) {
        function str_ends_with(string $haystack, string $needle): bool
        {
            return $needle === '' || substr($haystack, -\strlen($needle)) === $needle;
        }
    }

    if (!function_exists('str_contains')) {
        function str_contains(string $haystack, string $needle): bool
        {
            return $needle === '' || strpos($haystack, $needle) !== false;
        }
    }

    /**
     * Assertion counters, reported by run.php.
     */
    $GLOBALS['tests_passed'] = 0;
    $GLOBALS['tests_failed'] = 0;

    /**
     * Record one assertion.
     *
     * @param   string  $label  What is being asserted.
     * @param   bool    $ok     Whether it holds.
     *
     * @return  void
     */
    function check(string $label, bool $ok): void
    {
        if ($ok) {
            $GLOBALS['tests_passed']++;
            echo "  ok   $label\n";

            return;
        }

        $GLOBALS['tests_failed']++;
        echo "  FAIL $label\n";
    }

    /**
     * Announce a group of assertions.
     *
     * @param   string  $name  Group name.
     *
     * @return  void
     */
    function group(string $name): void
    {
        echo "$name\n";
    }

    /**
     * Report the totals and exit with a status the runner can act on.
     *
     * @return  void
     */
    function finish(): void
    {
        echo "\n{$GLOBALS['tests_passed']} passed, {$GLOBALS['tests_failed']} failed\n";
        exit($GLOBALS['tests_failed'] === 0 ? 0 : 1);
    }

    /**
     * A one-pixel PNG.
     *
     * @return  string
     */
    function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    /**
     * A one-pixel JPEG.
     *
     * @return  string
     */
    function jpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );
    }

    /**
     * Answer every download with this body and status.
     *
     * @param   int     $code  HTTP status to return.
     * @param   string  $body  Body to return.
     *
     * @return  void
     */
    function respondWith(int $code, string $body): void
    {
        \Joomla\CMS\Http\TestHttp::$handler = static fn ($url) => new \Joomla\CMS\Http\TestResponse($code, $body);
    }

    /**
     * Make every download fail at the transport level.
     *
     * @return  void
     */
    function respondWithFailure(): void
    {
        \Joomla\CMS\Http\TestHttp::$handler = static function ($url) {
            throw new \RuntimeException('network is down');
        };
    }

    /**
     * Forget the downloads recorded so far.
     *
     * @return  void
     */
    function resetRequests(): void
    {
        \Joomla\CMS\Http\TestHttp::$requests = [];
    }

    /**
     * The downloads recorded since the last reset.
     *
     * @return  array
     */
    function requests(): array
    {
        return \Joomla\CMS\Http\TestHttp::$requests;
    }
}

namespace Joomla\CMS\Language {
    /**
     * Language strings are not what the layout tests are about, so a key stands in for
     * itself and sprintf keeps its arguments visible.
     */
    class Text
    {
        public static function _($string)
        {
            return (string) $string;
        }

        public static function sprintf($string, ...$args)
        {
            return $string . '(' . implode(',', array_map('strval', $args)) . ')';
        }

        public static function plural($string, $n, ...$args)
        {
            return $string . '(' . $n . ')';
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _($key, ...$args)
        {
            return null;
        }
    }
}

namespace Joomla\CMS\WebAsset {
    /**
     * Fluent no-op: the layouts chain registerAndUseStyle/useScript on it.
     */
    class TestWebAssetManager
    {
        public function __call($name, $arguments)
        {
            return $this;
        }
    }
}

namespace Joomla\CMS\Application {
    use Joomla\CMS\WebAsset\TestWebAssetManager;

    class TestDocument
    {
        public function getWebAssetManager()
        {
            return new TestWebAssetManager();
        }
    }

    class TestApplication
    {
        public function getDocument()
        {
            return new TestDocument();
        }
    }
}

namespace Joomla\Registry {
    /**
     * Just enough Registry for a layout: get() with a default.
     */
    class TestRegistry
    {
        private array $data;

        public function __construct(array $data = [])
        {
            $this->data = $data;
        }

        public function get($key, $default = null)
        {
            return $this->data[$key] ?? $default;
        }
    }
}

namespace Joomla\CMS\Uri {

    /**
     * Stands in for Joomla's URI helper.
     */
    class Uri
    {
        public static function root($pathonly = false)
        {
            // The test site lives at the domain root, so the path-only form is empty --
            // exactly the case a helper that forgets the leading slash gets wrong.
            return $pathonly ? '' : 'https://example.test/';
        }
    }
}

namespace Joomla\CMS\Log {

    /**
     * Collects what the module logs instead of writing it anywhere.
     */
    class Log
    {
        public const WARNING = 8;

        public static $lines = [];

        public static function add($entry, $priority = null, $category = null, $date = null)
        {
            self::$lines[] = $entry;
        }
    }
}

namespace Joomla\CMS\Http {

    /**
     * Stands in for the body of a Joomla HTTP response, which is a PSR-7 stream.
     */
    class TestStream
    {
        private $contents;

        public function __construct(string $contents)
        {
            $this->contents = $contents;
        }

        public function __toString(): string
        {
            return $this->contents;
        }
    }

    /**
     * Stands in for a Joomla HTTP response. Joomla 4 and up returns a PSR-7 response, so
     * the accessors are the ones the module is allowed to use -- the ->code and ->body
     * properties it used to reach for are a shim deprecated in Joomla 6.
     */
    class TestResponse
    {
        private $code;

        private $body;

        private $headers;

        public function __construct(int $code, string $body, array $headers = [])
        {
            $this->code    = $code;
            $this->body    = $body;
            $this->headers = array_change_key_case($headers);
        }

        public function getStatusCode()
        {
            return $this->code;
        }

        public function getBody()
        {
            return new TestStream($this->body);
        }

        public function getHeaderLine($name)
        {
            return $this->headers[strtolower($name)] ?? '';
        }
    }

    /**
     * Answers downloads from a handler the test installs, and records what was asked for.
     */
    class TestHttp
    {
        public static $handler;

        public static $requests = [];

        public function get($url, array $headers = [], $timeout = null)
        {
            self::$requests[] = ['url' => $url, 'timeout' => $timeout];

            return (self::$handler)($url);
        }
    }

    /**
     * Hands the module the stubbed HTTP client.
     */
    class HttpFactory
    {
        public static function getHttp($options = [], $adapters = null)
        {
            return new TestHttp();
        }
    }
}

namespace Joomla\Filesystem {

    /**
     * The parts of Joomla's File wrapper the module uses. write() takes its buffer by
     * reference, exactly as the real one does, so a caller that passes a literal fails
     * here too.
     */
    class File
    {
        public static function write($file, &$buffer, $useStreams = false)
        {
            if (!is_dir(\dirname($file))) {
                return false;
            }

            return file_put_contents($file, $buffer) !== false;
        }

        public static function delete($file)
        {
            foreach ((array) $file as $one) {
                if (is_file($one) && !@unlink($one)) {
                    return false;
                }
            }

            return true;
        }
    }

    /**
     * The parts of Joomla's Folder wrapper the module uses. files() hides dot files, as
     * the real one does through its exclude filters.
     */
    class Folder
    {
        public static function create($path, $mode = 0755)
        {
            return is_dir($path) || @mkdir($path, $mode, true);
        }

        public static function delete($path)
        {
            if (!is_dir($path)) {
                return false;
            }

            foreach (scandir($path) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $child = $path . '/' . $entry;

                is_dir($child) ? self::delete($child) : @unlink($child);
            }

            return @rmdir($path);
        }

        public static function files($path, $filter = '.', $recurse = false, $full = false)
        {
            $found = [];

            foreach (scandir($path) as $entry) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }

                if (is_file($path . '/' . $entry)) {
                    $found[] = $full ? $path . '/' . $entry : $entry;
                }
            }

            return $found;
        }
    }
}
