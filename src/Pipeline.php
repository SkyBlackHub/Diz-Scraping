<?php

namespace Diz\Scraping;

use Diz\Scraping\Pipes\PipeInterface;

class Pipeline
{
	/** @var PipeInterface[] */
	private array $pipes = [];

	private bool $active = true;

	public function __construct(PipeInterface ...$pipes)
	{
		$this->setPipes($pipes);
	}

	public function getPipes(): array
	{
		return $this->pipes;
	}

	public function setPipes(array $pipes): self
	{
		$this->pipes = [];
		foreach ($pipes as $pipe) {
			if ($pipe instanceof PipeInterface) {
				$this->add($pipe);
			}
		}
		return $this;
	}

	public function isActive(): bool
	{
		return $this->active;
	}

	public function setActive(bool $active): self
	{
		$this->active = $active;
		return $this;
	}

	public function disable(): self
	{
		return $this->setActive(false);
	}

	public function enable(): self
	{
		return $this->setActive(true);
	}

	public function clear(): self
	{
		$this->pipes = [];
		return $this;
	}

	public function add(PipeInterface $pipe): self
	{
		$this->pipes[] = $pipe;
		return $this;
	}

	public function remove(PipeInterface $pipe): self
	{
		if ($index = array_search($pipe, $this->pipes, true)) {
			unset($this->pipes[$index]);
		}
		return $this;
	}

	public function removeAt(int $index): self
	{
		unset($this->pipes[$index]);
		return $this;
	}

	public function removeLast(): self
	{
		unset($this->pipes[count($this->pipes) - 1]);
		return $this;
	}

	/**
	 * @param mixed $value Input value
	 * @return mixed Output value
	 */
	#[\ReturnTypeWillChange]
	public function perform($value)
	{
		foreach ($this->pipes as $pipe) {
			$value = $pipe->transform($value);
		}
		return $value;
	}
}