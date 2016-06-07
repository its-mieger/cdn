<?
	namespace CDN\Sync;

	interface IRemoteFile
	{

		public function getRemoteFileName();

		public function getUrl();
	}