<?php

namespace Diz\Scraping\Tests;

use Diz\Scraping\Cookies\Cookie;
use Diz\Scraping\Enums\DataType;

use PHPUnit\Framework\TestCase;

use Diz\Scraping\Crawler;
use Diz\Scraping\Request;

use Diz\Toolkit\Kits\FileKit;

final class CrawlerTest extends TestCase
{
	private static function rootDir(): string
	{
		return __DIR__ . '/..';
	}

	public static function setUpBeforeClass(): void
	{
		chdir(self::rootDir());
		FileKit::delete('sandbox', true);
		FileKit::makeDir('sandbox');
		chdir('sandbox');
	}

	public static function tearDownAfterClass(): void
	{
		chdir(self::rootDir());
		FileKit::delete('sandbox', true);
	}

	private function instance(): Crawler
	{
		return (new Crawler('httpbin.org'))->addJSONPipe();
	}

	/**
	 * @covers Crawler::get
	 */
	public function testGet()
	{
		$crawler = $this->instance();

		$result = $crawler->get('get');

		$this->assertSame($crawler->normalizeURL('get'), $result['url']);

		$result = $crawler->get('get', ['foo' => 'bar', 'test' => 123]);

		$this->assertSame(['foo' => 'bar', 'test' => '123'], $result['args']);

		$crawler->get('status/403');
		$this->assertSame(403, $crawler->getLastStatusCode());

		$crawler->delete('status/201');
		$this->assertSame(201, $crawler->getLastStatusCode());
	}

	/**
	 * @covers Crawler::download
	 */
	public function testDownload()
	{
		$crawler = $this->instance();

		$size = $crawler->download('image/jpeg', 'test.jpg');

		$this->assertSame(35588, $size);
		$this->assertTrue(file_exists('test.jpg'));
	}

	/**
	 * @covers Crawler::composeURL
	 */
	public function testComposeURL()
	{
		$crawler = $this->instance();

		$this->assertSame('https://httpbin.org/', $crawler->composeURL());
		$this->assertSame('https://www.httpbin.org/', $crawler->composeURL(null, null, 'www'));
		$this->assertSame('http://httpbin.org/', $crawler->composeURL(null, null, null, false));
		$this->assertSame('https://httpbin.org/test?foo=bar', $crawler->composeURL('test', ['foo' => 'bar']));
		$this->assertSame('https://httpbin.org/test/?foo[bar]=1&foo[no]=0', $crawler->composeURL('test/', ['foo' => ['bar' => 1, 'no' => 0]]));
		$this->assertSame('https://httpbin.org/test/?foo[]=a&foo[]=b&foo[]=c', $crawler->composeURL('test/', ['foo' => ['a', 'b', 'c']]));
		$this->assertSame('http://www.google.com/search?q=foo', $crawler->composeURL('search', ['q' => 'foo'], 'www', false, 'google.com'));
	}

	/**
	 * @covers Crawler::normalizeURL
	 * @covers Crawler::setPath
	 * @group current
	 * @covers Crawler::setStrictPathHandling
	 */
	public function testNormalizeUL()
	{
		$crawler = $this->instance();

		$this->assertSame('https://httpbin.org/', $crawler->normalizeURL(''));
		$this->assertSame('https://httpbin.org/foo', $crawler->normalizeURL('foo'));
		$this->assertSame('https://httpbin.org/foo?bar=123', $crawler->normalizeURL('foo?bar=123'));

		$this->assertSame('https://httpbin.org/foo?test=me&bar=123', $crawler->normalizeURL('foo?bar=123', ['test' => 'me']));
		$crawler->setQuery(['token' => 'secret']);
		$this->assertSame('https://httpbin.org/?token=secret#here', $crawler->normalizeURL('#here'));
		$this->assertSame('https://httpbin.org/foo?token=secret&test=me&bar=123', $crawler->normalizeURL('foo?bar=123', ['test' => 'me']));
		$this->assertSame('https://httpbin.org/foo?token=not-secret&test=me&bar=123', $crawler->normalizeURL('foo?bar=123&token=not-secret', ['test' => 'me']));

		$this->assertSame('https://google.com/search', $crawler->normalizeURL('//google.com/search'));

		$this->assertSame('https://sample.com/100%success', $crawler->normalizeURL('https://sample.com/100%success', null, false));

		$crawler->setQuery(null);

		$crawler->setPath('foo');
		$this->assertSame('https://httpbin.org/foo', $crawler->normalizeURL(''));
		$this->assertSame('https://httpbin.org/foo/bar', $crawler->normalizeURL('bar'));
		$this->assertSame('https://httpbin.org/bar', $crawler->normalizeURL('/bar'));

		$crawler->setStrictPathHandling(true);
		$this->assertSame('https://httpbin.org/foo', $crawler->normalizeURL(''));
		$this->assertSame('https://httpbin.org/bar', $crawler->normalizeURL('bar'));
		$this->assertSame('https://httpbin.org/bar', $crawler->normalizeURL('/bar'));

		$crawler->setPath('foo/');
		$this->assertSame('https://httpbin.org/foo/', $crawler->normalizeURL(''));
		$this->assertSame('https://httpbin.org/foo/bar', $crawler->normalizeURL('bar'));
		$this->assertSame('https://httpbin.org/bar/', $crawler->normalizeURL('/bar/'));
	}

