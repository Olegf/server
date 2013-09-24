<?php
/**
 * 
 * @service drmProfile
 * @package plugins.drm
 * @subpackage api.services
 */
class DrmProfileService extends KalturaBaseService
{
	const MYSQL_CODE_DUPLICATE_KEY = 23000;
	
	public function initService($serviceId, $serviceName, $actionName)
	{
		parent::initService($serviceId, $serviceName, $actionName);
		if (!DrmPlugin::isAllowedPartner($this->getPartnerId()))
			throw new KalturaAPIException(KalturaErrors::SERVICE_FORBIDDEN, $this->serviceName.'->'.$this->actionName);
			
		$this->applyPartnerFilterForClass('DrmProfile');
	}
	
	/**
	 * Allows you to add a new DrmProfile object
	 * 
	 * @action add
	 * @param KalturaDrmProfile $drmProfile
	 * @return KalturaDrmProfile
	 * 
	 * @throws KalturaErrors::PLUGIN_NOT_AVAILABLE_FOR_PARTNER
	 * @throws KalturaErrors::INVALID_PARTNER_ID
	 * @throws KalturaErrors::PROPERTY_VALIDATION_CANNOT_BE_NULL
	 * @throws DrmErrors::ACTIVE_PROVIDER_PROFILE_ALREADY_EXIST
	 */
	public function addAction(KalturaDrmProfile $drmProfile)
	{
		// check for required parameters
		$drmProfile->validatePropertyNotNull('name');
		$drmProfile->validatePropertyNotNull('status');
		$drmProfile->validatePropertyNotNull('provider');
		$drmProfile->validatePropertyNotNull('partnerId');
		
		// validate values						
		if (!PartnerPeer::retrieveByPK($drmProfile->partnerId)) {
			throw new KalturaAPIException(KalturaErrors::INVALID_PARTNER_ID, $drmProfile->partnerId);
		}
		
		if (!DrmPlugin::isAllowedPartner($drmProfile->partnerId))
		{
			throw new KalturaAPIException(KalturaErrors::PLUGIN_NOT_AVAILABLE_FOR_PARTNER, DrmPlugin::getPluginName(), $drmProfile->partnerId);
		}

		// save in database
		$dbDrmProfile = $drmProfile->toInsertableObject();
		try 
		{	
			$dbDrmProfile->save();
		}
		catch(PropelException $e)
		{
			if($e->getCause()->getCode() == self::MYSQL_CODE_DUPLICATE_KEY) //unique constraint
				throw new KalturaAPIException(DrmErrors::ACTIVE_PROVIDER_PROFILE_ALREADY_EXIST, $drmProfile->provider);
		}
		// return the saved object
		$drmProfile = KalturaDrmProfile::getInstanceByType($dbDrmProfile->getProvider());
		$drmProfile->fromObject($dbDrmProfile);
		return $drmProfile;		
	}
	
	/**
	 * Retrieve a KalturaDrmProfile object by ID
	 * 
	 * @action get
	 * @param int $drmProfileId 
	 * @return KalturaDrmProfile
	 * 
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 */		
	public function getAction($drmProfileId)
	{
		$dbDrmProfile = DrmProfilePeer::retrieveByPK($drmProfileId);
		
		if (!$dbDrmProfile) {
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $drmProfileId);
		}
		$drmProfile = KalturaDrmProfile::getInstanceByType($dbDrmProfile->getProvider());
		$drmProfile->fromObject($dbDrmProfile);
		
		return $drmProfile;
	}
	

	/**
	 * Update an existing KalturaDrmProfile object
	 * 
	 * @action update
	 * @param int $drmProfileId
	 * @param KalturaDrmProfile $drmProfile
	 * @return KalturaDrmProfile
	 *
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 * @throws DrmErrors::ACTIVE_PROVIDER_PROFILE_ALREADY_EXIST
	 */	
	public function updateAction($drmProfileId, KalturaDrmProfile $drmProfile)
	{
		$dbDrmProfile = DrmProfilePeer::retrieveByPK($drmProfileId);
		
		if (!$dbDrmProfile) {
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $drmProfileId);
		}
								
		$dbDrmProfile = $drmProfile->toUpdatableObject($dbDrmProfile);
		try 
		{	
			$dbDrmProfile->save();
		}
		catch(PropelException $e)
		{
			if($e->getCause()->getCode() == self::MYSQL_CODE_DUPLICATE_KEY) //unique constraint
				throw new KalturaAPIException(DrmErrors::ACTIVE_PROVIDER_PROFILE_ALREADY_EXIST, $drmProfile->provider);
		}
			
		$drmProfile = KalturaDrmProfile::getInstanceByType($dbDrmProfile->getProvider());
		$drmProfile->fromObject($dbDrmProfile);
		
		return $drmProfile;
	}

	/**
	 * Mark the KalturaDrmProfile object as deleted
	 * 
	 * @action delete
	 * @param int $drmProfileId 
	 * @return KalturaDrmProfile
	 *
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 */		
	public function deleteAction($drmProfileId)
	{
		$dbDrmProfile = DrmProfilePeer::retrieveByPK($drmProfileId);
		
		if (!$dbDrmProfile) {
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $drmProfileId);
		}

		$dbDrmProfile->setStatus(DrmProfileStatus::DELETED);
		$dbDrmProfile->save();
			
		$drmProfile = KalturaDrmProfile::getInstanceByType($dbDrmProfile->getProvider());
		$drmProfile->fromObject($dbDrmProfile);
		
		return $drmProfile;
	}
	
	/**
	 * List KalturaDrmProfile objects
	 * 
	 * @action list
	 * @param KalturaDrmProfileFilter $filter
	 * @param KalturaFilterPager $pager
	 * @return KalturaDrmProfileListResponse
	 */
	public function listAction(KalturaDrmProfileFilter  $filter = null, KalturaFilterPager $pager = null)
	{
		if (!$filter)
			$filter = new KalturaDrmProfileFilter();

		$drmProfileFilter = $filter->toObject();
		$c = new Criteria();
		$drmProfileFilter->attachToCriteria($c);
		$count = DrmProfilePeer::doCount($c);
		if (! $pager)
			$pager = new KalturaFilterPager ();
		$pager->attachToCriteria ( $c );
		$list = DrmProfilePeer::doSelect($c);
		
		$response = new KalturaDrmProfileListResponse();
		$response->objects = KalturaDrmProfileArray::fromDbArray($list);
		$response->totalCount = $count;
		
		return $response;
	}
}
