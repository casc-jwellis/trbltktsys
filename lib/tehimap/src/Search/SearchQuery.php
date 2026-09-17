<?php

declare(strict_types=1);

namespace Tehimap\Imap\Search;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * A fluent, immutable-style builder for an IMAP SEARCH command's criteria string (RFC 3501
 * section 6.4.4), meant to be used as the argument to a UID SEARCH command.
 *
 * Every method returns a NEW SearchQuery with the criterion appended - the receiver is never
 * mutated. Criteria accumulated on one instance are ANDed together (IMAP has no explicit AND
 * keyword; it is expressed by simply listing search keys one after another).
 *
 * This class has no dependency on anything outside Tehimap\Imap\Search: its quoting logic is
 * conceptually similar to (but deliberately separate from) the protocol layer's, since the two
 * are being built in parallel.
 *
 * Example:
 *   (new SearchQuery())->unseen()->from('boss@example.com')->build();
 *   // 'UNSEEN FROM "boss@example.com"'
 */
final class SearchQuery
{
    /**
     * @param string[] $criteria Already-formatted IMAP SEARCH key(s), one per accumulated
     *   criterion, ANDed together by build().
     */
    public function __construct(private readonly array $criteria = [])
    {
    }

    public function unseen(): self
    {
        return $this->with('UNSEEN');
    }

    public function seen(): self
    {
        return $this->with('SEEN');
    }

    public function answered(): self
    {
        return $this->with('ANSWERED');
    }

    public function unanswered(): self
    {
        return $this->with('UNANSWERED');
    }

    public function flagged(): self
    {
        return $this->with('FLAGGED');
    }

    public function unflagged(): self
    {
        return $this->with('UNFLAGGED');
    }

    public function deleted(): self
    {
        return $this->with('DELETED');
    }

    public function undeleted(): self
    {
        return $this->with('UNDELETED');
    }

    public function all(): self
    {
        return $this->with('ALL');
    }

    public function from(string $address): self
    {
        return $this->with('FROM ' . self::quote($address));
    }

    public function to(string $address): self
    {
        return $this->with('TO ' . self::quote($address));
    }

    public function subject(string $text): self
    {
        return $this->with('SUBJECT ' . self::quote($text));
    }

    public function body(string $text): self
    {
        return $this->with('BODY ' . self::quote($text));
    }

    public function text(string $text): self
    {
        return $this->with('TEXT ' . self::quote($text));
    }

    public function header(string $fieldName, string $value): self
    {
        return $this->with('HEADER ' . self::quote($fieldName) . ' ' . self::quote($value));
    }

    /**
     * @param DateTimeInterface $date Formatted per RFC 3501's SEARCH date syntax: a two-letter
     *   day, three-letter month abbreviation and four-digit year (e.g. "1-Jan-2024").
     */
    public function since(DateTimeInterface $date): self
    {
        return $this->with('SINCE ' . self::formatDate($date));
    }

    public function before(DateTimeInterface $date): self
    {
        return $this->with('BEFORE ' . self::formatDate($date));
    }

    public function on(DateTimeInterface $date): self
    {
        return $this->with('ON ' . self::formatDate($date));
    }

    /**
     * @param string $sequenceSet An already-formatted IMAP sequence set, e.g. "4821:*" or "1,3,5".
     */
    public function uid(string $sequenceSet): self
    {
        return $this->with('UID ' . $sequenceSet);
    }

    /**
     * ORs the criteria accumulated so far on $this with $other's, using RFC 3501's OR search
     * key, which takes exactly two search-key arguments. Nest calls to combine more than two
     * alternatives.
     *
     * Either side's accumulated criteria are parenthesized into a single search key when there
     * is more than one, since each of OR's two arguments must itself be one search key; a side
     * with no criteria contributes ALL, matching build()'s own empty-criteria behavior.
     */
    public function orWhere(self $other): self
    {
        $left = self::asSingleKey($this->criteria);
        $right = self::asSingleKey($other->criteria);

        return new self(["OR {$left} {$right}"]);
    }

    /**
     * Joins every accumulated criterion with a single space. IMAP ANDs criteria by plain
     * concatenation - there is no explicit AND keyword. Returns the ALL keyword if nothing was
     * added, since IMAP does not allow an empty criteria list.
     */
    public function build(): string
    {
        if ($this->criteria === []) {
            return 'ALL';
        }

        return implode(' ', $this->criteria);
    }

    private function with(string $criterion): self
    {
        return new self([...$this->criteria, $criterion]);
    }

    /**
     * @param string[] $criteria
     */
    private static function asSingleKey(array $criteria): string
    {
        if ($criteria === []) {
            return 'ALL';
        }

        if (count($criteria) === 1) {
            return $criteria[0];
        }

        return '(' . implode(' ', $criteria) . ')';
    }

    private static function formatDate(DateTimeInterface $date): string
    {
        return $date->format('j-M-Y');
    }

    /**
     * Safely quotes a free-text SEARCH argument: wraps it in double quotes, first escaping any
     * backslash or double-quote character it contains (backslashes must be escaped before
     * quotes, or the quotes' own escaping backslashes would themselves get doubled).
     *
     * @throws InvalidArgumentException if $value contains a carriage return or line feed, since
     *   a quoted string cannot contain either and this class has no literal-based fallback.
     */
    private static function quote(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new InvalidArgumentException(
                'IMAP SEARCH quoted strings cannot contain a carriage return or line feed.'
            );
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $escaped . '"';
    }
}
