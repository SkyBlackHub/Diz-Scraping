<?php

namespace Diz\Scraping\Events;

class CrawlerEvent
{
	private bool $accepted = true;

	public function isAccepted(): bool
	{
		return $this->accepted;
	}

	public function isIgnored(): bool
	{
		return $this->accepted == false;
	}

	public function setAccepted(bool $accepted): self
	{
		$this->accepted = $accepted;
		return $this;
	}

	public function accept(): self
	{
		$this->accepted = true;
		return $this;
	}

	public function ignore(): self
	{
		$this->accepted = false;
		return $this;
	}
}