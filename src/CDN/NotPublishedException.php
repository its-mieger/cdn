<?
	namespace CDN;

	use Exception;

	class NotPublishedException extends \Exception {

		protected $cdnFile;

		public function __construct($cdnFile, $message = "", $code = 0, Exception $previous = null) {

			$this->cdnFile = $cdnFile;

			if (empty($message))
				$message = 'File "' . $cdnFile . '" not published to CDN';

			parent::__construct($message, $code, $previous);
		}

		/**
		 * @return string
		 */
		public function getCdnFile() {
			return $this->cdnFile;
		}
	}