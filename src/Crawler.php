<?php

namespace Diz\Scraping;

use Diz\Scraping\Cookies\Cookie;
use Diz\Scraping\Cookies\CookieAwareInterface;
use Diz\Scraping\Cookies\PortableCookieInterface;
use Diz\Scraping\Enums\DataType;
use Diz\Scraping\Enums\Method;
use Diz\Scraping\Events\DownloadEvent;
use Diz\Scraping\Events\RedirectEvent;
use Diz\Scraping\Events\RequestEvent;
use Diz\Scraping\Exceptions\CurlException;
use Diz\Scraping\Exceptions\LoopedRedirectException;
use Diz\Scraping\Exceptions\OverflowRedirectException;
use Diz\Scraping\Exceptions\TimeoutException;
use Diz\Scraping\Pipes\CallbackPipe;
use Diz\Scraping\Pipes\JSONPipe;
use Diz\Scraping\Pipes\PipeInterface;

use Diz\Toolkit\Kits\FileKit;
use Diz\Toolkit\Kits\FilterKit;
use Diz\Toolkit\Kits\PathKit;
use Diz\Toolkit\Kits\TextKit;
use Diz\Toolkit\Kits\URLKit;

class Crawler extends Options
{
	/** @var resource|\CurlHandle */
	private $curl;

	private string $domain = '';
	private ?string $subdomain = null;
	private ?string $path = null;
	private bool $secured = true;
	private ?array $query = null;

	/** @var PortableCookieInterface[] */
	private array $cookies_queue = [];

	private ?Response $response;
	private array $responses = [];

	private bool $redirects_allowed = true;
	private ?int $redirects_limit = 10;
	private bool $persist_curl = true;
	private bool $strict_path_handling = false;

	private bool $verbose = false;

	private ?string $download_path = null;

	private ?int $override_file_mode = 0777;
	/** @var string|int|null */
	private $override_file_owner = null;
	/** @var string|int|null */
	private $override_file_group = null;

	private bool $use_remote_time = true;

	/** @var Pipeline[] */
	private array $pipelines = [];
	private bool $pipelines_active = true;

	private ?string $default_data_type = null;

	private bool $encode_urls = true;

