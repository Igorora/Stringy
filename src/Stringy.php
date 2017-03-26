<?php

namespace Stringy;

use voku\helper\AntiXSS;
use voku\helper\EmailCheck;
use voku\helper\URLify;
use voku\helper\UTF8;

/**
 * Class Stringy
 *
 * @package Stringy
 */
class Stringy implements \Countable, \IteratorAggregate, \ArrayAccess
{
  /**
   * An instance's string.
   *
   * @var string
   */
  protected $str;

  /**
   * The string's encoding, which should be one of the mbstring module's
   * supported encodings.
   *
   * @var string
   */
  protected $encoding;

  /**
   * Initializes a Stringy object and assigns both str and encoding properties
   * the supplied values. $str is cast to a string prior to assignment, and if
   * $encoding is not specified, it defaults to mb_internal_encoding(). Throws
   * an InvalidArgumentException if the first argument is an array or object
   * without a __toString method.
   *
   * @param  mixed  $str      Value to modify, after being cast to string
   * @param  string $encoding The character encoding
   *
   * @throws \InvalidArgumentException if an array or object without a
   *         __toString method is passed as the first argument
   */
  public function __construct($str = '', $encoding = null)
  {
    if (is_array($str)) {
      throw new \InvalidArgumentException(
          'Passed value cannot be an array'
      );
    }

    if (
        is_object($str)
        &&
        !method_exists($str, '__toString')
    ) {
      throw new \InvalidArgumentException(
          'Passed object must have a __toString method'
      );
    }

    // don't throw a notice on PHP 5.3
    if (!defined('ENT_SUBSTITUTE')) {
      define('ENT_SUBSTITUTE', 8);
    }

    // init
    UTF8::checkForSupport();

    $this->str = (string)$str;

    if ($encoding) {
      $this->encoding = $encoding;
    } else {
      UTF8::mbstring_loaded();
      $this->encoding = mb_internal_encoding();
    }

    if ($encoding) {
      $this->encoding = $encoding;
    } else {
      $this->encoding = mb_internal_encoding();
    }
  }

  /**
   * Returns the value in $str.
   *
   * @return string The current value of the $str property
   */
  public function __toString()
  {
    return (string)$this->str;
  }

  /**
   * Returns a new string with $string appended.
   *
   * @param  string $string The string to append
   *
   * @return static  Object with appended $string
   */
  public function append($string)
  {
    return static::create($this->str . $string, $this->encoding);
  }

  /**
   * Append an password (limited to chars that are good readable).
   *
   * @param int $length length of the random string
   *
   * @return static  Object with appended password
   */
  public function appendPassword($length)
  {
    $possibleChars = '2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ';

    return $this->appendRandomString($length, $possibleChars);
  }

