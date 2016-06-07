<?
	namespace CDN\Sync;

	class RemoteFile implements IRemoteFile {

		protected $remoteFileName;
		protected $url;

		public function __construct($remoteFileName, $url) {
			$this->remoteFileName = $remoteFileName;
			$this->url            = $url;
		}


		/**
		 * @return string
		 */
		public function getRemoteFileName() {
			return $this->remoteFileName;
		}

		/**
		 * @param string $remoteFileName
		 */
		public function setRemoteFileName($remoteFileName) {
			$this->remoteFileName = $remoteFileName;
		}

		/**
		 * @return string
		 */
		public function getUrl() {
			return $this->url;
		}

		/**
		 * @param string $url
		 */
		public function setUrl($url) {
			$this->url = $url;
		}



	}