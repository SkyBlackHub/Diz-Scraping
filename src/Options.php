<?php

namespace Diz\Scraping;

use Diz\Scraping\Enums\DataType;
use Diz\Scraping\Traits\HeadersAwareTrait;
use Diz\Toolkit\Kits\FilterKit;

class Options
{
	use HeadersAwareTrait;

	private array $options;

	public function __construct(array $options = [])
	{
		$this->options = $options;
	}

	public function getOptions(): array
	{
		return $this->options;
	}

	public function setOptions(array $options): self
	{
		$this->options = $options;
		return $this;
	}

	public function getOption(int $option)
	{
		return $this->options[$option] ?? null;
	}

	public function setOption(int $option, $value): self
	{
		$this->options[$option] = $value;
		return $this;
	}

	public function removeOption(int ...$option): self
	{
		foreach ($option as $value) {
			unset($this->options[$value]);
		}
		return $this;
	}

	public function clearOptions(): self
	{
		$this->options = [];
		return $this;
	}

	public function getCustomMethod(): ?string
	{
		return $this->getOption(CURLOPT_CUSTOMREQUEST);
	}

	public function setCustomMethod(?string $method): self
	{
		if ($method === null) {
			$this->removeOption(CURLOPT_CUSTOMREQUEST);
		} else {
			$this->setOption(CURLOPT_CUSTOMREQUEST, $method);
		}
		return $this;
	}

	protected static function flatData(array $data, ?string $prefix = null): array
	{
		$result = [];
		foreach ($data as $key => $value) {
			$final_key = $prefix ? "{$prefix}[{$key}]" : $key;
			if (is_array($value)) {
				$result += static::flatData($value, $final_key);
			} else {
				$result[$final_key] = $value;
			}
		}
		return $result;
	}

	/**
	 * Explicitly set data and type
	 * @param $data
	 * @param string|null $type
	 * @return static
	 */
	protected function setDataExplicitly($data, ?string $type = null): self
	{
		if ($type !== null) {
			$this->getHeaders()->setContentType($type);
		}
		return $this->setOption(CURLOPT_POSTFIELDS, $data);
	}

	public function setPlainData(string $data): self
	{
		return $this->setDataExplicitly($data, DataType::PLAIN);
	}

	public function setQueryData(array $data, bool $keep_numeric_indexes = false): self
	{
		$data = http_build_query($data);
		if ($keep_numeric_indexes == false) {
			$data = preg_replace('/%5B\d+%5D/', '%5B%5D', $data);
		}
		return $this->setDataExplicitly($data, DataType::QUERY);
	}

	public function setFormData(array $data): self
	{
		return $this->setDataExplicitly(static::flatData($data), DataType::FORM);
	}

	public function setJSONData($data): self
	{
		return $this->setDataExplicitly(json_encode($data), DataType::JSON);
	}

	/**
	 * Explicitly set data and type
	 * @param $data
	 * @param string|null $type
	 * @return static
	 */
	public function setData($data, ?string $type = null): self
	{
		switch ($type) {
			case DataType::PLAIN:
				return FilterKit::canBeString($data) ? $this->setPlainData($data) : $this;

			case DataType::FORM:
				return is_array($data) ? $this->setFormData($data) : $this;

			case DataType::JSON:
				return $this->setJSONData($data);

			case DataType::QUERY:
				return is_array($data) ? $this->setQueryData($data) : $this;
		}

		if ($type == null) {
			if (is_array($data)) {
				return $this->setQueryData($data);
			}
			if (FilterKit::canBeString($data)) {
				return $this->setPlainData($data);
			}
			return $this;
		}
		return FilterKit::canBeString($data) ? $this->setDataExplicitly((string) $data, $type) : $this;
	}

	public function removeData(): self
	{
		return $this->removeOption(CURLOPT_POSTFIELDS);
	}

	public function getReferer(): ?string
	{
		return $this->getOption(CURLOPT_REFERER);
	}

	public function setReferer(?string $referer): self
	{
		if ($referer === null) {
			return $this->removeOption(CURLOPT_REFERER);
		}
		return $this->setOption(CURLOPT_REFERER, trim($referer));
	}

	public function getUserAgent(): ?string
	{
		return $this->getOption(CURLOPT_USERAGENT);
	}

	public function setUserAgent(string $user_agent): self
	{
		return $this->setOption(CURLOPT_USERAGENT, trim($user_agent));
	}

	/**
	 * @return resource|null
	 */
	public function getFile()
	{
		return $this->getOption(CURLOPT_FILE);
	}

	/**
	 * @param resource|null $file
	 */
	public function setFile($file): self
	{
		if ($file === null) {
			return $this->removeOption(CURLOPT_FILE);
		}
		return is_resource($file) ? $this->setOption(CURLOPT_FILE, $file) : $this;
	}

	public function getDirectURL(): ?string
	{
		return $this->getOption(CURLOPT_URL);
	}

	public function setDirectURL(?string $url): self
	{
		if ($url === null) {
			return $this->removeOption(CURLOPT_URL);
		}
		return $this->setOption(CURLOPT_URL, $url);
	}

	public function getAuthority(): ?string
	{
		return $this->getOption(CURLOPT_USERPWD);
	}

	public function setAuthority(string $username = null, ?string $password = null): self
	{
		$this->setOption(CURLOPT_USERPWD, $password !== null ? ($username . ':' . $password) : $username);
		return $this;
	}
	/* ----------------------------------------------------[ cookies routines ] */

	public function setCookieReadFile(string $filename): self
	{
		$this->setOption(CURLOPT_COOKIEFILE, $filename);
		return $this;
	}

	public function setCookieWriteFile(string $filename): self
	{
		$this->setOption(CURLOPT_COOKIEJAR, $filename);
		return $this;
	}

	public function setCookieFile(string $filename): self
	{
		$this->setOption(CURLOPT_COOKIEFILE, $filename);
		$this->setOption(CURLOPT_COOKIEJAR, $filename);
		return $this;
	}

	/* ------------------------------------------------------[ proxy routines ] */

	/**
	 * Set the HTTP proxy to tunnel requests through
	 * @param string $proxy The proxy address
	 * @param int|null $port The port number of the proxy to connect to
	 * @param string|null $username A username to use for the connection to the proxy
	 * @param string|null $password A password to use for the connection to the proxy
	 * @return $this
	 */
	public function setProxy(string $proxy, ?int $port = null, ?string $username = null, ?string $password = null): self
	{
		$this->setOption(CURLOPT_PROXY, $proxy);
		if ($port !== null) {
			$this->setOption(CURLOPT_PROXYPORT, $port);
		}
		if ($username !== null && $password !== null) {
			$this->setOption(CURLOPT_PROXYUSERPWD, $username . ':' . $password);
		}
		return $this;
	}

	public function getProxyHost(): ?string
	{
		return $this->getOption(CURLOPT_PROXY);
	}

	public function getProxyPort(): ?int
	{
		return $this->getOption(CURLOPT_PROXYPORT);
	}

	public function getProxyAuthority(): ?string
	{
		return $this->getOption(CURLOPT_PROXYUSERPWD);
	}

	public function getProxyString(): string
	{
		$result = $this->getProxyHost() ?: '';
		if ($port = $this->getProxyPort()) {
			$result .= ':' . $port;
		}
		if ($authority = $this->getProxyAuthority()) {
			$result = $authority . '@' . $result;
		}
		return $result;
	}

	public function removeProxy(): self
	{
		$this->removeOption(CURLOPT_PROXY);
		$this->removeOption(CURLOPT_PROXYUSERPWD);
		return $this;
	}
}