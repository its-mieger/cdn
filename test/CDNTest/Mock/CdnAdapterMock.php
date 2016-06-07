<?php

	namespace CDNTest\Mock;

	use CDN\Sync\ICdnAdapter;
	use CDN\Sync\IRemoteFile;
	use CDN\Sync\RemoteFile;

	class CdnAdapterMock implements ICdnAdapter
	{
		protected static $mockQueue = [];


		public static function append($call, callable $fn = null) {
			self::$mockQueue[] = [
				'call' => $call,
				'fn' => $fn
			];
		}

		protected static function callbackMock($call, $arguments) {
			$entry = array_shift(self::$mockQueue);

			if (!$entry)
				throw new \RuntimeException('Mock queue is empty');

			if ($call != $entry['call'])
				throw new \RuntimeException('Command call ' . $call . ' not mocked');

			if ($entry['fn'])
				return call_user_func_array($entry['fn'], $arguments);

			return null;
		}

		/**
		 * Creates an adapter instance from a config array
		 * @param array $configArray The config array
		 * @return self
		 */
		public static function fromConfig(array $configArray) {
			$ret = self::callbackMock(__FUNCTION__, func_get_args());

			if (!empty($ret))
				return $ret;

			return new self();
		}

		/**
		 * Pushes the specified file to the CDN storage
		 * @param string $filename The file name
		 * @param string $content The file content
		 * @param string $contentType The mime content type
		 * @param bool $forceUpdate True to force file update even if hash matches current version
		 * @return IRemoteFile
		 */
		public function pushFile($filename, $content, $contentType, $forceUpdate = false) {

			$ret = self::callbackMock(__FUNCTION__, func_get_args());

			if (!empty($ret))
				return $ret;

			return new RemoteFile($filename, 'test.test.de/' . $filename);
		}


	}