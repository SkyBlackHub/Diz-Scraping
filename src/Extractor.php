<?php

namespace Diz\Scraping;

use Diz\Scraping\Exceptions\ExtractorException;

class Extractor
{
	private string $url;
	private string $content;
	private string $delimiter = '#';
	private ?string $last_regexp = null;

	public function __construct(string $content = '', string $url = '')
	{
		$this->content = $content;
		$this->url = $url;
	}

	public function getURL(): string
	{
		return $this->url;
	}

	public function setURL(string $url): self
	{
		$this->url = $url;
		return $this;
	}

	public function getContent(): string
	{
		return $this->content;
	}

	public function setContent(string $content, ?string $url = null): self
	{
		$this->content = $content;
		if ($url !== null) {
			$this->setURL($url);
		}
		return $this;
	}

	public function getDelimiter(): string
	{
		return $this->delimiter;
	}

	public function setDelimiter(string $delimiter): self
	{
		if (strlen($delimiter) == 1) {
			$this->delimiter = $delimiter;
		}
		return $this;
	}

	public function getLastExpression(): ?string
	{
		return $this->last_regexp;
	}

	private function sieve(array $matches, $group = null)
	{
		if ($group !== null) {
			return $matches[$group] ?? null;
		}
		if (count($matches) == 1) {
			return $matches[0];
		}
		if (count($matches) == 2) {
			return $matches[1];
		}
		array_shift($matches);
		return $matches;
	}

	protected function prepareExpression(string $regexp, ?string $flags = null): string
	{
		return $this->delimiter . str_replace($this->delimiter, '\\' . $this->delimiter, $regexp) . $this->delimiter . $flags;
	}

	protected function performMatch(string $regexp, &$matches, bool $global = false, ?string $flags = null): string
	{
		$regexp = $this->last_regexp = $this->prepareExpression($regexp, $flags);
		if ($global) {
			preg_match_all($regexp, $this->content, $matches, PREG_SET_ORDER);
		} else {
			preg_match($regexp, $this->content, $matches);
		}
		return $regexp;
	}

	/**
	 * Extract data using the specified regular expression
	 * @param string $regexp The regular expression without delimiter
	 * @param int|string|null $group The name or index of the group, if specified, the function will return only that capture group
	 * @param bool $strict On mismatch, if the strict is true, an exception will be thrown, otherwise, the function will return null
	 * @param string|null $flags The regular expression additional flags
	 * @throws ExtractorException
	 */
	public function extract(string $regexp, $group = null, bool $strict = true, ?string $flags = null)
	{
		$this->performMatch($regexp, $matches, false, $flags);
		if ($matches == false) {
			if ($strict) {
				$this->extractionFailed();
			} else {
				return null;
			}
		}
		return $this->sieve($matches, $group);
	}

	/**
	 * Extract all data occurrences using the specified regular expression
	 * @param string $regexp The regular expression without delimiter
	 * @param int|string|null $group The name or index of the group, if specified, the function will return only that capture group
	 * @param bool $strict On mismatch, if the strict is true, an exception will be thrown, otherwise, the function will return an empty array
	 * @param string|null $flags The regular expression additional flags
	 * @throws ExtractorException
	 */
	public function extractAll(string $regexp, $group = null, bool $strict = false, ?string $flags = null): array
	{
		$this->performMatch($regexp, $matches, true, $flags);
		if ($matches == false) {
			if ($strict) {
				$this->extractionFailed();
			} else {
				return [];
			}
		}
		$result = [];
		foreach ($matches as $match) {
			$result[] = $this->sieve($match, $group);
		}
		return $result;
	}

	/**
	 * Check the specified regular expression for a match
	 * @param string $regexp The regular expression without delimiter
	 * @param string|null $flags The regular expression additional flags
	 */
	public function check(string $regexp, ?string $flags = null): bool
	{
		$this->performMatch($regexp, $matches, false, $flags);
		return $matches != false;
	}

	/**
	 * Extract and compare data against some value using the specified regular expression
	 * @param string $regexp The regular expression without delimiter
	 * @param string $expected_value Expected value to compare
	 * @param int|string|null $group The name or index of the group, if specified, the function will return only that capture group
	 * @param bool $strict On mismatch, if the strict is true, an exception will be thrown, otherwise, the function will return null
	 * @param string|null $flags The regular expression additional flags
	 * @throws ExtractorException
	 */
	public function equal(string $regexp, string $expected_value, $group = null, bool $case_sensitive = false, bool $strict = true, ?string $flags = null): bool
	{
		$exists = $this->extract($regexp, $group, $strict, $flags);
		return ($case_sensitive ? strcmp($exists, $expected_value) : strcasecmp($exists, $expected_value)) == 0;
	}

	/**
	 * Clean all unnecessary data using the specified regular expression(s)
	 * @param string|array $regexp The one or multiple regular expressions without delimiter
	 * @param string|null $flags The regular expression additional flags
	 */
	public function cleanOut($regexp, ?string $flags = null): string
	{
		$result = $this->content;
		foreach ((array) $regexp as $sub_regexp) {
			$result = preg_replace($this->last_regexp = $this->prepareExpression($sub_regexp, $flags), '', $result);
		}
		return $result;
	}

	/**
	 * @throws ExtractorException
	 */
	protected function extractionFailed(): void
	{
		throw new ExtractorException(null, $this->last_regexp, $this->url, $this->content);
	}
}