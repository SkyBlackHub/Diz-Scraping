<?php

namespace Diz\Scraping\Pipes;

class CallbackPipe implements PipeInterface
{
	private $callback = null;

	public function __construct(?callable $callback = null)
	{
		$this->callback = $callback;
	}

	public function getCallback(): ?callable
	{
		return $this->callback;
	}

	public function setCallback(?callable $callback): self
	{
		$this->callback = $callback;
		return $this;
	}

	#[\ReturnTypeWillChange]
	public function transform($value)
	{
		return $this->callback ? call_user_func($this->callback, $value) : null;
	}
}