<?php

namespace Diz\Scraping;

use Diz\Scraping\Enums\Method;
use Diz\Scraping\Exceptions\CurlException;
use Diz\Scraping\Exceptions\LoopedRedirectException;
use Diz\Scraping\Exceptions\OverflowRedirectException;
use Diz\Scraping\Exceptions\TimeoutException;

class Request extends Options
{
	private string $url = '';
	private string $method = Method::GET;
	private ?array $query = null;
	private ?Crawler $crawler = null;
	private ?string $default_data_type = null;

	public function __construct(array $options = [], ?Headers $headers = null)
	{
		parent::__construct($options);
		$this->headers = $headers ?? new Headers();
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

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getQuery(): ?array
	{
		return $this->query;
	}

	public function setQuery(?array $query): self
	{
		$this->query = $query;
		return $this;
	}

	protected function setMethod(string $method): self
	{
		$this->method = $method;
		return $this;
	}

	public function getCrawler(): ?Crawler
	{
		return $this->crawler;
	}

	public function setCrawler(?Crawler $crawler): self
	{
		$this->crawler = $crawler;
		return $this;
	}

	public function getDefaultDataType(): ?string
	{
		return $this->default_data_type;
	}

	public function setDefaultDataType(?string $default_data_type): self
	{
		$this->default_data_type = $default_data_type;
		return $this;
	}

	public function toGET(): self
	{
		$this->setMethod(Method::GET);
		$this->setCustomMethod(Method::GET);
		$this->removeOption(CURLOPT_NOBODY, CURLOPT_HEADER);
		$this->removeData();
		return $this;
	}

	public function toPOST($data = null, ?string $data_type = null): self
	{
		$this->setMethod(Method::POST);
		$this->setCustomMethod(Method::POST);
		$this->removeOption(CURLOPT_NOBODY, CURLOPT_HEADER);
		if ($data !== null) {
			$this->setData($data, $data_type ?? $this->getDefaultDataType());
		}
		return $this;
	}

	public function toPUT($data = null, ?string $data_type = null): self
	{
		$this->setMethod(Method::PUT);
		$this->setCustomMethod(Method::PUT);
		$this->removeOption(CURLOPT_NOBODY, CURLOPT_HEADER);
		if ($data !== null) {
			$this->setData($data, $data_type ?? $this->getDefaultDataType());
		}
		return $this;
	}

	public function toDELETE(): self
	{
		$this->setMethod(Method::DELETE);
		$this->setCustomMethod(Method::DELETE);
		$this->removeOption(CURLOPT_NOBODY, CURLOPT_HEADER);
		$this->removeData();
		return $this;
	}

	public function toHEAD(): self
	{
		$this->setMethod(Method::HEAD);
		// the method must NOT be set (cURL will set it by itself), otherwise the request will hang
		$this->setCustomMethod(null);
		$this->setOption(CURLOPT_NOBODY, true);
		$this->removeOption(CURLOPT_HEADER);
		$this->removeData();
		return $this;
	}

	public function toOPTIONS(): self
	{
		$this->setMethod(Method::OPTIONS);
		$this->setCustomMethod(Method::OPTIONS);
		$this->removeOption(CURLOPT_NOBODY);
		$this->setOption(CURLOPT_HEADER, true);
		$this->removeData();
		return $this;
	}

	/**
	 * Send this request via the assigned crawler
	 * @param Pipeline|bool $pipeline Use the specified Pipeline or, with true, one of the crawler's corresponding pipelines
	 * @return mixed
	 * @throws CurlException
	 * @throws LoopedRedirectException
	 * @throws OverflowRedirectException
	 * @throws TimeoutException
	 */
	public function send($pipeline = true)
	{
		if ($this->crawler == null) {
			return null;
		}
		return $this->crawler->sendRequest($this, $pipeline);
	}
}