  /**
   * Append an unique identifier.
   *
   * @param string|int $extraPrefix
   *
   * @return static  Object with appended unique identifier as md5-hash
   */
  public function appendUniqueIdentifier($extraPrefix = '')
  {
    $prefix = mt_rand() .
              session_id() .
              (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') .
              (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '') .
              $extraPrefix;

    return $this->append(md5(uniqid($prefix, true) . $prefix));
  }

  /**
   * Append an random string.
   *
   * @param int    $length        length of the random string
   * @param string $possibleChars characters string for the random selection
   *
   * @return static  Object with appended random string
   */
  public function appendRandomString($length, $possibleChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
  {
    // init
    $i = 0;
    $length = (int)$length;
    $str = $this->str;
    $maxlength = UTF8::strlen($possibleChars, $this->encoding);

    if ($maxlength === 0) {
      return $this;
    }

    // add random chars
    while ($i < $length) {
      $char = UTF8::substr($possibleChars, mt_rand(0, $maxlength - 1), 1, $this->encoding);
      $str .= $char;
      $i++;
    }

    return $this->append($str);
  }

  /**
   * Creates a Stringy object and assigns both str and encoding properties
   * the supplied values. $str is cast to a string prior to assignment, and if
   * $encoding is not specified, it defaults to mb_internal_encoding(). It
   * then returns the initialized object. Throws an InvalidArgumentException
   * if the first argument is an array or object without a __toString method.
   *
   * @param  mixed  $str      Value to modify, after being cast to string
   * @param  string $encoding The character encoding
   *
   * @return static  A Stringy object
   * @throws \InvalidArgumentException if an array or object without a
   *         __toString method is passed as the first argument
   */
  public static function create($str = '', $encoding = null)
  {
    return new static($str, $encoding);
  }

  /**
   * Returns the substring between $start and $end, if found, or an empty
   * string. An optional offset may be supplied from which to begin the
   * search for the start string.
   *
   * @param  string $start  Delimiter marking the start of the substring
   * @param  string $end    Delimiter marking the end of the substring
   * @param  int    $offset Index from which to begin the search
   *
   * @return static  Object whose $str is a substring between $start and $end
   */
  public function between($start, $end, $offset = 0)
  {
    $startIndex = $this->indexOf($start, $offset);
    if ($startIndex === false) {
      return static::create('', $this->encoding);
    }

    $substrIndex = $startIndex + UTF8::strlen($start, $this->encoding);
    $endIndex = $this->indexOf($end, $substrIndex);
    if ($endIndex === false) {
      return static::create('', $this->encoding);
    }

    return $this->substr($substrIndex, $endIndex - $substrIndex);
  }

  /**
   * Returns the index of the first occurrence of $needle in the string,
   * and false if not found. Accepts an optional offset from which to begin
   * the search.
   *
   * @param  string $needle Substring to look for
   * @param  int    $offset Offset from which to search
   *
   * @return int|bool The occurrence's index if found, otherwise false
   */
  public function indexOf($needle, $offset = 0)
  {
    return UTF8::strpos($this->str, (string)$needle, (int)$offset, $this->encoding);
  }

  /**
   * Returns the substring beginning at $start with the specified $length.
   * It differs from the UTF8::substr() function in that providing a $length of
   * null will return the rest of the string, rather than an empty string.
   *
   * @param  int $start  Position of the first character to use
   * @param  int $length Maximum number of characters used
   *
   * @return static  Object with its $str being the substring
   */
  public function substr($start, $length = null)
  {
    if ($length === null) {
      $length = $this->length();
    }

    $str = UTF8::substr($this->str, $start, $length, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns the length of the string.
   *
   * @return int The number of characters in $str given the encoding
   */
  public function length()
  {
    return UTF8::strlen($this->str, $this->encoding);
  }

  /**
   * Trims the string and replaces consecutive whitespace characters with a
   * single space. This includes tabs and newline characters, as well as
   * multibyte whitespace such as the thin space and ideographic space.
   *
   * @return static  Object with a trimmed $str and condensed whitespace
   */
  public function collapseWhitespace()
  {
    return $this->regexReplace('[[:space:]]+', ' ')->trim();
  }

  /**
   * Returns a string with whitespace removed from the start and end of the
   * string. Supports the removal of unicode whitespace. Accepts an optional
   * string of characters to strip instead of the defaults.
   *
   * @param  string $chars Optional string of characters to strip
   *
   * @return static  Object with a trimmed $str
   */
  public function trim($chars = null)
  {
    if (!$chars) {
      $chars = '[:space:]';
    } else {
      $chars = preg_quote($chars, '/');
    }

    return $this->regexReplace("^[$chars]+|[$chars]+\$", '');
  }

  /**
   * Replaces all occurrences of $pattern in $str by $replacement.
   *
   * @param  string $pattern     The regular expression pattern
   * @param  string $replacement The string to replace with
   * @param  string $options     Matching conditions to be used
   *
   * @return static  Object with the result2ing $str after the replacements
   */
  public function regexReplace($pattern, $replacement, $options = '')
  {
    if ($options === 'msr') {
      $options = 'ms';
    }

    $str = preg_replace(
        '/' . $pattern . '/u' . $options,
        $replacement,
        $this->str
    );

    return static::create($str, $this->encoding);
  }

  /**
   * Returns true if the string contains all $needles, false otherwise. By
   * default the comparison is case-sensitive, but can be made insensitive by
   * setting $caseSensitive to false.
   *
   * @param  array $needles       SubStrings to look for
   * @param  bool  $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str contains $needle
   */
  public function containsAll($needles, $caseSensitive = true)
  {
    /** @noinspection IsEmptyFunctionUsageInspection */
    if (empty($needles)) {
      return false;
    }

    foreach ($needles as $needle) {
      if (!$this->contains($needle, $caseSensitive)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Returns true if the string contains $needle, false otherwise. By default
   * the comparison is case-sensitive, but can be made insensitive by setting
   * $caseSensitive to false.
   *
   * @param  string $needle        Substring to look for
   * @param  bool   $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str contains $needle
   */
  public function contains($needle, $caseSensitive = true)
  {
    $encoding = $this->encoding;

    if ($caseSensitive) {
      return (UTF8::strpos($this->str, $needle, 0, $encoding) !== false);
    }

    return (UTF8::stripos($this->str, $needle, 0, $encoding) !== false);
  }

  /**
   * Returns true if the string contains any $needles, false otherwise. By
   * default the comparison is case-sensitive, but can be made insensitive by
   * setting $caseSensitive to false.
   *
   * @param  array $needles       SubStrings to look for
   * @param  bool  $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str contains $needle
   */
  public function containsAny($needles, $caseSensitive = true)
  {
    /** @noinspection IsEmptyFunctionUsageInspection */
    if (empty($needles)) {
      return false;
    }

    foreach ($needles as $needle) {
      if ($this->contains($needle, $caseSensitive)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns the length of the string, implementing the countable interface.
   *
   * @return int The number of characters in the string, given the encoding
   */
  public function count()
  {
    return $this->length();
  }

  /**
   * Returns the number of occurrences of $substring in the given string.
   * By default, the comparison is case-sensitive, but can be made insensitive
   * by setting $caseSensitive to false.
   *
   * @param  string $substring     The substring to search for
   * @param  bool   $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return int    The number of $substring occurrences
   */
  public function countSubstr($substring, $caseSensitive = true)
  {
    if ($caseSensitive) {
      return UTF8::substr_count($this->str, $substring, 0, null, $this->encoding);
    }

    $str = UTF8::strtoupper($this->str, $this->encoding);
    $substring = UTF8::strtoupper($substring, $this->encoding);

    return UTF8::substr_count($str, $substring, 0, null, $this->encoding);
  }

  /**
   * Returns a lowercase and trimmed string separated by dashes. Dashes are
   * inserted before uppercase characters (with the exception of the first
   * character of the string), and in place of spaces as well as underscores.
   *
   * @return static  Object with a dasherized $str
   */
  public function dasherize()
  {
    return $this->delimit('-');
  }

  /**
   * Returns a lowercase and trimmed string separated by the given delimiter.
   * Delimiters are inserted before uppercase characters (with the exception
   * of the first character of the string), and in place of spaces, dashes,
   * and underscores. Alpha delimiters are not converted to lowercase.
   *
   * @param  string $delimiter Sequence used to separate parts of the string
   *
   * @return static  Object with a delimited $str
   */
  public function delimit($delimiter)
  {
    $str = $this->trim();

    $str = preg_replace('/\B([A-Z])/u', '-\1', $str);

    $str = UTF8::strtolower($str, $this->encoding);

    $str = preg_replace('/[-_\s]+/u', $delimiter, $str);

    return static::create($str, $this->encoding);
  }

  /**
   * Ensures that the string begins with $substring. If it doesn't, it's
   * prepended.
   *
   * @param  string $substring The substring to add if not present
   *
   * @return static  Object with its $str prefixed by the $substring
   */
  public function ensureLeft($substring)
  {
    $stringy = static::create($this->str, $this->encoding);

    if (!$stringy->startsWith($substring)) {
      $stringy->str = $substring . $stringy->str;
    }

    return $stringy;
  }

  /**
   * Returns true if the string begins with $substring, false otherwise. By
   * default, the comparison is case-sensitive, but can be made insensitive
   * by setting $caseSensitive to false.
   *
   * @param  string $substring     The substring to look for
   * @param  bool   $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str starts with $substring
   */
  public function startsWith($substring, $caseSensitive = true)
  {
    $str = $this->str;

    if (!$caseSensitive) {
      $substring = UTF8::strtolower($substring, $this->encoding);
      $str = UTF8::strtolower($this->str, $this->encoding);
    }

    return UTF8::strpos($str, $substring, $this->encoding) === 0;
  }

  /**
   * Returns true if the string begins with any of $substrings, false otherwise.
   * By default the comparison is case-sensitive, but can be made insensitive by
   * setting $caseSensitive to false.
   *
   * @param  array $substrings    Substrings to look for
   * @param  bool  $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str starts with $substring
   */
  public function startsWithAny(array $substrings, $caseSensitive = true)
  {
    if (empty($substrings)) {
      return false;
    }

    foreach ($substrings as $substring) {
      if ($this->startsWith($substring, $caseSensitive)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Ensures that the string ends with $substring. If it doesn't, it's
   * appended.
   *
   * @param  string $substring The substring to add if not present
   *
   * @return static  Object with its $str suffixed by the $substring
   */
  public function ensureRight($substring)
  {
    $stringy = static::create($this->str, $this->encoding);

    if (!$stringy->endsWith($substring)) {
      $stringy->str .= $substring;
    }

    return $stringy;
  }

  /**
   * Returns true if the string ends with $substring, false otherwise. By
   * default, the comparison is case-sensitive, but can be made insensitive
   * by setting $caseSensitive to false.
   *
   * @param  string $substring     The substring to look for
   * @param  bool   $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return bool   Whether or not $str ends with $substring
   */
  public function endsWith($substring, $caseSensitive = true)
  {
    $substringLength = UTF8::strlen($substring, $this->encoding);
    $strLength = $this->length();

    $endOfStr = UTF8::substr(
        $this->str,
        $strLength - $substringLength,
        $substringLength,
        $this->encoding
    );

    if (!$caseSensitive) {
      $substring = UTF8::strtolower($substring, $this->encoding);
      $endOfStr = UTF8::strtolower($endOfStr, $this->encoding);
    }

    return (string)$substring === $endOfStr;
  }

  /**
   * Returns true if the string ends with any of $substrings, false otherwise.
   * By default, the comparison is case-sensitive, but can be made insensitive
   * by setting $caseSensitive to false.
   *
   * @param  string[] $substrings    Substrings to look for
   * @param  bool     $caseSensitive Whether or not to enforce
   *                                 case-sensitivity
   * @return bool     Whether or not $str ends with $substring
   */
  public function endsWithAny($substrings, $caseSensitive = true)
  {
    if (empty($substrings)) {
      return false;
    }

    foreach ($substrings as $substring) {
      if ($this->endsWith($substring, $caseSensitive)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Returns the first $n characters of the string.
   *
   * @param  int $n Number of characters to retrieve from the start
   *
   * @return static  Object with its $str being the first $n chars
   */
  public function first($n)
  {
    $stringy = static::create($this->str, $this->encoding);

    if ($n < 0) {
      $stringy->str = '';
    } else {
      return $stringy->substr(0, $n);
    }

    return $stringy;
  }

  /**
   * Returns the encoding used by the Stringy object.
   *
   * @return string The current value of the $encoding property
   */
  public function getEncoding()
  {
    return $this->encoding;
  }

  /**
   * Returns a new ArrayIterator, thus implementing the IteratorAggregate
   * interface. The ArrayIterator's constructor is passed an array of chars
   * in the multibyte string. This enables the use of foreach with instances
   * of Stringy\Stringy.
   *
   * @return \ArrayIterator An iterator for the characters in the string
   */
  public function getIterator()
  {
    return new \ArrayIterator($this->chars());
  }

  /**
   * Returns an array consisting of the characters in the string.
   *
   * @return array An array of string chars
   */
  public function chars()
  {
    // init
    $chars = array();
    $l = $this->length();

    for ($i = 0; $i < $l; $i++) {
      $chars[] = $this->at($i)->str;
    }

    return $chars;
  }

  /**
   * Returns the character at $index, with indexes starting at 0.
   *
   * @param  int $index Position of the character
   *
   * @return static  The character at $index
   */
  public function at($index)
  {
    return $this->substr($index, 1);
  }

  /**
   * Returns true if the string contains a lower case char, false
   * otherwise.
   *
   * @return bool Whether or not the string contains a lower case character.
   */
  public function hasLowerCase()
  {
    return $this->matchesPattern('.*[[:lower:]]');
  }

  /**
   * Returns true if $str matches the supplied pattern, false otherwise.
   *
   * @param  string $pattern Regex pattern to match against
   *
   * @return bool   Whether or not $str matches the pattern
   */
  protected function matchesPattern($pattern)
  {
    if (preg_match('/' . $pattern . '/u', $this->str)) {
      return true;
    }

    return false;
  }

  /**
   * Returns true if the string contains an upper case char, false
   * otherwise.
   *
   * @return bool Whether or not the string contains an upper case character.
   */
  public function hasUpperCase()
  {
    return $this->matchesPattern('.*[[:upper:]]');
  }

  /**
   * Convert all HTML entities to their applicable characters.
   *
   * @param  int|null $flags Optional flags
   *
   * @return static   Object with the resulting $str after being html decoded.
   */
  public function htmlDecode($flags = ENT_COMPAT)
  {
    $str = UTF8::html_entity_decode($this->str, $flags, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Convert all applicable characters to HTML entities.
   *
   * @param  int|null $flags Optional flags
   *
   * @return static   Object with the resulting $str after being html encoded.
   */
  public function htmlEncode($flags = ENT_COMPAT)
  {
    $str = UTF8::htmlentities($this->str, $flags, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Capitalizes the first word of the string, replaces underscores with
   * spaces, and strips '_id'.
   *
   * @return static  Object with a humanized $str
   */
  public function humanize()
  {
    $str = UTF8::str_replace(array('_id', '_'), array('', ' '), $this->str);

    return static::create($str, $this->encoding)->trim()->upperCaseFirst();
  }

  /**
   * Converts the first character of the supplied string to upper case.
   *
   * @return static  Object with the first character of $str being upper case
   */
  public function upperCaseFirst()
  {
    $first = UTF8::substr($this->str, 0, 1, $this->encoding);
    $rest = UTF8::substr(
        $this->str,
        1,
        $this->length() - 1,
        $this->encoding
    );

    $str = UTF8::strtoupper($first, $this->encoding) . $rest;

    return static::create($str, $this->encoding);
  }

  /**
   * Returns the index of the last occurrence of $needle in the string,
   * and false if not found. Accepts an optional offset from which to begin
   * the search. Offsets may be negative to count from the last character
   * in the string.
   *
   * @param  string $needle Substring to look for
   * @param  int    $offset Offset from which to search
   *
   * @return int|bool The last occurrence's index if found, otherwise false
   */
  public function indexOfLast($needle, $offset = 0)
  {
    return UTF8::strrpos($this->str, (string)$needle, (int)$offset, $this->encoding);
  }

  /**
   * Inserts $substring into the string at the $index provided.
   *
   * @param  string $substring String to be inserted
   * @param  int    $index     The index at which to insert the substring
   *
   * @return static  Object with the resulting $str after the insertion
   */
  public function insert($substring, $index)
  {
    $stringy = static::create($this->str, $this->encoding);
    if ($index > $stringy->length()) {
      return $stringy;
    }

    $start = UTF8::substr($stringy->str, 0, $index, $stringy->encoding);
    $end = UTF8::substr($stringy->str, $index, $stringy->length(), $stringy->encoding);

    $stringy->str = $start . $substring . $end;

    return $stringy;
  }

  /**
   * Returns true if the string contains the $pattern, otherwise false.
   *
   * WARNING: Asterisks ("*") are translated into (".*") zero-or-more regular
   * expression wildcards.
   *
   * @credit Originally from Laravel, thanks Taylor.
   *
   * @param string $pattern The string or pattern to match against.
   *
   * @return bool Whether or not we match the provided pattern.
   */
  public function is($pattern)
  {
    if ($this->toString() === $pattern) {
      return true;
    }

    $quotedPattern = preg_quote($pattern, '/');
    $replaceWildCards = str_replace('\*', '.*', $quotedPattern);

    return $this->matchesPattern('^' . $replaceWildCards . '\z');
  }

  /**
   * Returns true if the string contains only alphabetic chars, false
   * otherwise.
   *
   * @return bool Whether or not $str contains only alphabetic chars
   */
  public function isAlpha()
  {
    return $this->matchesPattern('^[[:alpha:]]*$');
  }

  /**
   * Determine whether the string is considered to be empty.
   *
   * A variable is considered empty if it does not exist or if its value equals FALSE.
   * empty() does not generate a warning if the variable does not exist.
   *
   * @return bool
   */
  public function isEmpty()
  {
    return empty($this->str);
  }

  /**
   * Returns true if the string contains only alphabetic and numeric chars,
   * false otherwise.
   *
   * @return bool Whether or not $str contains only alphanumeric chars
   */
  public function isAlphanumeric()
  {
    return $this->matchesPattern('^[[:alnum:]]*$');
  }

  /**
   * Returns true if the string contains only whitespace chars, false
   * otherwise.
   *
   * @return bool Whether or not $str contains only whitespace characters
   */
  public function isBlank()
  {
    return $this->matchesPattern('^[[:space:]]*$');
  }

  /**
   * Returns true if the string contains only hexadecimal chars, false
   * otherwise.
   *
   * @return bool Whether or not $str contains only hexadecimal chars
   */
  public function isHexadecimal()
  {
    return $this->matchesPattern('^[[:xdigit:]]*$');
  }

  /**
   * Returns true if the string contains HTML-Tags, false otherwise.
   *
   * @return bool Whether or not $str contains HTML-Tags
   */
  public function isHtml()
  {
    return UTF8::is_html($this->str);
  }

  /**
   * Returns true if the string contains a valid E-Mail address, false otherwise.
   *
   * @param bool $useExampleDomainCheck
   * @param bool $useTypoInDomainCheck
   * @param bool $useTemporaryDomainCheck
   * @param bool $useDnsCheck
   *
   * @return bool Whether or not $str contains a valid E-Mail address
   */
  public function isEmail($useExampleDomainCheck = false, $useTypoInDomainCheck = false, $useTemporaryDomainCheck = false, $useDnsCheck = false)
  {
    return EmailCheck::isValid($this->str, $useExampleDomainCheck, $useTypoInDomainCheck, $useTemporaryDomainCheck, $useDnsCheck);
  }

  /**
   * Returns true if the string is JSON, false otherwise. Unlike json_decode
   * in PHP 5.x, this method is consistent with PHP 7 and other JSON parsers,
   * in that an empty string is not considered valid JSON.
   *
   * @return bool Whether or not $str is JSON
   */
  public function isJson()
  {
    if (!isset($this->str[0])) {
      return false;
    }

    json_decode($this->str);

    if (json_last_error() === JSON_ERROR_NONE) {
      return true;
    }

    return false;
  }

  /**
   * Returns true if the string contains only lower case chars, false
   * otherwise.
   *
   * @return bool Whether or not $str contains only lower case characters
   */
  public function isLowerCase()
  {
    if ($this->matchesPattern('^[[:lower:]]*$')) {
      return true;
    }

    return false;
  }

  /**
   * Returns true if the string is serialized, false otherwise.
   *
   * @return bool Whether or not $str is serialized
   */
  public function isSerialized()
  {
    if (!isset($this->str[0])) {
      return false;
    }

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (
        $this->str === 'b:0;'
        ||
        @unserialize($this->str) !== false
    ) {
      return true;
    }

    return false;
  }

  /**
   * Returns true if the string contains only lower case chars, false
   * otherwise.
   *
   * @return bool Whether or not $str contains only lower case characters
   */
  public function isUpperCase()
  {
    return $this->matchesPattern('^[[:upper:]]*$');
  }

  /**
   * Returns the last $n characters of the string.
   *
   * @param  int $n Number of characters to retrieve from the end
   *
   * @return static  Object with its $str being the last $n chars
   */
  public function last($n)
  {
    $stringy = static::create($this->str, $this->encoding);

    if ($n <= 0) {
      $stringy->str = '';
    } else {
      return $stringy->substr(-$n);
    }

    return $stringy;
  }

  /**
   * Splits on newlines and carriage returns, returning an array of Stringy
   * objects corresponding to the lines in the string.
   *
   * @return static [] An array of Stringy objects
   */
  public function lines()
  {
    $array = preg_split('/[\r\n]{1,2}/u', $this->str);
    /** @noinspection CallableInLoopTerminationConditionInspection */
    /** @noinspection ForeachInvariantsInspection */
    for ($i = 0; $i < count($array); $i++) {
      $array[$i] = static::create($array[$i], $this->encoding);
    }

    return $array;
  }

  /**
   * Returns the longest common prefix between the string and $otherStr.
   *
   * @param  string $otherStr Second string for comparison
   *
   * @return static  Object with its $str being the longest common prefix
   */
  public function longestCommonPrefix($otherStr)
  {
    $encoding = $this->encoding;
    $maxLength = min($this->length(), UTF8::strlen($otherStr, $encoding));

    $longestCommonPrefix = '';
    for ($i = 0; $i < $maxLength; $i++) {
      $char = UTF8::substr($this->str, $i, 1, $encoding);

      if ($char == UTF8::substr($otherStr, $i, 1, $encoding)) {
        $longestCommonPrefix .= $char;
      } else {
        break;
      }
    }

    return static::create($longestCommonPrefix, $encoding);
  }

  /**
   * Returns the longest common suffix between the string and $otherStr.
   *
   * @param  string $otherStr Second string for comparison
   *
   * @return static  Object with its $str being the longest common suffix
   */
  public function longestCommonSuffix($otherStr)
  {
    $encoding = $this->encoding;
    $maxLength = min($this->length(), UTF8::strlen($otherStr, $encoding));

    $longestCommonSuffix = '';
    for ($i = 1; $i <= $maxLength; $i++) {
      $char = UTF8::substr($this->str, -$i, 1, $encoding);

      if ($char == UTF8::substr($otherStr, -$i, 1, $encoding)) {
        $longestCommonSuffix = $char . $longestCommonSuffix;
      } else {
        break;
      }
    }

    return static::create($longestCommonSuffix, $encoding);
  }

  /**
   * Returns the longest common substring between the string and $otherStr.
   * In the case of ties, it returns that which occurs first.
   *
   * @param  string $otherStr Second string for comparison
   *
   * @return static  Object with its $str being the longest common substring
   */
  public function longestCommonSubstring($otherStr)
  {
    // Uses dynamic programming to solve
    // http://en.wikipedia.org/wiki/Longest_common_substring_problem
    $encoding = $this->encoding;
    $stringy = static::create($this->str, $encoding);
    $strLength = $stringy->length();
    $otherLength = UTF8::strlen($otherStr, $encoding);

    // Return if either string is empty
    if ($strLength == 0 || $otherLength == 0) {
      $stringy->str = '';

      return $stringy;
    }

    $len = 0;
    $end = 0;
    $table = array_fill(
        0, $strLength + 1,
        array_fill(0, $otherLength + 1, 0)
    );

    for ($i = 1; $i <= $strLength; $i++) {
      for ($j = 1; $j <= $otherLength; $j++) {
        $strChar = UTF8::substr($stringy->str, $i - 1, 1, $encoding);
        $otherChar = UTF8::substr($otherStr, $j - 1, 1, $encoding);

        if ($strChar == $otherChar) {
          $table[$i][$j] = $table[$i - 1][$j - 1] + 1;
          if ($table[$i][$j] > $len) {
            $len = $table[$i][$j];
            $end = $i;
          }
        } else {
          $table[$i][$j] = 0;
        }
      }
    }

    $stringy->str = UTF8::substr($stringy->str, $end - $len, $len, $encoding);

    return $stringy;
  }

  /**
   * Returns whether or not a character exists at an index. Offsets may be
   * negative to count from the last character in the string. Implements
   * part of the ArrayAccess interface.
   *
   * @param  mixed $offset The index to check
   *
   * @return boolean Whether or not the index exists
   */
  public function offsetExists($offset)
  {
    // init
    $length = $this->length();
    $offset = (int)$offset;

    if ($offset >= 0) {
      return ($length > $offset);
    }

    return ($length >= abs($offset));
  }

  /**
   * Returns the character at the given index. Offsets may be negative to
   * count from the last character in the string. Implements part of the
   * ArrayAccess interface, and throws an OutOfBoundsException if the index
   * does not exist.
   *
   * @param  mixed $offset The index from which to retrieve the char
   *
   * @return string                 The character at the specified index
   * @throws \OutOfBoundsException If the positive or negative offset does
   *                               not exist
   */
  public function offsetGet($offset)
  {
    // init
    $offset = (int)$offset;
    $length = $this->length();

    if (
        ($offset >= 0 && $length <= $offset)
        ||
        $length < abs($offset)
    ) {
      throw new \OutOfBoundsException('No character exists at the index');
    }

    return UTF8::substr($this->str, $offset, 1, $this->encoding);
  }

  /**
   * Implements part of the ArrayAccess interface, but throws an exception
   * when called. This maintains the immutability of Stringy objects.
   *
   * @param  mixed $offset The index of the character
   * @param  mixed $value  Value to set
   *
   * @throws \Exception When called
   */
  public function offsetSet($offset, $value)
  {
    // Stringy is immutable, cannot directly set char
    throw new \Exception('Stringy object is immutable, cannot modify char');
  }

  /**
   * Implements part of the ArrayAccess interface, but throws an exception
   * when called. This maintains the immutability of Stringy objects.
   *
   * @param  mixed $offset The index of the character
   *
   * @throws \Exception When called
   */
  public function offsetUnset($offset)
  {
    // Don't allow directly modifying the string
    throw new \Exception('Stringy object is immutable, cannot unset char');
  }

  /**
   * Pads the string to a given length with $padStr. If length is less than
   * or equal to the length of the string, no padding takes places. The
   * default string used for padding is a space, and the default type (one of
   * 'left', 'right', 'both') is 'right'. Throws an InvalidArgumentException
   * if $padType isn't one of those 3 values.
   *
   * @param  int    $length  Desired string length after padding
   * @param  string $padStr  String used to pad, defaults to space
   * @param  string $padType One of 'left', 'right', 'both'
   *
   * @return static  Object with a padded $str
   * @throws \InvalidArgumentException If $padType isn't one of 'right', 'left' or 'both'
   */
  public function pad($length, $padStr = ' ', $padType = 'right')
  {
    if (!in_array($padType, array('left', 'right', 'both'), true)) {
      throw new \InvalidArgumentException(
          'Pad expects $padType ' . "to be one of 'left', 'right' or 'both'"
      );
    }

    switch ($padType) {
      case 'left':
        return $this->padLeft($length, $padStr);
      case 'right':
        return $this->padRight($length, $padStr);
      default:
        return $this->padBoth($length, $padStr);
    }
  }

  /**
   * Returns a new string of a given length such that the beginning of the
   * string is padded. Alias for pad() with a $padType of 'left'.
   *
   * @param  int    $length Desired string length after padding
   * @param  string $padStr String used to pad, defaults to space
   *
   * @return static  String with left padding
   */
  public function padLeft($length, $padStr = ' ')
  {
    return $this->applyPadding($length - $this->length(), 0, $padStr);
  }

  /**
   * Adds the specified amount of left and right padding to the given string.
   * The default character used is a space.
   *
   * @param  int    $left   Length of left padding
   * @param  int    $right  Length of right padding
   * @param  string $padStr String used to pad
   *
   * @return static  String with padding applied
   */
  protected function applyPadding($left = 0, $right = 0, $padStr = ' ')
  {
    $stringy = static::create($this->str, $this->encoding);

    $length = UTF8::strlen($padStr, $stringy->encoding);

    $strLength = $stringy->length();
    $paddedLength = $strLength + $left + $right;

    if (!$length || $paddedLength <= $strLength) {
      return $stringy;
    }

    $leftPadding = UTF8::substr(
        UTF8::str_repeat(
            $padStr,
            ceil($left / $length)
        ),
        0,
        $left,
        $stringy->encoding
    );

    $rightPadding = UTF8::substr(
        UTF8::str_repeat(
            $padStr,
            ceil($right / $length)
        ),
        0,
        $right,
        $stringy->encoding
    );

    $stringy->str = $leftPadding . $stringy->str . $rightPadding;

    return $stringy;
  }

  /**
   * Returns a new string of a given length such that the end of the string
   * is padded. Alias for pad() with a $padType of 'right'.
   *
   * @param  int    $length Desired string length after padding
   * @param  string $padStr String used to pad, defaults to space
   *
   * @return static  String with right padding
   */
  public function padRight($length, $padStr = ' ')
  {
    return $this->applyPadding(0, $length - $this->length(), $padStr);
  }

  /**
   * Returns a new string of a given length such that both sides of the
   * string are padded. Alias for pad() with a $padType of 'both'.
   *
   * @param  int    $length Desired string length after padding
   * @param  string $padStr String used to pad, defaults to space
   *
   * @return static  String with padding applied
   */
  public function padBoth($length, $padStr = ' ')
  {
    $padding = $length - $this->length();

    return $this->applyPadding(floor($padding / 2), ceil($padding / 2), $padStr);
  }

  /**
   * Returns a new string starting with $string.
   *
   * @param  string $string The string to append
   *
   * @return static  Object with appended $string
   */
  public function prepend($string)
  {
    return static::create($string . $this->str, $this->encoding);
  }

  /**
   * Returns a new string with the prefix $substring removed, if present.
   *
   * @param  string $substring The prefix to remove
   *
   * @return static  Object having a $str without the prefix $substring
   */
  public function removeLeft($substring)
  {
    $stringy = static::create($this->str, $this->encoding);

    if ($stringy->startsWith($substring)) {
      $substringLength = UTF8::strlen($substring, $stringy->encoding);

      return $stringy->substr($substringLength);
    }

    return $stringy;
  }

  /**
   * Returns a new string with the suffix $substring removed, if present.
   *
   * @param  string $substring The suffix to remove
   *
   * @return static  Object having a $str without the suffix $substring
   */
  public function removeRight($substring)
  {
    $stringy = static::create($this->str, $this->encoding);

    if ($stringy->endsWith($substring)) {
      $substringLength = UTF8::strlen($substring, $stringy->encoding);

      return $stringy->substr(0, $stringy->length() - $substringLength);
    }

    return $stringy;
  }

  /**
   * Returns a repeated string given a multiplier.
   *
   * @param  int $multiplier The number of times to repeat the string
   *
   * @return static  Object with a repeated str
   */
  public function repeat($multiplier)
  {
    $repeated = UTF8::str_repeat($this->str, $multiplier);

    return static::create($repeated, $this->encoding);
  }

  /**
   * Replaces all occurrences of $search in $str by $replacement.
   *
   * @param string $search        The needle to search for
   * @param string $replacement   The string to replace with
   * @param bool   $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return static  Object with the resulting $str after the replacements
   */
  public function replace($search, $replacement, $caseSensitive = true)
  {
    if ($caseSensitive) {
      $return = UTF8::str_replace($search, $replacement, $this->str);
    } else {
      $return = UTF8::str_ireplace($search, $replacement, $this->str);
    }

    return static::create($return);
  }

  /**
   * Replaces all occurrences of $search in $str by $replacement.
   *
   * @param array        $search        The elements to search for
   * @param string|array $replacement   The string to replace with
   * @param bool         $caseSensitive Whether or not to enforce case-sensitivity
   *
   * @return static  Object with the resulting $str after the replacements
   */
  public function replaceAll(array $search, $replacement, $caseSensitive = true)
  {
    if ($caseSensitive) {
      $return = UTF8::str_replace($search, $replacement, $this->str);
    } else {
      $return = UTF8::str_ireplace($search, $replacement, $this->str);
    }

    return static::create($return);
  }

  /**
   * Replaces all occurrences of $search from the beginning of string with $replacement
   *
   * @param string $search
   * @param string $replacement
   *
   * @return static  Object with the resulting $str after the replacements
   */
  public function replaceBeginning($search, $replacement)
  {
    $str = $this->regexReplace('^' . preg_quote($search, '/'), UTF8::str_replace('\\', '\\\\', $replacement));

    return static::create($str, $this->encoding);
  }

  /**
   * Replaces all occurrences of $search from the ending of string with $replacement
   *
   * @param string $search
   * @param string $replacement
   *
   * @return static  Object with the resulting $str after the replacements
   */
  public function replaceEnding($search, $replacement)
  {
    $str = $this->regexReplace(preg_quote($search, '/') . '$', UTF8::str_replace('\\', '\\\\', $replacement));

    return static::create($str, $this->encoding);
  }

  /**
   * Returns a reversed string. A multibyte version of strrev().
   *
   * @return static  Object with a reversed $str
   */
  public function reverse()
  {
    $reversed = UTF8::strrev($this->str);

    return static::create($reversed, $this->encoding);
  }

  /**
   * Truncates the string to a given length, while ensuring that it does not
   * split words. If $substring is provided, and truncating occurs, the
   * string is further truncated so that the substring may be appended without
   * exceeding the desired length.
   *
   * @param  int    $length    Desired length of the truncated string
   * @param  string $substring The substring to append if it can fit
   *
   * @return static  Object with the resulting $str after truncating
   */
  public function safeTruncate($length, $substring = '')
  {
    $stringy = static::create($this->str, $this->encoding);
    if ($length >= $stringy->length()) {
      return $stringy;
    }

    // Need to further trim the string so we can append the substring
    $encoding = $stringy->encoding;
    $substringLength = UTF8::strlen($substring, $encoding);
    $length -= $substringLength;

    $truncated = UTF8::substr($stringy->str, 0, $length, $encoding);

    // If the last word was truncated
    $strPosSpace = UTF8::strpos($stringy->str, ' ', $length - 1, $encoding);
    if ($strPosSpace != $length) {
      // Find pos of the last occurrence of a space, get up to that
      $lastPos = UTF8::strrpos($truncated, ' ', 0, $encoding);

      if ($lastPos !== false || $strPosSpace !== false) {
        $truncated = UTF8::substr($truncated, 0, $lastPos, $encoding);
      }
    }

    $stringy->str = $truncated . $substring;

    return $stringy;
  }

  /**
   * A multibyte string shuffle function. It returns a string with its
   * characters in random order.
   *
   * @return static  Object with a shuffled $str
   */
  public function shuffle()
  {
    $shuffledStr = UTF8::str_shuffle($this->str);

    return static::create($shuffledStr, $this->encoding);
  }

  /**
   * Converts the string into an URL slug. This includes replacing non-ASCII
   * characters with their closest ASCII equivalents, removing remaining
   * non-ASCII and non-alphanumeric characters, and replacing whitespace with
   * $replacement. The replacement defaults to a single dash, and the string
   * is also converted to lowercase.
   *
   * @param string $replacement The string used to replace whitespace
   * @param string $language    The language for the url
   * @param bool   $strToLower  string to lower
   *
   * @return static  Object whose $str has been converted to an URL slug
   */
  public function slugify($replacement = '-', $language = 'de', $strToLower = true)
  {
    $slug = URLify::slug($this->str, $language, $replacement, $strToLower);

    return static::create($slug, $this->encoding);
  }

  /**
   * Remove css media-queries.
   *
   * @return static 
   */
  public function stripeCssMediaQueries()
  {
    $pattern = '#@media\\s+(?:only\\s)?(?:[\\s{\\(]|screen|all)\\s?[^{]+{.*}\\s*}\\s*#misU';

    return static::create(preg_replace($pattern, '', $this->str));
  }

  /**
   * Strip all whitespace characters. This includes tabs and newline characters,
   * as well as multibyte whitespace such as the thin space and ideographic space.
   *
   * @return Stringy
   */
  public function stripWhitespace()
  {
    return static::create(UTF8::strip_whitespace($this->str));
  }

  /**
   * Remove empty html-tag.
   *
   * e.g.: <tag></tag>
   *
   * @return static 
   */
  public function stripeEmptyHtmlTags()
  {
    $pattern = "/<[^\/>]*>(([\s]?)*|)<\/[^>]*>/i";

    return static::create(preg_replace($pattern, '', $this->str));
  }

  /**
   * Converts the string into an valid UTF-8 string.
   *
   * @return static 
   */
  public function utf8ify()
  {
    return static::create(UTF8::cleanup($this->str));
  }

  /**
   * escape html
   *
   * @return static 
   */
  public function escape()
  {
    $str = UTF8::htmlspecialchars(
        $this->str,
        ENT_QUOTES | ENT_SUBSTITUTE,
        $this->encoding
    );

    return static::create($str, $this->encoding);
  }

  /**
   * Create an extract from a text, so if the search-string was found, it will be centered in the output.
   *
   * @param string   $search
   * @param int|null $length
   * @param string   $ellipsis
   *
   * @return static 
   */
  public function extractText($search = '', $length = null, $ellipsis = '...')
  {
    // init
    $text = $this->str;

    if (empty($text)) {
      return static::create('', $this->encoding);
    }

    $trimChars = "\t\r\n -_()!~?=+/*\\,.:;\"'[]{}`&";

    if ($length === null) {
      $length = (int)round($this->length() / 2, 0);
    }

    if (empty($search)) {

      $stringLength = UTF8::strlen($text, $this->encoding);
      $end = ($length - 1) > $stringLength ? $stringLength : ($length - 1);
      $pos = min(UTF8::strpos($text, ' ', $end, 0, $this->encoding), UTF8::strpos($text, '.', $end));

      if ($pos) {
        return static::create(
            rtrim(
                UTF8::substr($text, 0, $pos, $this->encoding),
                $trimChars
            ) . $ellipsis,
            $this->encoding
        );
      }

      return static::create($text, $this->encoding);
    }

    $wordPos = UTF8::strpos(
        UTF8::strtolower($text),
        UTF8::strtolower($search, $this->encoding),
        null,
        $this->encoding
    );
    $halfSide = (int)($wordPos - $length / 2 + UTF8::strlen($search, $this->encoding) / 2);

    if ($halfSide > 0) {

      $halfText = UTF8::substr($text, 0, $halfSide, $this->encoding);
      $pos_start = max(UTF8::strrpos($halfText, ' ', 0), UTF8::strrpos($halfText, '.', 0));

      if (!$pos_start) {
        $pos_start = 0;
      }

    } else {
      $pos_start = 0;
    }

    if ($wordPos && $halfSide > 0) {
      $l = $pos_start + $length - 1;
      $realLength = UTF8::strlen($text, $this->encoding);

      if ($l > $realLength) {
        $l = $realLength;
      }

      $pos_end = min(
                     UTF8::strpos($text, ' ', $l, $this->encoding),
                     UTF8::strpos($text, '.', $l, $this->encoding)
                 ) - $pos_start;

      if (!$pos_end || $pos_end <= 0) {
        $extract = $ellipsis . ltrim(
                UTF8::substr(
                    $text,
                    $pos_start,
                    UTF8::strlen($text),
                    $this->encoding
                ),
                $trimChars
            );
      } else {
        $extract = $ellipsis . trim(
                UTF8::substr(
                    $text,
                    $pos_start,
                    $pos_end,
                    $this->encoding
                ),
                $trimChars
            ) . $ellipsis;
      }

    } else {

      $l = $length - 1;
      $trueLength = UTF8::strlen($text, $this->encoding);

      if ($l > $trueLength) {
        $l = $trueLength;
      }

      $pos_end = min(
          UTF8::strpos($text, ' ', $l, $this->encoding),
          UTF8::strpos($text, '.', $l, $this->encoding)
      );

      if ($pos_end) {
        $extract = rtrim(
                       UTF8::substr($text, 0, $pos_end, $this->encoding),
                       $trimChars
                   ) . $ellipsis;
      } else {
        $extract = $text;
      }
    }

    return static::create($extract, $this->encoding);
  }


  /**
   * remove xss from html
   *
   * @return static 
   */
  public function removeXss()
  {
    static $antiXss = null;

    if ($antiXss === null) {
      $antiXss = new AntiXSS();
    }

    $str = $antiXss->xss_clean($this->str);

    return static::create($str, $this->encoding);
  }

  /**
   * remove html-break [br | \r\n | \r | \n | ...]
   *
   * @param string $replacement
   *
   * @return static 
   */
  public function removeHtmlBreak($replacement = '')
  {
    $str = preg_replace('#/\r\n|\r|\n|<br.*/?>#isU', $replacement, $this->str);

    return static::create($str, $this->encoding);
  }

  /**
   * remove html
   *
   * @param $allowableTags
   *
   * @return static 
   */
  public function removeHtml($allowableTags = null)
  {
    $str = strip_tags($this->str, $allowableTags);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns the substring beginning at $start, and up to, but not including
   * the index specified by $end. If $end is omitted, the function extracts
   * the remaining string. If $end is negative, it is computed from the end
   * of the string.
   *
   * @param  int $start Initial index from which to begin extraction
   * @param  int $end   Optional index at which to end extraction
   *
   * @return static  Object with its $str being the extracted substring
   */
  public function slice($start, $end = null)
  {
    if ($end === null) {
      $length = $this->length();
    } elseif ($end >= 0 && $end <= $start) {
      return static::create('', $this->encoding);
    } elseif ($end < 0) {
      $length = $this->length() + $end - $start;
    } else {
      $length = $end - $start;
    }

    $str = UTF8::substr($this->str, $start, $length, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Splits the string with the provided regular expression, returning an
   * array of Stringy objects. An optional integer $limit will truncate the
   * results.
   *
   * @param  string $pattern The regex with which to split the string
   * @param  int    $limit   Optional maximum number of results to return
   *
   * @return static [] An array of Stringy objects
   */
  public function split($pattern, $limit = null)
  {
    if ($limit === 0) {
      return array();
    }

    // this->split errors when supplied an empty pattern in < PHP 5.4.13
    // and current versions of HHVM (3.8 and below)
    if ($pattern === '') {
      return array(static::create($this->str, $this->encoding));
    }

    // this->split returns the remaining unsplit string in the last index when
    // supplying a limit
    if ($limit > 0) {
      $limit += 1;
    } else {
      $limit = -1;
    }

    $array = preg_split('/' . preg_quote($pattern, '/') . '/u', $this->str, $limit);

    if ($limit > 0 && count($array) === $limit) {
      array_pop($array);
    }

    /** @noinspection CallableInLoopTerminationConditionInspection */
    /** @noinspection ForeachInvariantsInspection */
    for ($i = 0; $i < count($array); $i++) {
      $array[$i] = static::create($array[$i], $this->encoding);
    }

    return $array;
  }

  /**
   * Surrounds $str with the given substring.
   *
   * @param  string $substring The substring to add to both sides
   *
   * @return static  Object whose $str had the substring both prepended and
   *                 appended
   */
  public function surround($substring)
  {
    $str = implode('', array($substring, $this->str, $substring));

    return static::create($str, $this->encoding);
  }

  /**
   * Returns a case swapped version of the string.
   *
   * @return static  Object whose $str has each character's case swapped
   */
  public function swapCase()
  {
    $stringy = static::create($this->str, $this->encoding);

    $stringy->str = UTF8::swapCase($stringy->str, $stringy->encoding);

    return $stringy;
  }

  /**
   * Returns a string with smart quotes, ellipsis characters, and dashes from
   * Windows-1252 (commonly used in Word documents) replaced by their ASCII
   * equivalents.
   *
   * @return static  Object whose $str has those characters removed
   */
  public function tidy()
  {
    $str = UTF8::normalize_msword($this->str);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns a trimmed string with the first letter of each word capitalized.
   * Also accepts an array, $ignore, allowing you to list words not to be
   * capitalized.
   *
   * @param  array $ignore An array of words not to capitalize
   *
   * @return static  Object with a titleized $str
   */
  public function titleize($ignore = null)
  {
    $stringy = static::create($this->trim(), $this->encoding);
    $encoding = $this->encoding;

    $stringy->str = preg_replace_callback(
        '/([\S]+)/u',
        function ($match) use ($encoding, $ignore) {
          if ($ignore && in_array($match[0], $ignore, true)) {
            return $match[0];
          }

          $stringy = new Stringy($match[0], $encoding);

          return (string)$stringy->toLowerCase()->upperCaseFirst();
        },
        $stringy->str
    );

    return $stringy;
  }

  /**
   * Converts all characters in the string to lowercase. An alias for PHP's
   * UTF8::strtolower().
   *
   * @return static  Object with all characters of $str being lowercase
   */
  public function toLowerCase()
  {
    $str = UTF8::strtolower($this->str, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns true if the string is base64 encoded, false otherwise.
   *
   * @return bool Whether or not $str is base64 encoded
   */
  public function isBase64()
  {
    return UTF8::is_base64($this->str);
  }

  /**
   * Returns an ASCII version of the string. A set of non-ASCII characters are
   * replaced with their closest ASCII counterparts, and the rest are removed
   * unless instructed otherwise.
   *
   * @param $strict [optional] <p>Use "transliterator_transliterate()" from PHP-Intl | WARNING: bad performance</p>
   *
   * @return static  Object whose $str contains only ASCII characters
   */
  public function toAscii($strict = false)
  {
    $str = UTF8::to_ascii($this->str, '?', $strict);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns a boolean representation of the given logical string value.
   * For example, 'true', '1', 'on' and 'yes' will return true. 'false', '0',
   * 'off', and 'no' will return false. In all instances, case is ignored.
   * For other numeric strings, their sign will determine the return value.
   * In addition, blank strings consisting of only whitespace will return
   * false. For all other strings, the return value is a result of a
   * boolean cast.
   *
   * @return bool A boolean value for the string
   */
  public function toBoolean()
  {
    $key = $this->toLowerCase()->str;
    $map = array(
        'true'  => true,
        '1'     => true,
        'on'    => true,
        'yes'   => true,
        'false' => false,
        '0'     => false,
        'off'   => false,
        'no'    => false,
    );

    if (array_key_exists($key, $map)) {
      return $map[$key];
    }

    if (is_numeric($this->str)) {
      return ((int)$this->str > 0);
    }

    return (bool)$this->regexReplace('[[:space:]]', '')->str;
  }

  /**
   * Return Stringy object as string, but you can also use (string) for automatically casting the object into a string.
   *
   * @return string
   */
  public function toString()
  {
    return (string)$this->str;
  }

  /**
   * Converts each tab in the string to some number of spaces, as defined by
   * $tabLength. By default, each tab is converted to 4 consecutive spaces.
   *
   * @param  int $tabLength Number of spaces to replace each tab with
   *
   * @return static  Object whose $str has had tabs switched to spaces
   */
  public function toSpaces($tabLength = 4)
  {
    $spaces = UTF8::str_repeat(' ', $tabLength);
    $str = UTF8::str_replace("\t", $spaces, $this->str);

    return static::create($str, $this->encoding);
  }

  /**
   * Converts each occurrence of some consecutive number of spaces, as
   * defined by $tabLength, to a tab. By default, each 4 consecutive spaces
   * are converted to a tab.
   *
   * @param  int $tabLength Number of spaces to replace with a tab
   *
   * @return static  Object whose $str has had spaces switched to tabs
   */
  public function toTabs($tabLength = 4)
  {
    $spaces = UTF8::str_repeat(' ', $tabLength);
    $str = UTF8::str_replace($spaces, "\t", $this->str);

    return static::create($str, $this->encoding);
  }

  /**
   * Converts the first character of each word in the string to uppercase.
   *
   * @return static  Object with all characters of $str being title-cased
   */
  public function toTitleCase()
  {
    // "mb_convert_case()" used a polyfill from the "UTF8"-Class
    $str = mb_convert_case($this->str, MB_CASE_TITLE, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Converts all characters in the string to uppercase. An alias for PHP's
   * UTF8::strtoupper().
   *
   * @return static  Object with all characters of $str being uppercase
   */
  public function toUpperCase()
  {
    $str = UTF8::strtoupper($this->str, $this->encoding);

    return static::create($str, $this->encoding);
  }

  /**
   * Returns a string with whitespace removed from the start of the string.
   * Supports the removal of unicode whitespace. Accepts an optional
   * string of characters to strip instead of the defaults.
   *
   * @param  string $chars Optional string of characters to strip
   *
   * @return static  Object with a trimmed $str
   */
  public function trimLeft($chars = null)
  {
    if (!$chars) {
      $chars = '[:space:]';
    } else {
      $chars = preg_quote($chars, '/');
    }

    return $this->regexReplace("^[$chars]+", '');
  }

  /**
   * Returns a string with whitespace removed from the end of the string.
   * Supports the removal of unicode whitespace. Accepts an optional
   * string of characters to strip instead of the defaults.
   *
   * @param  string $chars Optional string of characters to strip
   *
   * @return static  Object with a trimmed $str
   */
  public function trimRight($chars = null)
  {
    if (!$chars) {
      $chars = '[:space:]';
    } else {
      $chars = preg_quote($chars, '/');
    }

    return $this->regexReplace("[$chars]+\$", '');
  }

  /**
   * Truncates the string to a given length. If $substring is provided, and
   * truncating occurs, the string is further truncated so that the substring
   * may be appended without exceeding the desired length.
   *
   * @param  int    $length    Desired length of the truncated string
   * @param  string $substring The substring to append if it can fit
   *
   * @return static  Object with the resulting $str after truncating
   */
  public function truncate($length, $substring = '')
  {
    $stringy = static::create($this->str, $this->encoding);
    if ($length >= $stringy->length()) {
      return $stringy;
    }

    // Need to further trim the string so we can append the substring
    $substringLength = UTF8::strlen($substring, $stringy->encoding);
    $length -= $substringLength;

    $truncated = UTF8::substr($stringy->str, 0, $length, $stringy->encoding);
    $stringy->str = $truncated . $substring;

    return $stringy;
  }

  /**
   * Returns a lowercase and trimmed string separated by underscores.
   * Underscores are inserted before uppercase characters (with the exception
   * of the first character of the string), and in place of spaces as well as
   * dashes.
   *
   * @return static  Object with an underscored $str
   */
  public function underscored()
  {
    return $this->delimit('_');
  }

  /**
   * Returns an UpperCamelCase version of the supplied string. It trims
   * surrounding spaces, capitalizes letters following digits, spaces, dashes
   * and underscores, and removes spaces, dashes, underscores.
   *
   * @return static  Object with $str in UpperCamelCase
   */
  public function upperCamelize()
  {
    return $this->camelize()->upperCaseFirst();
  }

  /**
   * Returns a camelCase version of the string. Trims surrounding spaces,
   * capitalizes letters following digits, spaces, dashes and underscores,
   * and removes spaces, dashes, as well as underscores.
   *
   * @return static  Object with $str in camelCase
   */
  public function camelize()
  {
    $encoding = $this->encoding;
    $stringy = $this->trim()->lowerCaseFirst();
    $stringy->str = preg_replace('/^[-_]+/', '', $stringy->str);

    $stringy->str = preg_replace_callback(
        '/[-_\s]+(.)?/u',
        function ($match) use ($encoding) {
          if (isset($match[1])) {
            return UTF8::strtoupper($match[1], $encoding);
          }

          return '';
        },
        $stringy->str
    );

    $stringy->str = preg_replace_callback(
        '/[\d]+(.)?/u',
        function ($match) use ($encoding) {
          return UTF8::strtoupper($match[0], $encoding);
        },
        $stringy->str
    );

    return $stringy;
  }

  /**
   * Convert a string to e.g.: "snake_case"
   *
   * @return static  Object with $str in snake_case
   */
  public function snakeize()
  {
    $str = $this->str;

    $encoding = $this->encoding;
    $str = UTF8::normalize_whitespace($str);
    $str = str_replace('-', '_', $str);

    $str = preg_replace_callback(
        '/([\d|A-Z])/u',
        function ($matches) use ($encoding) {
          $match = $matches[1];
          $matchInt = (int)$match;

          if ("$matchInt" == $match) {
            return '_' . $match . '_';
          }

          return '_' . UTF8::strtolower($match, $encoding);
        },
        $str
    );

    $str = preg_replace(
        array(

            '/\s+/',      // convert spaces to "_"
            '/^\s+|\s+$/',  // trim leading & trailing spaces
            '/_+/',         // remove double "_"
        ),
        array(
            '_',
            '',
            '_',
        ),
        $str
    );

    $str = UTF8::trim($str, '_'); // trim leading & trailing "_"
    $str = UTF8::trim($str); // trim leading & trailing whitespace

    return static::create($str, $this->encoding);
  }

  /**
   * Converts the first character of the string to lower case.
   *
   * @return static  Object with the first character of $str being lower case
   */
  public function lowerCaseFirst()
  {
    $first = UTF8::substr($this->str, 0, 1, $this->encoding);
    $rest = UTF8::substr($this->str, 1, $this->length() - 1, $this->encoding);

    $str = UTF8::strtolower($first, $this->encoding) . $rest;

    return static::create($str, $this->encoding);
  }

  /**
   * Shorten the string after $length, but also after the next word.
   *
   * @param int    $length
   * @param string $strAddOn
   *
   * @return static 
   */
  public function shortenAfterWord($length, $strAddOn = '...')
  {
    $string = UTF8::str_limit_after_word($this->str, $length, $strAddOn);

    return static::create($string);
  }

  /**
   * Line-Wrap the string after $limit, but also after the next word.
   *
   * @param int $limit
   *
   * @return static 
   */
  public function lineWrapAfterWord($limit)
  {
    $strings = preg_split('/\\r\\n|\\r|\\n/', $this->str);

    $string = '';
    foreach ($strings as $value) {
      $string .= wordwrap($value, $limit);
      $string .= "\n";
    }

    return static::create($string);
  }

  /**
   * Gets the substring after the first occurrence of a separator.
   * If no match is found returns new empty Stringy object.
   *
   * @param string $separator
   *
   * @return static 
   */
  public function afterFirst($separator)
  {
    if (($offset = $this->indexOf($separator)) === false) {
      return static::create('');
    }

    return static::create(
        UTF8::substr(
            $this->str,
            $offset + UTF8::strlen($separator, $this->encoding),
            null,
            $this->encoding
        ),
        $this->encoding
    );
  }

  /**
   * Gets the substring after the last occurrence of a separator.
   * If no match is found returns new empty Stringy object.
   *
   * @param string $separator
   *
   * @return static 
   */
  public function afterLast($separator)
  {
    $offset = $this->indexOfLast($separator);
    if ($offset === false) {
      return static::create('', $this->encoding);
    }

    return static::create(
        UTF8::substr(
            $this->str,
            $offset + UTF8::strlen($separator, $this->encoding),
            null,
            $this->encoding
        ),
        $this->encoding
    );
  }

  /**
   * Gets the substring before the first occurrence of a separator.
   * If no match is found returns new empty Stringy object.
   *
   * @param string $separator
   *
   * @return static 
   */
  public function beforeFirst($separator)
  {
    $offset = $this->indexOf($separator);
    if ($offset === false) {
      return static::create('', $this->encoding);
    }

    return static::create(
        UTF8::substr(
            $this->str,
            0,
            $offset,
            $this->encoding
        ),
        $this->encoding
    );
  }

  /**
   * Gets the substring before the last occurrence of a separator.
   * If no match is found returns new empty Stringy object.
   *
   * @param string $separator
   *
   * @return static 
   */
  public function beforeLast($separator)
  {
    $offset = $this->indexOfLast($separator);
    if ($offset === false) {
      return static::create('', $this->encoding);
    }

    return static::create(
        UTF8::substr(
            $this->str,
            0,
            $offset,
            $this->encoding
        ),
        $this->encoding
    );
  }

  /**
   * Returns the string with the first letter of each word capitalized,
   * except for when the word is a name which shouldn't be capitalized.
   *
   * @return static  Object with $str capitalized
   */
  public function capitalizePersonalName()
  {
    $stringy = $this->collapseWhitespace();
    $stringy->str = $this->capitalizePersonalNameByDelimiter($stringy->str, ' ');
    $stringy->str = $this->capitalizePersonalNameByDelimiter($stringy->str, '-');

    return static::create($stringy, $this->encoding);
  }

  /**
   * @param string $word
   *
   * @return string
   */
  protected function capitalizeWord($word)
  {
    $encoding = $this->encoding;

    $firstCharacter = UTF8::substr($word, 0, 1, $encoding);
    $restOfWord = UTF8::substr($word, 1, null, $encoding);
    $firstCharacterUppercased = UTF8::strtoupper($firstCharacter, $encoding);

    return new static($firstCharacterUppercased . $restOfWord, $encoding);
  }

  /**
   * Personal names such as "Marcus Aurelius" are sometimes typed incorrectly using lowercase ("marcus aurelius").
   *
   * @param string $names
   * @param string $delimiter
   *
   * @return string
   */
  protected function capitalizePersonalNameByDelimiter($names, $delimiter)
  {
    // init
    $names = explode((string)$delimiter, (string)$names);
    $encoding = $this->encoding;

    $specialCases = array(
        'names'    => array(
            'ab',
            'af',
            'al',
            'and',
            'ap',
            'bint',
            'binte',
            'da',
            'de',
            'del',
            'den',
            'der',
            'di',
            'dit',
            'ibn',
            'la',
            'mac',
            'nic',
            'of',
            'ter',
            'the',
            'und',
            'van',
            'von',
            'y',
            'zu',
        ),
        'prefixes' => array(
            'al-',
            "d'",
            'ff',
            "l'",
            'mac',
            'mc',
            'nic',
        ),
    );

    foreach ($names as &$name) {
      if (in_array($name, $specialCases['names'], true)) {
        continue;
      }

      $continue = false;

      if ($delimiter == '-') {
        foreach ($specialCases['names'] as $beginning) {
          if (UTF8::strpos($name, $beginning, null, $encoding) === 0) {
            $continue = true;
          }
        }
      }

      foreach ($specialCases['prefixes'] as $beginning) {
        if (UTF8::strpos($name, $beginning, null, $encoding) === 0) {
          $continue = true;
        }
      }

      if ($continue) {
        continue;
      }

      $name = $this->capitalizeWord($name);
    }

    return new static(implode($delimiter, $names), $encoding);
  }
}
