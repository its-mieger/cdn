<?
	namespace CDN\Sync;

	interface ICdnAdapter {

		/**
		 * Creates an adapter instance from a config array
		 * @param array $configArray The config array
		 * @return self
		 */
		public static function fromConfig(array $configArray);


		/**
		 * Pushes the specified file to the CDN storage
		 * @param string $filename The file name
		 * @param string $content The file content
		 * @param string $contentType The mime content type
		 * @param bool $forceUpdate True to force file update even if hash matches current version
		 * @return IRemoteFile
		 */
		public function pushFile($filename, $content, $contentType, $forceUpdate = false);

	}
