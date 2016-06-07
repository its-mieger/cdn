<?
	namespace CDNTest\Cases\Sync\Adapters\S3;


	use Aws\MockHandler;
	use CDN\CDN;
	use CDN\Inventory\PhpInventoryFile;
	use CDN\Script\SyncScript;
	use CDN\Sync\RemoteFile;
	use CDNTest\Mock\CdnAdapterMock;

	class SyncScriptTest extends \PHPUnit_Framework_TestCase
	{

		/**
		 * @var MockHandler
		 */
		protected $mockHandler;
		protected $tmpKeyCacheFileName;

		/**
		 * @before
		 */
		protected function initMockHandler() {
			$this->mockHandler = new MockHandler();
		}

		public function testRun1() {

			CdnAdapterMock::append('fromConfig', function($args) {
				$this->assertEquals([
					'adapter'    => 'Mock',
					'aws'        => [
						'region'      => 'eu-west-1',
						'credentials' => [
							'key'    => '',
							'secret' => ''
						]
					],
					'bucket'     => 'test-bucket',
					'url'        => 'test.test.de',
					'target-dir' => 'img'
				], $args);
			});
			CdnAdapterMock::append('pushFile', function($filename, $content, $contentType, $forceUpdate) {
				$this->assertEquals('test.txt', $filename);
				$this->assertEquals('this-is-the-test', $content);
				$this->assertEquals('text/plain', $contentType);
				$this->assertFalse($forceUpdate);
			});
			CdnAdapterMock::append('fromConfig', function ($args) {
				$this->assertEquals([
					'adapter'    => 'Mock',
					'aws'        => [
						'region'      => 'eu-west-1',
						'credentials' => [
							'key'    => '',
							'secret' => ''
						]
					],
					'bucket'     => 'test-bucket',
					'url'        => 'test.test.de',
					'target-dir' => 'css',
					'appendHash' => true,
				], $args);
			});
			CdnAdapterMock::append('pushFile', function ($filename, $content, $contentType, $forceUpdate) {
				$this->assertEquals('sub/test2.css', $filename);
				$this->assertEquals('body {  background-color: white;  }', $content);
				$this->assertEquals('text/css', $contentType);
				$this->assertFalse($forceUpdate);
			});
			CdnAdapterMock::append('pushFile', function ($filename, $content, $contentType, $forceUpdate) {
				$this->assertEquals('sub/test.css', $filename);
				$this->assertEquals('body {background-color: red;}', $content);
				$this->assertEquals('text/css', $contentType);
				$this->assertFalse($forceUpdate);
			});


			SyncScript::run(dirname(__FILE__) . '/../../Data/test1/cdn.json');


			// inventory exists
			$inventoryFile = dirname(__FILE__) . '/../../Data/test1/vendor/cdn/cdn_inventory.php';
			$this->assertTrue(file_exists($inventoryFile));

			// check inventory content
			$iv = new PhpInventoryFile($inventoryFile);
			$this->assertEquals('test.test.de/test.txt', $iv->getUrl('img/test.txt'));
			$this->assertEquals('test.test.de/sub/test.css', $iv->getUrl('css/sub/test.css'));
			$this->assertEquals('test.test.de/sub/test2.css', $iv->getUrl('css/sub/test2.css'));


			// check cdn.php
			$cdnInclude = dirname(__FILE__) . '/../../Data/test1/vendor/cdn.php';
			$this->assertTrue(file_exists($cdnInclude));

			$cmd = "\\" . get_class(new CDN()) . '::loadInventory(new \\' . get_class(new PhpInventoryFile('')) . '(__DIR__ . \'/cdn/cdn_inventory.php\'));';
			$this->assertContains($cmd, file_get_contents($cdnInclude));

		}

		public function testRun2() {

			CdnAdapterMock::append('fromConfig', function ($args) {
				$this->assertEquals([
					'adapter'    => 'Mock',
					'aws'        => [
						'region'      => 'eu-west-1',
						'credentials' => [
							'key'    => '',
							'secret' => ''
						]
					],
					'bucket'     => 'test-bucket',
					'url'        => ['test1.test.de/webroot/img/', 'test2.test.de/webroot/img/'],
					'target-dir' => 'webroot/img',
				], $args);
			});
			CdnAdapterMock::append('pushFile', function ($filename, $content, $contentType, $forceUpdate) {
				$this->assertEquals('test.txt', $filename);
				$this->assertEquals('this-is-the-test', $content);
				$this->assertEquals('text/plain', $contentType);
				$this->assertFalse($forceUpdate);

				return new RemoteFile('webroot/img/test.txt', 'test1.test.de/webroot/img/test.txt');
			});
			CdnAdapterMock::append('fromConfig', function ($args) {
				$this->assertEquals([
					'adapter'    => 'Mock',
					'aws'        => [
						'region'      => 'eu-west-1',
						'credentials' => [
							'key'    => '',
							'secret' => ''
						]
					],
					'bucket'     => 'test-bucket',
					'url'        => ['test1.test.de', 'test2.test.de'],
					'target-dir' => 'webroot/css/sub',
				], $args);
			});
			CdnAdapterMock::append('pushFile', function ($filename, $content, $contentType, $forceUpdate) {
				$this->assertEquals('test2.css', $filename);
				$this->assertEquals('body {  background-color: white;  }', $content);
				$this->assertEquals('text/css', $contentType);
				$this->assertFalse($forceUpdate);

				return new RemoteFile('webroot/css/sub/test2.css', 'test.test.de/test2.css');
			});


			SyncScript::run(dirname(__FILE__) . '/../../Data/test2/cdn.json');


			// inventory exists
			$inventoryFile = dirname(__FILE__) . '/../../Data/test2/vendor/cdn/cdn_inventory.php';
			$this->assertTrue(file_exists($inventoryFile));

			// check inventory content
			$iv = new PhpInventoryFile($inventoryFile);
			$this->assertEquals('test1.test.de/webroot/img/test.txt', $iv->getUrl('webroot/img/test.txt'));
			$this->assertEquals('test.test.de/test2.css', $iv->getUrl('webroot/css/sub/test2.css'));
			$this->assertFalse($iv->getUrl('webroot/css/test.css'));


			// check cdn.php
			$cdnInclude = dirname(__FILE__) . '/../../Data/test2/vendor/cdn.php';
			$this->assertTrue(file_exists($cdnInclude));

			$cmd = "\\" . get_class(new CDN()) . '::loadInventory(new \\' . get_class(new PhpInventoryFile('')) . '(__DIR__ . \'/cdn/cdn_inventory.php\'));';
			$this->assertContains($cmd, file_get_contents($cdnInclude));

		}
	}