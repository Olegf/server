<?php
/**
 * 
 * @service playReadyDrm
 * @package plugins.playReady
 * @subpackage api.services
 */
class PlayReadyDrmService extends KalturaBaseService
{	
	const PLAY_READY_BEGIN_DATE_PARAM = 'playReadyBeginDate';
	const PLAY_READY_EXPIRATION_DATE_PARAM = 'playReadyExpirationDate';
	
	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);
		if (!PlayReadyPlugin::isAllowedPartner($this->getPartnerId()))
			throw new KalturaAPIException(KalturaErrors::SERVICE_FORBIDDEN, $this->serviceName.'->'.$this->actionName);
			
		$this->applyPartnerFilterForClass('DrmPolicy');
		$this->applyPartnerFilterForClass('DrmProfile');	
		$this->applyPartnerFilterForClass('entry');
	}
	
	/**
	 * Generate key id and content key for PlayReady encryption
	 * 
	 * @action generateKey 
	 * @return KalturaPlayReadyContentKey $response
	 * 
	 */
	public function generateKeyAction()
	{
		$keySeed = $this->getPartnerKeySeed();
		$keyId = kPlayReadyAESContentKeyGenerator::generatePlayReadyKeyId();		
		$contentKey = $this->createContentKeyObject($keySeed, $keyId);
		$response = new KalturaPlayReadyContentKey();
		$response->fromObject($contentKey);
		return $response;
	}
	
	/**
	 * Get content keys for input key ids
	 * 
	 * @action getContentKeys
	 * @param string $keyIds - comma separated key id's 
	 * @return KalturaPlayReadyContentKeyArray $response
	 * 
	 */
	public function getContentKeysAction($keyIds)
	{
		$keySeed = $this->getPartnerKeySeed();
		$contentKeysArr = array();
		$keyIdsArr = explode(',', $keyIds);
		foreach ($keyIdsArr as $keyId) 
		{
			$contentKeysArr[] = $this->createContentKeyObject($keySeed, $keyId);
		}	
		$response = KalturaPlayReadyContentKeyArray::fromDbArray($contentKeysArr);	
		return $response;
	}

	/**
	 * Get content key and key id for the given entry
	 * 
	 * @action getEntryContentKey
	 * @param string $entryId 
	 * @return KalturaPlayReadyContentKey $response
	 * 
	 */
	public function getEntryContentKeyAction($entryId)
	{
		$entry = entryPeer::retrieveByPK($entryId);
		if(!$entry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		$keySeed = $this->getPartnerKeySeed();
		$keyId = $entry->getFromCustomData("playready_key_id");
		$contentKey = $this->createContentKeyObject($keySeed, $keyId);
		$response = new KalturaPlayReadyContentKey();
		$response->fromObject($contentKey);
		
		return $response;				
	}
		
	/**
	 * Get Play Ready policy and dates for license creation
	 * 
	 * @action getLicenseDetails
	 * @param string $keyId
	 * @param string $entryId
	 * @param string $referrer 64base encoded  
	 * @return KalturaPlayReadyLicenseDetails $response
	 * 
	 * @throws KalturaErrors::MISSING_MANDATORY_PARAMETER
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaPlayReadyErrors::ENTRY_NOT_FOUND_BY_KEY_ID
	 * @throws KalturaPlayReadyErrors::PLAYREADY_POLICY_NOT_FOUND
	 */
	public function getLicenseDetailsAction($keyId, $entryId = null, $referrer = null)
	{
		KalturaLog::debug('Get Play Ready license details for keyID: '.$keyId);
		$entry = $this->getLicenseRequestEntry($keyId, $entryId);		
		
		$policyId = $this->validateAccessControl($entry, $referrer); //TODO: check what policy returned for admin
		$dbPolicy = DrmPolicyPeer::retrieveByPK($policyId);
		if(!$dbPolicy)
			throw new KalturaAPIException(KalturaPlayReadyErrors::PLAYREADY_POLICY_OBJECT_NOT_FOUND, $policyId);
			
		list($beginDate, $expirationDate, $removalDate) = $this->calculateLicenseDates($dbPolicy, $entry);
		
		$policy = new KalturaPlayReadyPolicy();
		$policy->fromObject($dbPolicy);
		
		$response = new KalturaPlayReadyLicenseDetails();
		$response->policy = $policy;
		$response->beginDate = $beginDate;
		$response->expirationDate = $expirationDate;
		$response->removalDate = $removalDate;
		
		return $response;
	}
	
	private function validateAccessControl(entry $entry, $referrer64base)
	{
		KalturaLog::debug("Validating access control");
		
		$referrer = base64_decode(str_replace(" ", "+", $referrer64base));
		if (!is_string($referrer))
			$referrer = ""; // base64_decode can return binary data		
			
		$secureEntryHelper = new KSecureEntryHelper($entry, kCurrentContext::$ks, $referrer, ContextType::PLAY);
		$secureEntryHelper->validateForPlay();
		$actions = $secureEntryHelper->getContextResult()->getActions();
		foreach($actions as $action)
		{
			if($action instanceof kAccessControlPlayReadyPolicyAction && $action->getPolicyId())
				return $action->getPolicyId();
		}
		
		throw new KalturaAPIException(KalturaPlayReadyErrors::PLAYREADY_POLICY_NOT_FOUND, $entry->getId());
	}
	
	private function getLicenseRequestEntry($keyId, $entryId = null)
	{
		$entry = null;
		
		if(!$keyId)
			throw new KalturaAPIException(KalturaErrors::MISSING_MANDATORY_PARAMETER, "keyId");
		
		if($entryId)
		{
			 $entry = entryPeer::retrieveByPK($entryId); 
			 if(!$entry)
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);	
			//TODO: validate key id on the entry is the same as the input		 
		}
		else 
		{
			$entryFilter = new entryFilter();
			$entryFilter->fields['_like_plugins_data'] = PlayReadyPlugin::getPlayReadyKeyIdSearchData($keyId);
			$entryFilter->setPartnerSearchScope(baseObjectFilter::MATCH_KALTURA_NETWORK_AND_PRIVATE);
			$c = KalturaCriteria::create(entryPeer::OM_CLASS);				
			$entryFilter->attachToCriteria($c);	
			$c->applyFilters();
			$entries = entryPeer::doSelect($c);
		
			if($entries && count($entries) > 0)
				$entry = $entries[0];
			if(!$entry)
				throw new KalturaAPIException(KalturaPlayReadyErrors::ENTRY_NOT_FOUND_BY_KEY_ID, $keyId);			 				
		}
		
		return $entry;
	}
	
	private function getPartnerKeySeed()
	{
		$partnerId = kCurrentContext::$partner_id ? kCurrentContext::$partner_id : kCurrentContext::$ks_partner_id;
		$profile = DrmProfilePeer::retrieveByProvider(PlayReadyPlugin::getPlayReadyProviderCoreValue());
		if(!$profile)
			throw new KalturaAPIException(KalturaPlayReadyErrors::PLAYREADY_PROFILE_NOT_FOUND);
		return $profile->getKeySeed();
	}
	
	private function createContentKeyObject($keySeed, $keyId)
	{
		if(!$keyId)
			throw new KalturaAPIException(KalturaErrors::MISSING_MANDATORY_PARAMETER, "keyId");
			
		$contentKeyVal = kPlayReadyAESContentKeyGenerator::generatePlayReadyContentKey($keySeed, $keyId);
		$contentKey = new PlayReadyContentKey();
		$contentKey->setKeyId($keyId);
		$contentKey->setContentKey($contentKeyVal);	

		return $contentKey;
	}
	
	private function calculateLicenseDates(PlayReadyPolicy $policy, entry $entry)
	{
		$beginDate = time();
		$expirationDate = null;
		$removalDate = null;
	
		switch($policy->getLicenseExpirationPolicy())
		{
			case DrmLicenseExpirationPolicy::FIXED_DURATION:
				$expirationDate = $beginDate + dateUtils::DAY*$policy->getDuration();
				break;
			case DrmLicenseExpirationPolicy::ENTRY_SCHEDULING_END:
				$expirationDate = $entry->getEndDate();
				break;
		}
		
		switch($policy->getLicenseRemovalPolicy())
		{
			case PlayReadyLicenseRemovalPolicy::FIXED_FROM_EXPIRATION:
				$removalDate = $expirationDate + dateUtils::DAY*$policy->getLicenseRemovalDuration();
				break;
			case PlayReadyLicenseRemovalPolicy::ENTRY_SCHEDULING_END:
				$removalDate = $entry->getEndDate();
				break;
		}
			
		//override begin and expiration dates from ks if passed
		if(kCurrentContext::$ks_object)
		{
			$privileges = kCurrentContext::$ks_object->getPrivileges();
			$allParams = explode(',', $privileges);
			foreach($allParams as $param)
			{
				$exParam = explode(':', $param);
				if ($exParam[0] == self::PLAY_READY_BEGIN_DATE_PARAM)
					$beginDate = $exParam[1];
				if ($exParam[0] == self::PLAY_READY_EXPIRATION_DATE_PARAM)
					$expirationDate = $exParam[1];
			}				
		}
				
		return array($beginDate, $expirationDate, $removalDate);		
	}
}
