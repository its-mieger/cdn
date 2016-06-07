<?php

	namespace CDNTest\Mock\Aws;

	use Aws\CommandInterface;
	use Aws\S3\Exception\S3Exception;

	class S3ServiceMock
	{

		public static function putObjectSuccess(callable $paramsValidator = null) {
			return function (CommandInterface $cmd) use ($paramsValidator) {
				if ($cmd->getName() != 'PutObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if (!empty($paramsValidator))
					call_user_func($paramsValidator, $params);

				// incomplete return, but sufficient for the tests
				return new \Aws\Result([
					'ETag'       => '',
					'Expiration' => '',
					'ObjectURL'  => 'http://s3.test.de/' . $params['Key'],
					'VersionId'  => '',
				]);
			};
		}

		public static function putObjectFail(callable $paramsValidator = null) {
			return function (CommandInterface $cmd) use ($paramsValidator) {
				if ($cmd->getName() != 'PutObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if (!empty($paramsValidator))
					call_user_func($paramsValidator, $params);

				throw new S3Exception('Mocked exception', $cmd, ['code' => null]);
			};
		}

		public static function getObjectSuccess($body, $key = null, $bucket = null, $acl = null, $bodyMd5 = null) {
			return function (CommandInterface $cmd) use ($body, $key, $bucket, $acl, $bodyMd5) {
				if ($cmd->getName() != 'GetObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if ($key && $key != $params['Key'])
					throw new \RuntimeException('Expected key "' . $key . '" but got "' . $params['Key'] . '"');
				if ($bucket && $bucket != $params['Bucket'])
					throw new \RuntimeException('Expected bucket "' . $bucket . '" but got "' . $params['Bucket'] . '"');
				if ($acl && $acl != $params['ACL'])
					throw new \RuntimeException('Expected ACL "' . $acl . '" but got "' . $params['ACL'] . '"');
				if ($bodyMd5 && $bodyMd5 != md5($params['Body']))
					throw new \RuntimeException('Expected body checksum not matching (' . $params['Body'] . ')');


				// incomplete return, but sufficient for the tests
				return new \Aws\Result([
					'Body'       => $body,
					'Expiration' => '',
					'ObjectURL'  => 'http://s3.test.de/' . $key,
					'VersionId'  => '',
				]);
			};
		}

		public static function getObjectFail($key = null, $bucket = null, $acl = null, $bodyMd5 = null) {
			return function (CommandInterface $cmd) use ($key, $bucket, $acl, $bodyMd5) {
				if ($cmd->getName() != 'GetObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if ($key && $key != $params['Key'])
					throw new \RuntimeException('Expected key "' . $key . '" but got "' . $params['Key'] . '"');
				if ($bucket && $bucket != $params['Bucket'])
					throw new \RuntimeException('Expected bucket "' . $bucket . '" but got "' . $params['Bucket'] . '"');
				if ($acl && $acl != $params['ACL'])
					throw new \RuntimeException('Expected ACL "' . $acl . '" but got "' . $params['ACL'] . '"');
				if ($bodyMd5 && $bodyMd5 != md5($params['Body']))
					throw new \RuntimeException('Expected body checksum not matching (' . $params['Body'] . ')');


				throw new S3Exception('Mocked exception', $cmd, ['code' => null]);
			};
		}

		public static function deleteObjectSuccess($keyPrefix = null, $bucket = null) {
			return function (CommandInterface $cmd) use ($keyPrefix, $bucket) {
				if ($cmd->getName() != 'DeleteObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if ($keyPrefix && substr($params['Key'], 0, strlen($keyPrefix)) != $keyPrefix)
					throw new \RuntimeException('Expected key to start with"' . $keyPrefix . '" but got "' . $params['Key'] . '"');
				if ($bucket && $bucket != $params['Bucket'])
					throw new \RuntimeException('Expected bucket "' . $bucket . '" but got "' . $params['Bucket'] . '"');


				// incomplete return, but sufficient for the tests
				return new \Aws\Result([]);
			};
		}

		public static function deleteObjectFail($keyPrefix = null, $bucket = null) {
			return function (CommandInterface $cmd) use ($keyPrefix, $bucket) {
				if ($cmd->getName() != 'DeleteObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if ($keyPrefix && substr($params['Key'], 0, strlen($keyPrefix)) != $keyPrefix)
					throw new \RuntimeException('Expected key to start with"' . $keyPrefix . '" but got "' . $params['Key'] . '"');
				if ($bucket && $bucket != $params['Bucket'])
					throw new \RuntimeException('Expected bucket "' . $bucket . '" but got "' . $params['Bucket'] . '"');


				throw new S3Exception('Mocked exception', $cmd, ['code' => null]);
			};
		}

		public static function headObjectSuccess($cacheControl, $contentType, $metaData = [], callable $paramsValidator = null) {
			return function (CommandInterface $cmd) use ($cacheControl, $contentType, $metaData, $paramsValidator) {
				if ($cmd->getName() != 'HeadObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if (!empty($paramsValidator))
					call_user_func($paramsValidator, $params);

				// incomplete return, but sufficient for the tests
				return new \Aws\Result([
					'CacheControl' => $cacheControl,
					'ContentType' => $contentType,
					'Metadata' => $metaData,
					'Expiration' => '',
					'VersionId'  => '',
				]);
			};
		}

		public static function headObjectFail(callable $paramsValidator = null) {
			return function (CommandInterface $cmd) use ($paramsValidator) {
				if ($cmd->getName() != 'HeadObject')
					throw new \RuntimeException('Command call ' . $cmd->getName() . ' not mocked');

				$params = $cmd->toArray();

				if (!empty($paramsValidator))
					call_user_func($paramsValidator, $params);


				throw new S3Exception('Mocked exception', $cmd, ['code' => 'NotFound']);
			};
		}
	}