	/**
	 * @covers Crawler::post
	 * @covers Request::toPOST
	 * @covers Request::send
	 */
	public function testPost()
	{
		$crawler = $this->instance();

		$crawler->setDefaultDataType(DataType::PLAIN);
		$result = $crawler->post('post', 'test data');
		$this->assertSame('test data', $result['data']);

		$data = ['foo' => ['bar' => 1, 'test' => ['me' => 'now']]];
		$form_data = [
			'foo[bar]' => '1',
			'foo[test][me]' => 'now'
		];

		$crawler->setDefaultDataType(DataType::JSON);
		$result = $crawler->post('post', $data);
		$this->assertSame($data, $result['json']);

		$result = $crawler->get('get');
		$this->assertSame($crawler->normalizeURL('get'), $result['url']);

		$request = $crawler->newRequest('post');
		$request->toPOST($data, DataType::FORM);
		$result = $request->send();

		$this->assertSame($form_data, $result['form']);

		$request->setData($data, DataType::JSON);
		$result = $request->send();
		$this->assertSame(json_encode($data), $result['data']);

		$request->setData($data, DataType::QUERY);
		$result = $crawler->sendRequest($request);

		$this->assertSame($form_data, $result['form']);
		$headers = $this->getHeaders($result);
		$this->assertSame('application/x-www-form-urlencoded', $headers['Content-Type']);

		$result = $crawler->newRequest('get')->send();
		$this->assertSame($crawler->normalizeURL('get'), $result['url']);
	}

	/**
	 * @covers Crawler::getLastURL
	 * @covers Crawler::getLastRedirectURL
	 * @covers Crawler::disallowRedirects
	 */
	public function testRedirect()
	{
		$crawler = $this->instance();

		$crawler->get('redirect/3');

		$this->assertSame($crawler->composeURL('get'), $crawler->getLastURL());

		$crawler->disallowRedirects();
		$crawler->get('redirect/3');
		$this->assertSame($crawler->composeURL('relative-redirect/2'), $crawler->getLastRedirectURL());
	}

	public function getHeaders(array $result): array
	{
		$this->assertArrayHasKey('headers', $result);

		return $result['headers'];
	}

	/**
	 * @covers Crawler::newRequest
	 * @covers Crawler::sendRequest
	 * @covers Request::setHeader
	 * @covers Request::addHeader
	 * @covers Crawler::addCallbackPipe
	 */
	public function testHeaders()
	{
		$crawler = $this->instance();

		$crawler->addCallbackPipe([$this, 'getHeaders']);

		$crawler->addHeader('Test', 'Foo');

		$headers = $crawler->get('headers');

		$this->assertSame('Foo', $headers['Test']);

		$request = $crawler->newRequest('headers');

		$request->addHeader('Test', 'Bar');
		$headers = $request->send();
		$this->assertSame('Foo,Bar', $headers['Test']);

		$request->setHeader('Test', 'Bar');
		$headers = $request->send();
		$this->assertSame('Bar', $headers['Test']);

		$request = $crawler->newRequest('headers');
		$headers = $crawler->sendRequest($request);

		$this->assertSame('Foo', $headers['Test']);
	}

	/**
	 * @covers Crawler::setPath
	 * @covers Crawler::addSimpleCookie
	 * @covers Crawler::addCallbackPipe
	 * @covers Crawler::replaceCookies
	 * @covers Crawler::flushCookies
	 * @covers Crawler::disablePipelines
	 * @covers Crawler::enablePipelines
	 */
	public function testCookies()
	{
		$crawler = $this->instance();
		$crawler->setPath('cookies');

		$crawler->addCallbackPipe(function($result) {
			$this->assertIsArray($result);
			$this->assertArrayHasKey('cookies', $result);
			return $result['cookies'];
		});

		$crawler->addSimpleCookie('foo', 'bar', null, '/');
		$cookies = $crawler->get();

		$this->assertSame('bar', $cookies['foo'] ?? null);

		$crawler->get('set', ['test' => 'me']);
		$cookies = $crawler->get();
		$this->assertSame('me', $cookies['test'] ?? null);

		$crawler->addSimpleCookie('new', 'cookie');
		$cookies = $crawler->get();

		$this->assertSame('cookie', $cookies['new'] ?? null);
		$cookies = $crawler->obtainCookies();
		$this->assertSame(3, count($cookies));

		$cookie = $cookies[2];
		$this->assertInstanceOf(Cookie::class, $cookie);
		$this->assertSame('new', $cookie->getName());
		$this->assertSame('cookie', $cookie->getValue());
		$cookie->setPath('/');
		$crawler->replaceCookies($cookies);
		$crawler->flushCookies();

		$crawler->disallowRedirects();
		$crawler->disablePipelines();
		$crawler->get('delete?test');
		$crawler->get('delete?new');
		$crawler->enablePipelines();
		$cookies = $crawler->get();
		$this->assertSame(['foo' => 'bar'], $cookies);
		$this->assertSame(1, count($crawler->obtainCookies()));

		$crawler->allowRedirects();
		$cookies = $crawler->get('delete?foo');
		$this->assertSame([], $cookies);
	}

	/**
	 * @covers Crawler::setAuthority
	 * @covers Crawler::getAuthority
	 */
	public function testAuth()
	{
		$crawler = $this->instance();

		$crawler->setAuthority('test_user');
		$this->assertSame('test_user', $crawler->getAuthority());

		$crawler->setAuthority('test_user', 'test_pass');
		$result = $crawler->get('/basic-auth/test_user/test_pass');

		$this->assertSame([
			'authenticated' => true,
			'user' => 'test_user'
		], $result);

		$this->assertSame('test_user:test_pass', $crawler->getAuthority());
	}
}