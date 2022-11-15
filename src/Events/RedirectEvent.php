<?php

namespace Diz\Scraping\Events;

class RedirectEvent extends CrawlerEvent
{
	private string $location;
	private int $status_code;
	private int $count;

	public function __construct(string $location, int $status_code = 301, int $count = 1)
	{
		$this->location = $location;
		$this->status_code = $status_code;
		$this->count = $count;
	}

	public function getLocation(): string
	{
		return $this->location;
	}

	public function setLocation(string $location): self
	{
		$this->location = $location;
		return $this;
	}

	public function getStatusCode(): int
	{
		return $this->status_code;
	}

	public function setStatusCode(int $status_code): self
	{
		$this->status_code = $status_code;
		return $this;
	}

	public function getCount(): int
	{
		return $this->count;
	}
}