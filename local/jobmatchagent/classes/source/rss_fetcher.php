<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Generic RSS / Atom feed fetcher.
 *
 * @package    local_jobmatchagent
 * @copyright  2026 Fondazione Terzo Millennio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_jobmatchagent\source;

defined('MOODLE_INTERNAL') || die();

class rss_fetcher {

    /**
     * Fetch an RSS or Atom feed and return normalized items.
     *
     * @param string $url
     * @param int $timeout Seconds
     * @return array of items: ['title','link','description','pub_date','guid']
     * @throws \Exception on network/parse error
     */
    public static function fetch($url, $timeout = 30) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['ignoresecurity' => false]);
        $options = [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 3,
            'CURLOPT_USERAGENT' => 'JobMatchAgent/0.2 (Moodle local plugin)',
        ];
        $xml = $curl->get($url, [], $options);

        if ($curl->get_errno() !== 0) {
            throw new \Exception('Errore di rete: ' . $curl->error);
        }

        $info = $curl->get_info();
        if (isset($info['http_code']) && $info['http_code'] >= 400) {
            throw new \Exception('HTTP ' . $info['http_code']);
        }

        if (empty($xml) || strlen(trim($xml)) === 0) {
            throw new \Exception('Risposta vuota dal feed');
        }

        // Fix HTML named entities (e.g. &agrave;) that simplexml doesn't know.
        // Convert them to numeric entities while preserving the 5 XML standard ones.
        $xml = self::fix_html_entities($xml);

        libxml_use_internal_errors(true);
        $sx = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOENT);
        if (!$sx) {
            $errors = libxml_get_errors();
            $msg = !empty($errors) ? $errors[0]->message : 'XML non valido';
            libxml_clear_errors();
            throw new \Exception('Parse XML fallito: ' . trim($msg));
        }

        $items = [];

        // RSS 2.0
        if (isset($sx->channel) && isset($sx->channel->item)) {
            foreach ($sx->channel->item as $item) {
                $items[] = self::parse_rss_item($item);
            }
        // Atom
        } else if (isset($sx->entry)) {
            foreach ($sx->entry as $entry) {
                $items[] = self::parse_atom_entry($entry);
            }
        // RSS 1.0 (RDF)
        } else if (isset($sx->item)) {
            foreach ($sx->item as $item) {
                $items[] = self::parse_rss_item($item);
            }
        }

        return $items;
    }

    /**
     * Parse a single RSS 2.0 / 1.0 <item>.
     *
     * @param \SimpleXMLElement $item
     * @return array
     */
    private static function parse_rss_item($item) {
        $title = self::clean_text((string) ($item->title ?? ''));
        $link = self::clean_text((string) ($item->link ?? ''));
        $desc = self::clean_text((string) ($item->description ?? ''));
        $guid = self::clean_text((string) ($item->guid ?? $link));
        $pubdate = (string) ($item->pubDate ?? '');
        return [
            'title' => $title,
            'link' => $link,
            'description' => $desc,
            'pub_date' => self::parse_date($pubdate),
            'guid' => $guid,
        ];
    }

    /**
     * Parse a single Atom <entry>.
     *
     * @param \SimpleXMLElement $entry
     * @return array
     */
    private static function parse_atom_entry($entry) {
        $title = self::clean_text((string) ($entry->title ?? ''));
        $link = '';
        if (isset($entry->link)) {
            // Atom link can have href attribute.
            if (isset($entry->link['href'])) {
                $link = (string) $entry->link['href'];
            } else {
                $link = (string) $entry->link;
            }
        }
        $summary = (string) ($entry->summary ?? '');
        $content = (string) ($entry->content ?? '');
        $desc = self::clean_text($summary !== '' ? $summary : $content);
        $id = self::clean_text((string) ($entry->id ?? $link));
        $updated = (string) ($entry->updated ?? $entry->published ?? '');
        return [
            'title' => $title,
            'link' => $link,
            'description' => $desc,
            'pub_date' => self::parse_date($updated),
            'guid' => $id,
        ];
    }

    /**
     * @param string $str
     * @return int|null Unix timestamp
     */
    private static function parse_date($str) {
        if (empty($str)) {
            return null;
        }
        $ts = strtotime($str);
        return $ts !== false ? $ts : null;
    }

    /**
     * Strip HTML and normalize whitespace.
     *
     * @param string $str
     * @return string
     */
    private static function clean_text($str) {
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $str = strip_tags($str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    /**
     * Convert HTML named entities (e.g. &agrave;) to XML numeric entities (&#224;)
     * so that simplexml_load_string doesn't choke. Preserves the 5 standard XML
     * entities (&amp; &lt; &gt; &quot; &apos;).
     *
     * @param string $xml
     * @return string
     */
    private static function fix_html_entities($xml) {
        static $standard = ['amp' => 1, 'lt' => 1, 'gt' => 1, 'quot' => 1, 'apos' => 1];
        return preg_replace_callback(
            '/&([a-zA-Z][a-zA-Z0-9]+);/',
            function ($m) use ($standard) {
                if (isset($standard[$m[1]])) {
                    return $m[0];
                }
                $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($decoded === $m[0]) {
                    return $m[0];
                }
                $code = function_exists('mb_ord')
                    ? mb_ord($decoded, 'UTF-8')
                    : (function ($c) {
                        $bytes = unpack('C*', $c);
                        if (count($bytes) === 1) {
                            return $bytes[1];
                        }
                        // Multi-byte UTF-8 fallback.
                        return ord($c[0]);
                    })($decoded);
                return $code !== false ? '&#' . $code . ';' : $m[0];
            },
            $xml
        );
    }
}