	public function __construct(?string $domain = null, ?string $subdomain = null)
	{
		parent::__construct();

		if ($domain !== null) {
			$domain = trim($domain);
			if ($subdomain === null && TextKit::startsWith($domain, 'www.', false)) {
				$subdomain = 'www';
				$domain = substr($domain, 4);
			}
			$this->domain = $domain;
		}
		if ($subdomain !== null) {
			$this->setSubdomain($subdomain);
		}

		$this->headers = new Headers();

		$this->resetOptions();
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Close cURL forcibly
	 * @return $this
	 */
	public function close(): self
	{
		$this->closeCurl();
		return $this;
	}

	/**
	 * Initialize cURL forcibly
	 * @return $this
	 * @throws CurlException
	 */
	public function initialize(): self
	{
		$this->getCurlInstance();
		return $this;
	}

	/**
	 * Initialize cURL forcibly, close the current if it was already initialized
	 * @return $this
	 * @throws CurlException
	 */
	public function reinitialize(): self
	{
		$this->initCurl();
		return $this;
	}

	public function resetOptions(): self
	{
		$this->setOptions([
			CURLOPT_USERAGENT            => 'Mozilla/5.0 (Linux x86_64; rv:103.0) Gecko/20100101 Firefox/103.0',
			CURLOPT_FILETIME             => true,
			// enable in memory only cookies
			CURLOPT_COOKIEFILE           => '',
			CURLOPT_COOKIEJAR            => '',
			// send all supported encoding types
			CURLOPT_ENCODING             => '',

			CURLOPT_SSL_VERIFYPEER       => false,
			CURLOPT_SSL_VERIFYHOST       => false,
			CURLOPT_PROXY_SSL_VERIFYPEER => false,

			CURLOPT_HTTPGET              => true,

			CURLOPT_FILE                 => null,
			CURLOPT_INFILE               => null,
			CURLOPT_STDERR               => null,
			CURLOPT_WRITEHEADER          => null
		]);

		return $this;
	}

	public function getDomain(): string
	{
		return $this->domain;
	}

	public function setDomain(string $domain): self
	{
		$this->domain = trim($domain);
		return $this;
	}

	public function getSubdomain(): ?string
	{
		return $this->subdomain;
	}

	public function setSubdomain(?string $subdomain): self
	{
		$this->subdomain = TextKit::clarify($subdomain);
		return $this;
	}

	public function getHost(?string $subdomain = null): string
	{
		$subdomain = ($subdomain ? trim($subdomain) : null) ?: $this->subdomain;
		return $subdomain ? ($subdomain . '.' . $this->domain) : $this->domain;
	}

	public function getPath(): ?string
	{
		return $this->path;
	}

	public function setPath(?string $path): self
	{
		$this->path = TextKit::clarify($path);
		return $this;
	}

	public function normalizePath(string $path, bool $trailing_slash = true): string
	{
		if ($this->isStrictPathHandling()) {
			$base_path = $this->path ? PathKit::removeLastSegment($this->path) : null;
		} else {
			$base_path = $this->path;
		}
		return PathKit::normalize($path, $base_path, $trailing_slash);
	}

	public function isSecured(): bool
	{
		return $this->secured;
	}

	public function setSecured(bool $secured): self
	{
		$this->secured = $secured;
		return $this;
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

	public function isRedirectsAllowed(): bool
	{
		return $this->redirects_allowed;
	}

	public function allowRedirects(): self
	{
		return $this->setRedirectsAllowed(true);
	}

	public function disallowRedirects(): self
	{
		return $this->setRedirectsAllowed(false);
	}

	public function setRedirectsAllowed(bool $allow_redirect): self
	{
		$this->redirects_allowed = $allow_redirect;
		return $this;
	}

	public function getRedirectsLimit(): ?int
	{
		return $this->redirects_limit;
	}

	public function setRedirectsLimit(?int $redirects_limit): self
	{
		$this->redirects_limit = $redirects_limit ? max(1, $redirects_limit) : null;
		return $this;
	}

	public function isPersistCurl(): bool
	{
		return $this->persist_curl;
	}

	public function setPersistCurl(bool $persist_curl): self
	{
		$this->persist_curl = $persist_curl;
		return $this;
	}

	public function isStrictPathHandling(): bool
	{
		return $this->strict_path_handling;
	}

	public function setStrictPathHandling(bool $strict_path_handling): self
	{
		$this->strict_path_handling = $strict_path_handling;
		return $this;
	}

	public function isVerbose(): bool
	{
		return $this->verbose;
	}

	public function setVerbose(bool $verbose): self
	{
		$this->verbose = $verbose;
		return $this;
	}

	public function getDownloadPath(): ?string
	{
		return $this->download_path;
	}

	public function setDownloadPath(?string $download_path): self
	{
		$this->download_path = TextKit::clarify($download_path);
		return $this;
	}

	public function normalizeDownloadPath(string $path, bool $trailing_slash = true): string
	{
		return PathKit::normalize($path, $this->download_path, $trailing_slash);
	}

	public function getOverrideFileMode(): ?int
	{
		return $this->override_file_mode;
	}

	public function setOverrideFileMode(?int $override_file_mode): self
	{
		$this->override_file_mode = $override_file_mode;
		return $this;
	}

	public function getOverrideFileOwner()
	{
		return $this->override_file_owner;
	}

	public function setOverrideFileOwner($override_file_owner): self
	{
		$this->override_file_owner = $override_file_owner;
		return $this;
	}

	public function getOverrideFileGroup()
	{
		return $this->override_file_group;
	}

	public function setOverrideFileGroup($override_file_group): self
	{
		$this->override_file_group = $override_file_group;
		return $this;
	}

	public function isUseRemoteTime(): bool
	{
		return $this->use_remote_time;
	}

	public function setUseRemoteTime(bool $use_remote_time): self
	{
		$this->use_remote_time = $use_remote_time;
		return $this;
	}

	/* -----------------------------------------------------------[ pipelines ] */

	public function getPipelines(): array
	{
		return $this->pipelines;
	}

	/**
	 * @param Pipeline|null $pipeline The Pipeline, pass NULL to unset
	 * @param array|string $methods One or more target methods, pass empty string to set the pipeline as default
	 */
	public function setPipeline(?Pipeline $pipeline, $methods = ''): self
	{
		foreach ((array) $methods as $method) {
			if (FilterKit::canBeString($method) == false) {
				continue;
			}
			$method = TextKit::trimUpper($method);
			if ($pipeline === null) {
				unset($this->pipelines[$method]);
			} else {
				$this->pipelines[$method] = $pipeline;
			}
		}
		return $this;
	}

	/**
	 * @param PipeInterface $pipe The Pipe
	 * @param array|string $methods One or more target methods, pass empty string to set the pipeline as default
	 */
	public function addPipe(PipeInterface $pipe, $methods = ''): self
	{
		foreach ((array) $methods as $method) {
			if (FilterKit::canBeString($method) == false) {
				continue;
			}
			$method = TextKit::trimUpper($method);
			$pipeline = $this->pipelines[$method] ?? null;
			if ($pipeline === null) {
				$pipeline = $this->pipelines[$method] = $this->newPipelineInstance();
			}
			$pipeline->add($pipe);
		}
		return $this;
	}

	/**
	 * @param array|string $methods One or more target methods, pass empty string to set the pipeline as default
	 */
	public function addJSONPipe($methods = Method::WITH_PAYLOAD): self
	{
		return $this->addPipe($this->newJSONPipeInstance(), $methods);
	}

	/**
	 * @param callable $callback Callback
	 * @param array|string $methods One or more target methods, pass empty string to set the pipeline as default
	 */
	public function addCallbackPipe(callable $callback, $methods = Method::WITH_PAYLOAD): self
	{
		$pipe = $this->newCallbackPipeInstance();
		if ($pipe instanceof CallbackPipe) {
			$pipe->setCallback($callback);
		}
		return $this->addPipe($pipe, $methods);
	}

	public function clearPipelines(): self
	{
		$this->pipelines = [];
		return $this;
	}

	public function getPipeline(string $method = ''): ?Pipeline
	{
		$method = TextKit::trimUpper($method);
		return $this->pipelines[$method] ?? ($this->pipelines[''] ?? null);
	}

	/**
	 * @param mixed $value Input value
	 * @param string $method HTTP method
	 * @return mixed Output value
	 */
	#[\ReturnTypeWillChange]
	public function performPipeline($value, string $method = '')
	{
		$pipeline = $this->getPipeline($method);
		return ($pipeline && $pipeline->isActive()) ? $pipeline->perform($value) : $value;
	}

	public function isPipelinesActive(): bool
	{
		return $this->pipelines_active;
	}

	public function setPipelinesActive(bool $pipelines_active): self
	{
		$this->pipelines_active = $pipelines_active;
		return $this;
	}

	public function disablePipelines(): self
	{
		return $this->setPipelinesActive(false);
	}

	public function enablePipelines(): self
	{
		return $this->setPipelinesActive(true);
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

	public function isEncodeURLs(): bool
	{
		return $this->encode_urls;
	}

	/**
	 * By default, Crawler encodes all URLs during normalization before each request.
	 * If all URLs will be already encoded, then this option should be disabled.
	 * @param bool $encode_urls
	 * @return static
	 */
	public function setEncodeURLs(bool $encode_urls): self
	{
		$this->encode_urls = $encode_urls;
		return $this;
	}

	/* --------------------------------------------------------[ URL routines ] */

	/**
	 * Compose a new URL using the context of the crawler
	 * Query parameters will be merged in the next sequence - "crawler default" <- "query parameter"
	 * @param string|null $path Custom path
	 * @param array|null $query Custom query parameters
	 * @param string|null $subdomain Custom subdomain
	 * @param bool|null $https Scheme override
	 * @param string|null $domain Custom domain	 *
	 * @return string New URL
	 */
	public function composeURL(?string $path = null, ?array $query = null, ?string $subdomain = null, ?bool $https = null, ?string $domain = null): string
	{
		$domain = ($domain ? trim($domain) : null) ?: $this->getDomain();
		$subdomain = ($subdomain ? trim($subdomain) : null) ?: $this->getSubdomain();
		$host = $subdomain ? ($subdomain . '.' . $domain) : $domain;

		if ($path === null) {
			$path = $this->path ?? '';
		} else {
			$path = ltrim(trim($path), '/');
		}
		if ($query) {
			if ($base_query = $this->getQuery()) {
				$query = array_merge($base_query, $query);
			}
			$path .= '?' . URLKit::buildQuery($query);
		}

		if ($https === null) {
			$https = $this->isSecured();
		}
		return ($https ? 'https' : 'http') . '://' . $host . '/' . $path;
	}

	/**
	 * Normalize the existing URL using the context of the crawler
	 * Query parameters will be merged in the next sequence - "crawler default" <- "query parameter" <- "parsed from url"
	 * @param string $url The target URL
	 * @param array|null $query Additional query parameters
	 * @param bool|null $encode Encode URL before parsing, if null then use the default value (Crawler::isEncodeURLs)
	 * @return string Normalized URL
	 */
	public function normalizeURL(string $url, ?array $query = null, ?bool $encode = null): string
	{
		if ($encode === null) {
			$encode = $this->isEncodeURLs();
		}
		if ($encode) {
			$url = URLKit::encode($url);
		}
		$url = parse_url($url);

		if ($host = ($url['host'] ?? null)) {
			if (TextKit::endsWith($host, $this->getHost(), false) == false) {
				return URLKit::compose($url + [
					'scheme' => $this->isSecured() ? 'https' : 'http'
				]);
			}
		}

		if ($path = ($url['path'] ?? null)) {
			$url['path'] = $this->normalizePath($path, PathKit::hasTrailingDelimiter($path));
		} else {
			if ($this->path) {
				$url['path'] = $this->path;
			}
		}

		if ($query === null) {
			$query = $this->getQuery();
		} else if ($base_query = $this->getQuery()) {
			$query = array_merge($base_query, $query);
		}

		if ($query !== null) {
			if ($url_query = $url['query'] ?? null) {
				parse_str($url_query, $url_query);
				$query = array_merge($query, $url_query);
			}
			$url['query'] = $query;
		}

		return URLKit::compose($url + [
			'scheme' => $this->isSecured() ? 'https' : 'http',
			'host' => $this->getHost()
		]);
	}

	/**
	 * Generate a filename based on the specified file URL and destination
	 * @param string $url File URL
	 * @param string|null $destination File destination (can be either a full path or a directory)
	 */
	public function generateFilename(string $url, ?string $destination = null): string
	{
		$destination = TextKit::clarify($destination);
		if ($destination == null) {
			$destination = pathinfo($url, PATHINFO_BASENAME);
		} else if (PathKit::hasTrailingDelimiter($destination)) {
			$destination .= pathinfo($url, PATHINFO_BASENAME);
		}
		return PathKit::normalize($destination, $this->download_path, false);
	}

	public function enableXHR(): self
	{
		return $this->setXHR(true);
	}

	public function disableXHR(): self
	{
		return $this->setXHR(false);
	}

	public function setXHR(bool $xhr): self
	{
		$this->headers->setXHR($xhr);
		return $this;
	}

	public function isXHR(): bool
	{
		return $this->headers->isXHR();
	}

	public function newRequest(?string $url = null, ?array $query = null, ?string $referer = null): Request
	{
		$request = $this->newRequestInstance();

		$request->setCrawler($this);
		$request->setOptions($this->getOptions());
		$request->setHeaders(clone $this->getHeaders());

		$request->setDefaultDataType($this->getDefaultDataType());

		if ($url !== null) {
			$request->setURL($url);
		}
		if ($query !== null) {
			$request->setQuery($query);
		}
		if ($referer !== null) {
			$request->setReferer($referer);
		}

		return $request;
	}

	protected function newResponse(?string $url = null, ?int $status_code = null, ?string $content = null, ?string $reason_phrase = null): Response
	{
		$response = $this->newResponseInstance();

		if ($url !== null) {
			$response->setURL($url);
		}

		if ($status_code !== null) {
			$response->setStatusCode($status_code);
		}

		if ($content !== null) {
			$response->setContent($content);
		}

		if ($reason_phrase !== null) {
			$response->setReasonPhrase($reason_phrase);
		}

		return $response;
	}


	public function getResponse(): Response
	{
		return $this->response;
	}

	/**
	 * @return Response[]
	 */
	public function getResponses(): array
	{
		return $this->responses;
	}

	public function clearResponses(): self
	{
		$this->response = null;
		$this->responses = [];
		return $this;
	}

	/**
	 * Get a URL of the last response.
	 * @return string|null URL or null, if there were no responses
	 */
	public function getLastURL(): ?string
	{
		return $this->response ? $this->response->getURL() : null;
	}

	public function getLastStatusCode(): ?int
	{
		return $this->response ? $this->response->getStatusCode() : null;
	}

	public function getLastReasonPhrase(): ?string
	{
		return $this->response ? $this->response->getReasonPhrase() : null;
	}

	/**
	 * Get a redirect URL of the last response.
	 * If there was no redirect, then there will be an empty string.
	 * @return string|null URL or null, if there were no responses
	 */
	public function getLastRedirectURL(): ?string
	{
		return $this->response ? $this->response->getRedirectURL() : null;
	}

	/* ----------------------------------------------------[ cookies routines ] */

	/**
	 * Queue the specified cookie to add to the active cURL instance on the next request
	 * @param PortableCookieInterface $cookie
	 * @return $this
	 */
	public function addCookie(PortableCookieInterface $cookie): self
	{
		$this->cookies_queue[] = $cookie;
		return $this;
	}

	/**
	 * Queue multiple cookies to add to the active cURL instance on the next request
	 * @param PortableCookieInterface[] $cookies
	 * @return $this
	 */
	public function addCookies(array $cookies): self
	{
		foreach ($cookies as $cookie) {
			if ($cookie instanceof PortableCookieInterface) {
				$this->addCookie($cookie);
			}
		}
		return $this;
	}

	/**
	 * Clear all cookies in the active cURL instance and in the queue, and queue new cookies
	 * @param PortableCookieInterface[] $cookies
	 * @return $this
	 */
	public function replaceCookies(array $cookies): self
	{
		$this->clearCookies();
		$this->addCookies($cookies);
		return $this;
	}

	/**
	 * Create and queue a simple cookie to add to the active cURL instance on the next request
	 * @param string $name
	 * @param string $value
	 * @param int|null $lifetime Lifetime in seconds, null for unlimited
	 * @param string|null $path Path, if not specified, crawler's path will be used
	 * @return $this
	 */
	public function addSimpleCookie(string $name, string $value, ?int $lifetime = null, ?string $path = null): self
	{
		$cookie = $this->newSimpleCookieInstance();
		$cookie->setName($name);
		$cookie->setValue($value);
		$cookie->setExpiresAt($lifetime !== null ? Cookie::expiresAtFromLifetime($lifetime) : null);
		$cookie->setPath($path ?? ('/' . $this->getPath() ?? ''));
		$cookie->setHost($this->getHost());
		$cookie->setSecure($this->isSecured());
		return $this->addCookie($cookie);
	}

	/**
	 * Create and queue multiple simple cookies to add to the active cURL instance on the next request
	 * @param array $values
	 * @param int|null $lifetime Lifetime in seconds, null for unlimited
	 * @param string|null $path Path, if not specified, crawler's path will be used
	 * @return $this
	 */
	public function addSimpleCookies(array $values, ?int $lifetime = null, ?string $path = null): self
	{
		foreach ($values as $name => $value) {
			if (FilterKit::canBeString($value)) {
				$this->addSimpleCookie($name, $value, $lifetime, $path);
			}
		}
		return $this;
	}

	/**
	 * Flush the queued cookies to the active cURL instance, if it is initialized.
	 * @return $this
	 */
	public function flushCookies(): self
	{
		if ($this->isCurlInitialized()) {
			$this->flushCurlCookies($this->getCurl());
		}
		return $this;
	}

	/**
	 * Obtain the cookies from the active cURL instance.
	 * @return PortableCookieInterface[] Cookies list or empty array, if cURL instance is no initialized
	 */
	public function obtainCookies(): array
	{
		if ($this->isCurlInitialized()) {
			return $this->obtainCurlCookies($this->getCurl());
		}
		return [];
	}

	/**
	 * Clear all cookies in the active cURL instance and in the queue
	 * @return $this
	 */
	public function clearCookies(): self
	{
		if ($this->isCurlInitialized()) {
			$this->clearCurlCookies($this->getCurl());
		}
		$this->cookies_queue = [];
		return $this;
	}

	/**
	 * Load the cookies from the cookie file and write them to the active cURL instance
	 * @return $this
	 */
	public function loadCookiesFromFile(): self
	{
		if ($this->isCurlInitialized()) {
			$this->loadCurlCookiesFromFile($this->getCurl());
		}
		return $this;
	}

	/**
	 * Read back the cookies from the active cURL instance and save them to the cookie file
	 * @return $this
	 */
	public function saveCookiesToFile(): self
	{
		if ($this->isCurlInitialized()) {
			$this->saveCurlCookiesToFile($this->getCurl());
		}
		return $this;
	}

	/* -------------------------------------------------------[ cURL routines ] */

	/**
	 * @return resource|null
	 */
	#[\ReturnTypeWillChange]
	protected function getCurl()
	{
		return $this->curl;
	}

	/**
	 * @return resource|\CurlHandle
	 * @throws CurlException
	 */
	#[\ReturnTypeWillChange]
	protected function getCurlInstance()
	{
		return $this->curl ?? $this->initCurl();
	}

	protected function isCurlInitialized(): bool
	{
		return $this->curl != null;
	}

	/**
	 * @return resource|\CurlHandle
	 * @throws CurlException
	 */
	#[\ReturnTypeWillChange]
	protected function initCurl()
	{
		if ($this->curl) {
			$this->closeCurl();
		}

		$this->curl = curl_init();
		if ($this->curl === false) {
			$this->curl = null;
			throw new CurlException('Could not initialize cURL.', CURLE_FAILED_INIT);
		}

		return $this->curl;
	}

	protected function closeCurl(): self
	{
		if ($this->curl) {
			curl_close($this->curl);
			$this->curl = null;
		}
		return $this;
	}

	private function flushCurlCookies($curl): void
	{
		foreach ($this->cookies_queue as $cookie) {
			curl_setopt($curl, CURLOPT_COOKIELIST, $cookie->pack());
		}
		$this->cookies_queue = [];
	}

	private function obtainCurlCookies($curl): array
	{
		$cookies = curl_getinfo($curl, CURLINFO_COOKIELIST);
		$result = [];

		foreach ($cookies as $row) {
			$cookie = $this->newPortableCookieInstance();
			if ($cookie->unpack($row)) {
				$result[] = $cookie;
			}
		}

		return $result;
	}

	private function clearCurlCookies($curl): void
	{
		curl_setopt($curl, CURLOPT_COOKIELIST, 'ALL');
	}

	private function loadCurlCookiesFromFile($curl): void
	{
		curl_setopt($curl, CURLOPT_COOKIELIST, 'RELOAD');
	}

	private function saveCurlCookiesToFile($curl): void
	{
		curl_setopt($curl, CURLOPT_COOKIELIST, 'FLUSH');
	}

	/**
	 * * @throws TimeoutException
	 * @throws CurlException
	 */
	protected function executeCurl(Options $options, ?Headers $headers = null, ?string $url = null): Response
	{
		$curl = $this->getCurlInstance();

		if (curl_setopt_array($curl, $options->getOptions()) == false) {
			$error = curl_errno($curl);
			return $this->handleCurlError(null, curl_error($curl) ?: curl_strerror($error), $error);
		}

		if ($url !== null) {
			curl_setopt($curl, CURLOPT_URL, $url);
		}

		$this->flushCurlCookies($curl);

		if ($this->isVerbose()) {
			$verbose = fopen('php://temp', 'rw+');
			curl_setopt($curl, CURLOPT_STDERR, $verbose);
			curl_setopt($curl, CURLOPT_VERBOSE, true);
		} else {
			$verbose = null;
		}

		if ($headers) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers->getPlain());
		}

		$plain_headers = [];
		$status_code = null;
		$reason_phrase = null;

		curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $plain_header) use (&$plain_headers, &$status_code, &$reason_phrase): int
		{
			if (TextKit::startsWith($plain_header, 'HTTP/', false)) {
				$plain_headers = [];
				list( , $status_code, $reason_phrase) = explode(' ', $plain_header, 3);
				$reason_phrase = trim($reason_phrase) ?: null;
			} else {
				$plain_headers[] = $plain_header;
			}
			return strlen($plain_header); // Needed by curl
		});

