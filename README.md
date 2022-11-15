# Diz.Scraping
A PHP kit for sending a request and receiving a response over HTTP using the native cURL library.

Simple example:
```
$crawler = new Crawler('sample.com'); // Create a crawler instance with a target site domain - sample.com
$content = $crawler->get('test'); // get a page at https://sample.com/test

$extractor = new Extractor($content); // Create an extractor instance for the result content
$item = $extractor->extract($regexp); // Extract the desired element from the text using some regexp
```

Advanced example:
```
// Create a crawler instance and add a JSON pipe
// The JSON response will be automatically converted to an array
$crawler = (new Crawler('sample.com'))->addJSONPipe();
// Set cookies to be used in the upcoming request
$crawler->addSimpleCookie('user-id', '123');
// Add a permanent custom header data
$crawler->addHeader('Token', 'zxy123gh');

// Create a request instance with a target URL - https://sample.com/post
$request = $crawler->newRequest('post');
// Switch request to POST method and JSON body type
$request->toPOST($data, DataType::JSON);
// Add a custom header data only to this request
$request->addHeader('X-Custom-Data', 'Foo Bar');

$result = $request->send();
// Get actual cookies
$cookies = $crawler->obtainCookies();
```

Although the crawler can be used as is, a more appropriate solution is class inheritance:
```
class MyCrawler extends Crawler
{
    public function __construct()
	{
		parent::__construct('sample.com', 'my'); // https://my.sample.com
		$this->addJSONPipe();
		$this->addCallbackPipe([$this, 'checkResponse']);
	}
	
	// Add a callback pipe to check the status of the response from the server
	public function checkResponse(array $result): array
	{
		if (($result['status'] ?? null) !== 'ok') {
		   throw new \Exception($result['message'] ?? null); 
		}
		return $result['content'];
	}
	
	public function getItems(string $category): array
	{
	    return $this->get('items', ['category' => $category]);
	}
	
	// Listen the download event to prevent downloading erroneous files
	protected function onDownloadEvent(DownloadEvent $event): void
	{
		$response = $event->getResponse();

		$status_code = $response->getStatusCode();
		$size = $response->getDownloadSize();
		$destination = $event->getDestination();

		if ($status_code != 200 || $size == 0) {
			unlink($destination);
			if ($status_code == 404) {
				throw new \Exception('File not found.');
			} else {
			    throw new \Exception('File download error.');
			}
		}
    }
}
```