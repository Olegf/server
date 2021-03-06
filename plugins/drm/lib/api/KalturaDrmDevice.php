<?php
/**
 * @package plugins.drm
 * @subpackage api.objects
 */
class KalturaDrmDevice extends KalturaObject implements IFilterable
{	
	/**
	 * @var int
	 * @readonly
	 * @filter eq,in,order
	 */
	public $id;
	
	/**
	 * @var int
	 * @insertonly
	 * @filter eq,in
	 */
	public $partnerId;
	
	/**
	 * @var string
	 * @filter like,order
	 */
	public $userId;
	
	/**
	 * @var string
	 * @filter like,order
	 */
	public $deviceId;
	
	/**
	 * @var string
	 * @filter like,order
	 */
	public $version;
	
	
	/**
	 * @var string
	 */
	public $platformDescriptor;
	
	/**
	 * @var KalturaDrmProviderType
	 * @filter eq,in
	 */
	public $provider;

	/**
	 * @var int
	 * @filter eq,in
	 */
	public $profileId;
	
	/**
	 * @var KalturaDrmPolicyStatus
	 * @filter eq,in
	 */
	public $status;
		
	/**
	 * @var int
	 * @readonly
	 */
	public $createdAt;

	/**
	 * @var int
	 * @readonly
	 */
	public $updatedAt;
	
	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)  
	 */
	private static $map_between_objects = array(
		'id',
		'partnerId',
		'provider',
		'profileId',
		'userId',
		'deviceId',
		'version',
		'platformDescriptor',
		'status',
		'createdAt',
		'updatedAt',
	 );
		 
	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
	
	public function toObject($dbObject = null, $skip = array())
	{
		if (is_null($dbObject))
			$dbObject = new DrmDevice();
		parent::toObject($dbObject, $skip);		
		return $dbObject;
	}
		
	/**
	 * @param int $type
	 * @return KalturaDrmDevice
	 */
	static function getInstanceByType ($provider)
	{
		$obj = KalturaPluginManager::loadObject('KalturaDrmDevice', $provider);		
		if(!$obj)
			$obj = new KalturaDrmDevice();
		return $obj;
	}

	public function getExtraFilters()
	{
		return array();
	} 
	
	public function getFilterDocs()
	{
		return null;
	}
	
}