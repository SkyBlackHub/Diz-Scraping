<?php

namespace Diz\Scraping\Cookies;

interface PortableCookieInterface
{
	public function pack(): string;

	public function unpack(string $packed): bool;
}