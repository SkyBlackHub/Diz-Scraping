<?php

namespace Diz\Scraping\Tests;

use PHPUnit\Framework\TestCase;

use Diz\Scraping\Exceptions\ExtractorException;
use Diz\Scraping\Extractor;

final class ExtractorTest extends TestCase
{
	private static string $content = '<div id="item_123" data-slug="/items/123">Foo Bar Item <span>* - rare</span></div>';

	/**
	 * @covers Extractor::extract
	 * @throws ExtractorException
	 */
	public function testExtract()
	{
		$extractor = new Extractor(self::$content);

		$regexp = 'id="item_(\d*)"';
		$this->assertSame('123', $extractor->extract($regexp), 'Simple Group');

		$regexp = 'id="item_(?P<id>\d*)"';
		$this->assertSame('123', $extractor->extract($regexp, 1), 'Indexed Group');
		$this->assertSame('123', $extractor->extract($regexp, 'id'), 'Named Group');
	}

	/**
	 * @covers Extractor::extractAll
	 * @throws ExtractorException
	 */
	public function testExtractAll()
	{
		$extractor = new Extractor('<div id="item_1">Item 1</div><div id="item_2">Item 2</div><div id="item_3">Item 3</div>');

		$regexp = 'id="item_(?P<id>\d*)">([^<]*)';
		$this->assertSame(['1', '2', '3'], $extractor->extractAll($regexp, 'id'), 'Named Group');
		$this->assertSame(['1', '2', '3'], $extractor->extractAll($regexp, 1), 'Indexed Group');
		$this->assertSame([null, null, null], $extractor->extractAll($regexp, 'wrong'), 'Wrong Group');
	}

	/**
	 * @covers Extractor::check
	 */
	public function testCheck()
	{
		$extractor = new Extractor(self::$content);

		$this->assertTrue($extractor->check('data-slug=".*"'));
		$this->assertFalse($extractor->check('data-slug=".*777"'));
	}

	/**
	 * @covers Extractor::equal
	 * @throws ExtractorException
	 */
	public function testEqual()
	{
		$extractor = new Extractor(self::$content);

		$this->assertTrue($extractor->equal('data-slug="(.*)"', '/items/123'));
		$this->assertFalse($extractor->equal('data-slug="(.*)"', '/items/777'));
		$this->assertFalse($extractor->equal('data-slug="(.*)"', '/Items/123', null, true));

		$this->assertTrue($extractor->equal('data-slug="(?P<slug>.*)"', '/items/123', 'slug'));
	}
}
