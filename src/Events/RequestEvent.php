<?php

namespace Diz\Scraping\Events;

use Diz\Scraping\Request;

class RequestEvent extends CrawlerEvent
{
	private Request $request;

	private string $effective_url;

	public function __construct(Request $request, string $effective_url)
	{
		$this->request = $request;
		$this->effective_url = $effective_url;
	}

	public function getRequest(): Request
	{
		return $this->request;
	}

	public function setRequest(Request $request): self
	{
		$this->request = $request;
		return $this;
	}

	public function getEffectiveURL(): string
	{
		return $this->effective_url;
	}

	public function setEffectiveURL(string $effective_url): self
	{
		$this->effective_url = trim($effective_url);
		return $this;
	}
}