		ob_start();
		$result = curl_exec($curl);
		$content = ob_get_clean();

		if ($result === false) {
			$error = curl_errno($curl);
			$error_message = curl_error($curl) ?: curl_strerror($error);
		}

		curl_setopt($curl, CURLOPT_HEADERFUNCTION, null);

		if ($verbose) {
			rewind($verbose);
			if (($verbose_content = stream_get_contents($verbose)) !== false) {
				$this->handleCurlVerbose($verbose_content);
			}
			fclose($verbose);
			curl_setopt($curl, CURLOPT_STDERR, null);
			curl_setopt($curl, CURLOPT_VERBOSE, false);
		}

		$url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

		if ($result === false) {
			return $this->handleCurlError($url, $error_message, $error);
		}

		if ($status_code == null) {
			$status_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		}

		$response = $this->newResponse($url, $status_code, $content, $reason_phrase);

		$response->setPlainHeaders($plain_headers);
		$this->updateResponse($curl, $response);

		return $response;
	}

	protected function updateResponse($curl, Response $response): void
	{
		$response->setRedirectURL(curl_getinfo($curl, CURLINFO_REDIRECT_URL));
		$response->setDownloadSize(curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD_T));
		$response->setDownloadSpeed(curl_getinfo($curl, CURLINFO_SPEED_DOWNLOAD_T));
		if (($document_time = curl_getinfo($curl, CURLINFO_FILETIME)) >= 0) {
			$response->setDocumentTime($document_time);
		}

		$response->setDownloadTime((
				curl_getinfo($curl, CURLINFO_TOTAL_TIME_T)
			- curl_getinfo($curl, CURLINFO_NAMELOOKUP_TIME_T)
			- curl_getinfo($curl, CURLINFO_CONNECT_TIME_T)
			- curl_getinfo($curl, CURLINFO_PRETRANSFER_TIME_T)
			- curl_getinfo($curl, CURLINFO_STARTTRANSFER_TIME_T)
			- curl_getinfo($curl, CURLINFO_REDIRECT_TIME_T)
		) / 1000000);
	}

	/**
	 * @throws TimeoutException
	 * @throws CurlException
	 */
	protected function handleCurlError(?string $url, string $message, int $number)
	{
		switch ($number) {
			case 28:
				throw new TimeoutException($url, $message, $number);

			default:
				throw new CurlException($url, $message, $number);
		}
	}

	protected function handleCurlVerbose(string $verbose)
	{

	}

	/* ----------------------------------------------------[ request routines ] */

	/**
	 * Send the already prepared HTTP Request
	 * @param Request|null $request The HTTP Request
	 * @param Pipeline|bool $pipeline Use the specified Pipeline or, with true, one of the corresponding pipelines
	 * @return mixed
	 * @throws CurlException
	 * @throws LoopedRedirectException
	 * @throws OverflowRedirectException
	 * @throws TimeoutException
	 */
	public function sendRequest(?Request $request = null, $pipeline = true)
	{
		$request = $request ?: $this->newRequest();

		$url = $request->getDirectURL();
		if ($url == null) {
			$url = $this->normalizeURL($request->getURL(), $request->getQuery());
			if ($url == false) {
				throw new \InvalidArgumentException('Request URL is not set or empty.');
			}
		}

		$event = new RequestEvent($request, $url);
		$this->onRequestEvent($event);
		if ($event->isIgnored()) {
			return null;
		}
		$url = $event->getEffectiveURL();

		$options = new Options($request->getOptions());
		if ($this->isPersistCurl() && $request->getMethod() == Method::GET) {
			// Force the previously using curl handle to get back to using GET
			$options->setCustomMethod(Method::GET);
			$options->setOption(CURLOPT_HTTPGET, true);
		}
		$headers = $request->getHeaders() ?? $this->getHeaders();

		if ($this->redirects_allowed && $request->getOption(CURLOPT_FOLLOWLOCATION) == false) {

			$locations = [];
			$count = 0;
			$this->clearResponses();

			while (true) {
				$this->responses[] = $this->response = $response = $this->executeCurl($options, $headers, $url);

				if ($response->isRedirect() == false) {
					break;
				}

				$locations[] = $url = ($response->getURL() ?? '');

				$event = new RedirectEvent(URLKit::complete($response->getRedirectURL() ?? '', $url), $response->getStatusCode(), ++$count);
				$this->onRedirectEvent($event);
				if ($event->isIgnored()) {
					break;
				}
				$url = $event->getLocation();
				if (in_array($url, $locations)) {
					throw new LoopedRedirectException($event);
				}
				if ($this->getRedirectsLimit() && $event->getCount() > $this->getRedirectsLimit()) {
					throw new OverflowRedirectException($event);
				}
				// if the output to the file is set, then we need to clean it from the redirect response
				if ($file = $request->getFile()) {
					ftruncate($file, 0);
				}
			}
		} else {
			$this->responses = [$this->response = $this->executeCurl($options, $headers, $url)];
		}

		if ($this->isPersistCurl() == false) {
			$this->closeCurl();
		}

		$result = $this->response->getContent();
		if ($pipeline == false) {
			return $result;
		}
		if ($pipeline instanceof Pipeline) {
			return $pipeline->perform($result);
		}
		return $this->isPipelinesActive() ? $this->performPipeline($result, $request->getMethod()) : $result;
	}

	/**
	 * @return mixed
	 */
	public function get(?string $url = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toGET()->send();
	}

	/**
	 * @return mixed
	 */
	public function post(?string $url = null, $data = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toPOST($data)->send();
	}

	/**
	 * @return mixed
	 */
	public function put(?string $url = null, $data = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toPUT($data)->send();
	}

	/**
	 * @return mixed
	 */
	public function delete(?string $url = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toDELETE()->send();
	}

	/**
	 * @return mixed
	 */
	public function head(?string $url = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toHEAD()->send();
	}

	/**
	 * @return mixed
	 */
	public function options(?string $url = null, ?array $query = null, ?string $referer = null)
	{
		return $this->newRequest($url, $query, $referer)->toOPTIONS()->send();
	}

	/**
	 * @return mixed
	 */
	public function custom(string $method, ?string $url = null, $data = null, ?array $query = null, ?string $referer = null)
	{
		$request = $this->newRequest($url, $query, $referer);

		$method = TextKit::trimUpper($method);
		switch ($method) {
			case Method::GET:
				$request->toGET();
				break;

			case Method::POST:
				$request->toPOST($data ?? []);
				break;

			case Method::PUT:
				$request->toPUT($data ?? []);
				break;

			case Method::DELETE:
				$request->toDELETE();
				break;

			case Method::HEAD:
				$request->toHEAD();
				break;

			case Method::OPTIONS:
				$request->toOPTIONS();
				break;

			default:
				$request->setCustomMethod($method);
				if ($data !== null) {
					$request->setData($data);
				}
		}

		return $request->send();
	}

	public function download(string $url, ?string $destination = null, ?array $query = null, ?string $referer = null): ?int
	{
		$destination = $this->generateFilename($url, $destination);

		if ($destination == false) {
			throw new \InvalidArgumentException('Unable to autodetect destination filename');
		}

		FileKit::makeDir(dirname($destination));

		$file = fopen($destination, 'wb');

		$request = $this->newRequest($url, $query, $referer);
		$request->setFile($file);
		$request->send(false);

		fclose($file);

		$response = $this->response;
		$event = new DownloadEvent($destination, $response);
		if ($response->getStatusCode() != 200 || $response->getDownloadSize() == 0) {
			$event->ignore();
		}
		$this->onDownloadEvent($event);
		if ($event->isIgnored()) {
			unlink($destination);
			return null;
		}

		if ($this->override_file_mode) {
			chmod($destination, $this->override_file_mode);
		}
		if ($this->override_file_owner) {
			chown($destination, $this->override_file_owner);
		}
		if ($this->override_file_group) {
			chgrp($destination, $this->override_file_group);
		}

		if ($this->use_remote_time && ($time = $response->getDocumentTime()) >= 0) {
			touch($destination, $time);
		}

		return $response->getDownloadSize();
	}

	/**
	 * Test the speed of downloading data for the specified URL
	 * @param string $url Data URL
	 * @param array|null $query The query parameters
	 * @param string|null $referer The referer for the spoofing
	 * @param int $connect_timeout The number of seconds to wait while trying to connect. Use 0 to wait indefinitely
	 * @param int $execute_timeout The maximum number of milliseconds allowed to execute
	 * @return int|null Average download speed
	 */
	public function test(string $url, ?array $query = null, ?string $referer = null, int $connect_timeout = 2, int $execute_timeout = 60): ?int
	{
		$request = $this->newRequest($url, $query, $referer);
		$request->setOption(CURLOPT_CONNECTTIMEOUT, $connect_timeout);
		$request->setOption(CURLOPT_TIMEOUT, $execute_timeout);
		$request->send(false);

		return $this->response->getDownloadSpeed();
	}

	/**
	 * Find the final URL by going through all redirects
	 * Pay attention! This method does not produce redirect events
	 * @param string $url Starting URL
	 * @param array|null $query The query parameters
	 * @param string|null $referer The referer for the spoofing
	 * @param bool $use_get_method Use the GET method instead of HEAD
	 */
	public function discover(string $url, ?array $query = null, ?string $referer = null, bool $use_get_method = false): ?string
	{
		$request = $this->newRequest($url, $query, $referer);
		$request->setOption(CURLOPT_FOLLOWLOCATION, true);
		if ($use_get_method) {
			$request->setOption(CURLOPT_NOBODY, true);
		} else {
			$request->toHEAD();
		}

		$request->send(false);

		return $this->response->getURL();
	}

	public function getJSON(string $url, ?array $query = null, ?string $referer = null): ?array
	{
		$content = $this->newRequest($url, $query, $referer)->send(false);
		return json_decode($content, true);
	}

	/* --------------------------------------------------------------[ events ] */

	protected function onRequestEvent(RequestEvent $event): void
	{ }

	protected function onRedirectEvent(RedirectEvent $event): void
	{ }

	protected function onDownloadEvent(DownloadEvent $event): void
	{	}

	/* ------------------------------------------------[ dependency injection ] */

	protected function newRequestInstance(): Request
	{
		return new Request();
	}

	protected function newResponseInstance(): Response
	{
		return new Response();
	}

	protected function newJSONPipeInstance(): PipeInterface
	{
		return new JSONPipe();
	}

	protected function newCallbackPipeInstance(): PipeInterface
	{
		return new CallbackPipe();
	}

	protected function newPipelineInstance(): Pipeline
	{
		return new Pipeline();
	}

	protected function newSimpleCookieInstance(): CookieAwareInterface
	{
		return new Cookie();
	}

	protected function newPortableCookieInstance(): PortableCookieInterface
	{
		return $this->newSimpleCookieInstance();
	}
}