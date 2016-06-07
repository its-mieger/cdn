<?
	namespace CDNTest\Cases\Sync\Adapters\S3;

	use Aws\MockHandler;
	use Aws\S3\Exception\S3Exception;
	use Aws\S3\S3Client;
	use CDNTest\Mock\Aws\S3ServiceMock;
	use CDN\Sync\Adapters\S3\S3Adapter;

	class S3AdapterTest extends \PHPUnit_Framework_TestCase {

		/**
		 * @var MockHandler
		 */
		protected $mockHandler;
		protected $tmpKeyCacheFileName;

		protected function mockS3Client() {
			$this->mockHandler = new MockHandler();

			return new S3Client([
				'version'     => 'latest',
				'credentials' => [
					'key'    => '',
					'secret' => '',
				],
				'region'      => 'eu-central-1',
				'handler'     => $this->mockHandler
			]);
		}

		protected function tmpKeyCacheFile() {
			$this->tmpKeyCacheFileName = tempnam(sys_get_temp_dir(), 'phpunit');

			return $this->tmpKeyCacheFileName;
		}

		/**
		 * @after
		 */
		public function cleanTemporaryFiles() {
			if ($this->tmpKeyCacheFileName) {
				if (file_exists($this->tmpKeyCacheFileName))
					unlink($this->tmpKeyCacheFileName);

				$this->tmpKeyCacheFileName = null;
			}
		}


		public function testGetBucket() {

			$client    = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', []);

			$this->assertEquals('test-bucket', $adp->getBucket());
		}

		public function testGetDistributionUrls() {

			$client = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', ['url1/', 'url2']);

			$this->assertEquals(['url1/', 'url2/'], $adp->getDistributionURLs());
		}

		public function testGetAppendHash() {

			$client    = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', []);
			$adp->setAppendHash();

			$this->assertTrue($adp->getAppendHash());
		}

		public function testGetRootDir() {

			$client    = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', []);
			$adp->setRootDir('root/dir/');

			$this->assertEquals('root/dir', $adp->getRootDir());
		}

		public function testGetAdditionalMetaData() {

			$client    = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', []);
			$adp->setAdditionalMetaData(['meta1' => 'value1']);

			$this->assertEquals(['meta1' => 'value1'], $adp->getAdditionalMetaData());
		}

		public function testGetCacheControl() {

			$client    = $this->mockS3Client();

			$adp = new S3Adapter($client, 'test-bucket', []);
			$adp->setCacheControl('max-age=86400');

			$this->assertEquals('max-age=86400', $adp->getCacheControl());
		}

		public function testPublishFile() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileExisting() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectSuccess(
				'max-age=86400',
				'text/text',
				[
					'md5' => md5($fileContent),
					'created' => time(),
				],
				function ($params) {
					$this->assertEquals('test-bucket', $params['Bucket']);
					$this->assertEquals('the/test/key.txt', $params['Key']);
				}
			));

			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileExistingButDiffers() {

			$client    = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectSuccess(
				'max-age=86400',
				'text/text',
				[
					'md5'     => md5($fileContent . 'notTheSame'),
					'created' => time(),
				],
				function ($params) {
					$this->assertEquals('test-bucket', $params['Bucket']);
					$this->assertEquals('the/test/key.txt', $params['Key']);
				}
			));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));

			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileExistingForce() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));

			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text', true);

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileMd5Filename() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key_' . md5($fileContent) . '.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key_' . md5($fileContent) . '.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAppendHash(true);
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key_' . md5($fileContent) . '.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key_' . md5($fileContent) . '.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}


		public function testPublishFileMd5Existing() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectSuccess(
				'max-age=86400',
				'text/text',
				[
					'md5'     => md5($fileContent),
					'created' => time(),
				],
				function ($params) use ($fileContent) {
					$this->assertEquals('test-bucket', $params['Bucket']);
					$this->assertEquals('the/test/key_' . md5($fileContent) . '.txt', $params['Key']);
				}
			));

			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAppendHash(true);
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key_' . md5($fileContent) . '.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key_' . md5($fileContent) . '.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileRootDir() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('theRootDir/the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('theRootDir/the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setRootDir('theRootDir');
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileAdditionalMetaData() {

			$client    = $this->mockS3Client();
			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
				$this->assertEquals(1, $params['Metadata']['meta1']);
				$this->assertEquals('value', $params['Metadata']['meta2']);
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAdditionalMetaData(['meta1' => 1, 'meta2' => 'value']);
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileAdditionalMetaDataResolver() {

			$client    = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
				$this->assertEquals(1, $params['Metadata']['meta1']);
				$this->assertEquals('value', $params['Metadata']['meta2']);
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAdditionalMetaData(function($fn) {
				$this->assertEquals('the/test/key.txt', $fn);

				return ['meta1' => 1, 'meta2' => 'value'];
			});
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileAdditionalMetaDataExists() {

			$client    = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectSuccess(
				'max-age=86400',
				'text/text',
				[
					'md5'     => md5($fileContent),
					'created' => time(),
					'meta1'   => 1,
					'meta2'   => 'value',
				],
				function ($params) {
					$this->assertEquals('test-bucket', $params['Bucket']);
					$this->assertEquals('the/test/key.txt', $params['Key']);
				}
			));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAdditionalMetaData(['meta1' => 1, 'meta2' => 'value']);
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileAdditionalMetaDataExistsButDiffers() {

			$client    = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectSuccess(
				'max-age=86400',
				'text/text',
				[
					'md5'     => md5($fileContent),
					'created' => time(),
					'meta1'   => 2,
					'meta2'   => 'value2',
				],
				function ($params) {
					$this->assertEquals('test-bucket', $params['Bucket']);
					$this->assertEquals('the/test/key.txt', $params['Key']);
				}
			));

			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
				$this->assertEquals(1, $params['Metadata']['meta1']);
				$this->assertEquals('value', $params['Metadata']['meta2']);
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			$adp->setAdditionalMetaData(['meta1' => 1, 'meta2' => 'value']);
			$ret = $adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

			$this->assertEquals('the/test/key.txt', $ret->getRemoteFileName());
			$this->assertEquals('test.test.de/the/test/key.txt', $ret->getUrl());

			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testGetUrlShuffle() {
			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));
			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key2.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key2.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));
			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectSuccess(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));


			$adp = new S3Adapter($client, 'test-bucket', ['test1.test.de', 'test2.test.de', 'test3.test.de']);

			// add to inventory
			$this->assertEquals('test1.test.de/the/test/key.txt', $adp->pushFile('the/test/key.txt', $fileContent, 'text/text')->getUrl());
			$this->assertEquals('test3.test.de/the/test/key2.txt', $adp->pushFile('the/test/key2.txt', $fileContent, 'text/text')->getUrl());
			$this->assertEquals('test1.test.de/the/test/key.txt', $adp->pushFile('the/test/key.txt', $fileContent, 'text/text')->getUrl());


			$this->assertEquals(0, $this->mockHandler->count());
		}

		public function testPublishFileFail() {

			$client = $this->mockS3Client();

			$fileContent = 'the-content';

			$this->mockHandler->append(S3ServiceMock::headObjectFail(function ($params) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
			}));
			$this->mockHandler->append(S3ServiceMock::putObjectFail(function ($params) use ($fileContent) {
				$this->assertEquals('test-bucket', $params['Bucket']);
				$this->assertEquals('the/test/key.txt', $params['Key']);
				$this->assertEquals($fileContent, $params['Body']);
				$this->assertEquals('text/text', $params['ContentType']);
				$this->assertEquals('max-age=86400', $params['CacheControl']);
				$this->assertEquals('public-read', $params['ACL']);
				$this->assertEquals(md5($fileContent), $params['Metadata']['md5']);
				$this->assertGreaterThan(time() - 5, strtotime($params['Metadata']['created']));
				$this->assertLessThanOrEqual(time(), strtotime($params['Metadata']['created']));
			}));


			// publish file
			$adp = new S3Adapter($client, 'test-bucket', ['test.test.de']);
			$adp->setCacheControl('max-age=86400');
			try {
				$adp->pushFile('the/test/key.txt', $fileContent, 'text/text');

				$this->fail('Expected S3Exception here');
			}
			catch (S3Exception $ex) {
				$this->assertTrue(true);
			}

			$this->assertEquals(0, $this->mockHandler->count());
		}

