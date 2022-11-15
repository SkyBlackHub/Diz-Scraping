<?php

namespace Diz\Scraping\Traits;

use Diz\Scraping\Request;

trait VerboseMemoryTrait
{
	private string $verbose_output = '';

	private bool $persist_verbose_output = false;

	public function getVerboseOutput(): string
	{
		return $this->verbose_output;
	}

	public function setVerboseOutput(string $verbose_output): self
	{
		$this->verbose_output = $verbose_output;
		return $this;
	}

	public function clearVerboseOutput(): self
	{
		$this->verbose_output = '';
		return $this;
	}

	public function isPersistVerboseOutput(): bool
	{
		return $this->persist_verbose_output;
	}

	public function setPersistVerboseOutput(bool $persist_verbose_output): self
	{
		$this->persist_verbose_output = $persist_verbose_output;
		return $this;
	}

	protected function handleCurlVerbose(string $verbose)
	{
		$this->verbose_output .= $verbose;
	}

	protected function beforeRequest(Request $request): void
	{
		if ($this->isPersistVerboseOutput() == false) {
			$this->clearVerboseOutput();
		}
	}
}