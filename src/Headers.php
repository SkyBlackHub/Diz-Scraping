<?php

namespace Diz\Scraping;

use Diz\Toolkit\Kits\FilterKit;
use Diz\Toolkit\Kits\TextKit;

class Headers implements \IteratorAggregate, \Countable
{
	private array $headers = [];
	private bool $auto_correct_names = false;

	public function __construct(?array $headers = null)
	{
		if ($headers) {
			$this->set($headers);
		}
	}

	public function getIterator(): \ArrayIterator
	{
		return new \ArrayIterator($this->headers);
	}

	public function count(): int
	{
		return count($this->headers);
	}

	public function isAutoCorrectNames(): bool
	{
		return $this->auto_correct_names;
	}

	public function setAutoCorrectNames(bool $auto_correct_names): self
	{
		$this->auto_correct_names = $auto_correct_names;
		return $this;
	}

	public function getAll(): array
	{
		return $this->headers;
	}

	public function getNames(): array
	{
		return array_keys($this->headers);
	}

	public function has(string $name): bool
	{
		$name = $this->normalizeName($name);
		return $name && isset($this->headers[$name]);
	}

	public function get(string $name): ?array
	{
		$name = $this->normalizeName($name);
		return $name ? ($this->headers[$name] ?? null) : null;
	}

	public function getAt(string $name, int $index): ?string
	{
		$name = $this->normalizeName($name);
		return $name ? ($this->headers[$name][$index] ?? null) : null;
	}

	public function getFirst(string $name): ?string
	{
		return $this->getAt($name, 0);
	}

	public function getLast(string $name): ?string
	{
		$name = $this->normalizeName($name);
		if ($name == null) {
			return null;
		}
		$headers = $this->headers[$name] ?: null;
		return $headers ? ($headers[count($headers) - 1] ?? null) : null;
	}

	public function contains(string $name, string $value): bool
	{
		$name = $this->normalizeName($name);
		return $name && in_array(trim($value), $this->headers[$name] ?? []);
	}

	public function getPlain(bool $capitalize = false): array
	{
		$result = [];
		foreach ($this->headers as $name => $values) {
			if ($capitalize) {
				$name = ucwords($name, '-');
			}
			foreach ($values as $value) {
				$result[] = $name . ': ' . $value;
			}
		}
		return $result;
	}

	public function set(array $headers): self
	{
		$this->headers = [];
		foreach ($headers as $name => $value) {
			$this->replace($name, $value);
		}
		return $this;
	}

	public function add(string $name, $value): self
	{
		$name = $this->normalizeName($name);
		if ($name) {
			$value = FilterKit::toTrimmedArray((array) $value, true);
			if ($value) {
				if (isset($this->headers[$name])) {
					$this->headers[$name] = array_merge($this->headers[$name], $value);
				} else {
					$this->headers[$name] = $value;
				}
			}
		}
		return $this;
	}

	public function addPlain(string $plain_header): self
	{
		$header = explode(':', $plain_header, 2);
		if (count($header) == 2) {
			$this->add($header[0], $header[1]);
		}
		return $this;
	}

	public function replace(string $name, $value): self
	{
		$name = $this->normalizeName($name);
		if ($name) {
			$this->headers[$name] = FilterKit::toTrimmedArray((array) $value, true);
		}
		return $this;
	}

	public function clear(): self
	{
		$this->headers = [];
		return $this;
	}

	public function remove(string $name): self
	{
		$name = $this->normalizeName($name);
		if ($name) {
			unset($this->headers[$name]);
		}
		return $this;
	}

	private function normalizeName(string $name): ?string
	{
		$name = TextKit::clarifyLower($name);
		if ($name) {
			return $this->auto_correct_names ? substr_replace([' ', '_'], '-', $name) : $name;
		}
		return null;
	}

	public function getContentLength(): ?int
	{
		$result =	$this->getLast('content-length');
		return $result !== null ? (int) $result : null;
	}

	public function setContentType(string $type): self
	{
		$this->replace('content-type', $type);
		return $this;
	}

	public function getContentType(): ?string
	{
		return $this->getFirst('content-type');
	}

	public function getLocation(): ?string
	{
		return $this->getLast('location');
	}

	public function getReceivedCookies(): array
	{
		return $this->get('set-cookie');
	}

	public function setXHR(bool $xhr): self
	{
		$this->replace('x-requested-with', $xhr ? 'XMLHttpRequest' : null);
		return $this;
	}

	public function isXHR(): bool
	{
		return $this->contains('x-requested-with', 'XMLHttpRequest');
	}
}