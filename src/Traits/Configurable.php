<?php

namespace Cerbaro\LaravelGmail\Traits;

use Google\Service\Gmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

/**
 * Trait Configurable
 * @package Cerbaro\LaravelGmail\Traits
 */
trait Configurable
{

	protected $additionalScopes = [];
	private $_config;

	public function __construct($config)
	{
		$this->_config = $config;
	}

	public function config($string = null)
	{
		$disk = Storage::disk('local');
		$fileName = $this->getFileName();
		$file = "gmail/tokens/$fileName.json";
		$allowJsonEncrypt = $this->_config['gmail.allow_json_encrypt'];

		if ($disk->exists($file)) {
			if ($allowJsonEncrypt) {
				$config = json_decode(decrypt($disk->get($file)), true);
			} else {
				$config = json_decode($disk->get($file), true);
			}

			if ($string) {
				if (isset($config[$string])) {
					return $config[$string];
				}
			} else {
				return $config;
			}
		}

		return null;
	}

	private function getFileName()
	{
		if (property_exists(get_class($this), 'userId') && $this->userId) {
			$userId = $this->userId;
		} elseif (auth()->user()) {
			$userId = auth()->user()->id;
		}

		$credentialFilename = $this->_config['gmail.credentials_file_name'];
		$allowMultipleCredentials = $this->_config['gmail.allow_multiple_credentials'];

		if (isset($userId) && $allowMultipleCredentials) {
			return sprintf('%s-%s', $credentialFilename, $userId);
		}

		return $credentialFilename;
	}

	/**
	 * @return array
	 */
	public function getConfigs()
	{
		return [
			'client_secret' => $this->_config['gmail.client_secret'],
			'client_id' => $this->_config['gmail.client_id'],
			'redirect_uri' => url($this->_config['gmail.redirect_url']),
			'state' => isset($this->_config['gmail.state']) ? $this->_config['gmail.state'] : null,
		];
	}

	public function setAdditionalScopes(array $scopes)
	{
		$this->additionalScopes = $scopes;

		return $this;
	}

	private function configApi()
	{
		$type = $this->_config['gmail.access_type'];
		$approval_prompt = $this->_config['gmail.approval_prompt'];

		$this->setScopes($this->getUserScopes());

		$this->setAccessType($type);

		$this->setApprovalPrompt($approval_prompt);
	}

	public abstract function setScopes($scopes);

	private function getUserScopes()
	{
		return $this->mapScopes();
	}

	private function mapScopes()
	{
		$scopes = array_merge($this->_config['gmail.scopes'] ?? [], $this->additionalScopes);
		$scopes = array_unique(array_filter($scopes));
		$mappedScopes = [];

		if (!empty($scopes)) {
			foreach ($scopes as $scope) {
				$mappedScopes[] = $this->scopeMap($scope);
			}
		}

		return array_merge($mappedScopes, $this->_config['gmail.additional_scopes'] ?? []);
	}

	private function scopeMap($scope)
	{
		$scopes = [
			'all' => Gmail::MAIL_GOOGLE_COM,
			'compose' => Gmail::GMAIL_COMPOSE,
			'insert' => Gmail::GMAIL_INSERT,
			'labels' => Gmail::GMAIL_LABELS,
			'metadata' => Gmail::GMAIL_METADATA,
			'modify' => Gmail::GMAIL_MODIFY,
			'readonly' => Gmail::GMAIL_READONLY,
			'send' => Gmail::GMAIL_SEND,
			'settings_basic' => Gmail::GMAIL_SETTINGS_BASIC,
			'settings_sharing' => Gmail::GMAIL_SETTINGS_SHARING,
		];

		return Arr::get($scopes, $scope);
	}

	public abstract function setAccessType($type);

	public abstract function setApprovalPrompt($approval);
}