//		public function testGetUrl() {
//			$client = $this->mockS3Client();
//			$inventory = new MockInventory();
//
//			$adp = new S3Adapter($client, 'test-bucket', 'test.test.de', $inventory);
//
//			// add to inventory
//			$inventory->put('the/test/key.txt', 'remote/file');
//
//			// get url
//			$this->assertEquals('test.test.de/remote/file', $adp->getUrl('the/test/key.txt'));
//
//			$this->assertEquals(0, $this->mockHandler->count());
//		}
//
//
//		public function testGetUrlShuffle() {
//			$client = $this->mockS3Client();
//			$inventory = new MockInventory();
//
//
//
//			$adp = new S3Adapter($client, 'test-bucket', ['test1.test.de', 'test2.test.de', 'test3.test.de'], $inventory);
//
//			// add to inventory
//			$inventory->put('the/test/key1.txt', 'the/test/key1.txt');
//			$inventory->put('the/test/key2.txt', 'the/test/key2.txt');
//			$inventory->put('the/test/key3.txt', 'the/test/key3.txt');
//
//			// get url
//
//			$urls = [
//				$adp->getUrl('the/test/key1.txt'),
//				$adp->getUrl('the/test/key1.txt'),
//				$adp->getUrl('the/test/key2.txt'),
//				$adp->getUrl('the/test/key3.txt'),
//				$adp->getUrl('the/test/key1.txt'),
//				$adp->getUrl('the/test/key1.txt'),
//				$adp->getUrl('the/test/key1.txt'),
//			];
//
//			// assert same url for same file
//			$this->assertEquals($urls[0], $urls[1]);
//			$this->assertEquals($urls[0], $urls[4]);
//			$this->assertEquals($urls[0], $urls[5]);
//			$this->assertEquals($urls[0], $urls[6]);
//
//			// assert multiple sub domains used
//			$subDomains = [];
//			foreach($urls as $curr) {
//				$sd = substr($curr, 0, 5);
//				if (!in_array($sd, $subDomains))
//					$subDomains[] = $sd;
//			}
//			$this->assertGreaterThan(1, count($subDomains));
//
//			$this->assertEquals(0, $this->mockHandler->count());
//		}
//
//		public function testGetUrlFail() {
//			$client = $this->mockS3Client();
//
//			$adp = new S3Adapter($client, 'test-bucket', 'test.test.de', new MockInventory());
//
//			try {
//				$adp->getUrl('the/test/not-existing.txt');
//
//				$this->fail('Expected NotPublishedException here');
//			}
//			catch (NotPublishedException $ex) {
//				$this->assertTrue(true);
//			}
//
//			$this->assertEquals(0, $this->mockHandler->count());
//		}



	}