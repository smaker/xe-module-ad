<?php
/**
 * @class  ad
 * @author 퍼니XE <funnyxe@simplesoft.io>
 * @brief  ad 모듈의 high class
 **/

class ad extends ModuleObject
{
	/**
	 * URL 열기 대상
	 * @var array $url_target
	 **/
	public $url_target = array('_blank','_self');

	/** 
	 * 이미지 종류에 따른 Content-Type 정의
	 * @var array $image_mime
	 **/
	public $image_mime = array('image/gif','image/jpeg','image/png');

	/**
	 * 동영상 종류에 따른 Content-Type 정의
	 * @var array $video_mime
	 **/
	public $video_mime = array('video/mp4','video/mpeg','video/quicktime','video/x-flv','video/x-ms-wmv','video/x-msvideo');

	/**
	 * 관리자 페이지에서 사용되는 검색 옵션
	 * @var array $search_option
	 **/
	public $search_option = array('content','user_id','member_srl','user_name','nick_name','is_notice','tags','click_count','regdate','ipaddress');

	/**
	 * 모듈 설정
	 * @var mixed $config
	 **/
	public $config;

	/**
	 * 관리자 act
	 * @var array $adminActList
	 **/
	protected $adminActList = [
		'dispAdDashboard',
		'dispAdConfig',
		'dispAdManageList',
		'dispAdModuleList',
		'dispAdModuleInfo',
		'dispAdPopupList',
		'dispAdPopupInsert',
		'dispAdInsertModule',
		'dispAdDeleteModule',
		'dispAdModuleAdTimeRange',
		'dispAdModuleGrantInfo',
		'dispAdModuleSkinInfo',
		'dispAdNoticeList',
		'dispAdNoticeWrite',
		'dispAdPluginSetup',
		'dispAdModifyAd',
		'procAdInsertConfig',
		'procAdInsertModule',
		'procAdDeleteModule',
		// 광고 삭제
		'procAdDeleteChecked',
		// 광고 수정
		'procAdModifyAd'
	];

	/** @var mixed $triggers 트리거 */
	protected $triggers = array(
		'moduleHandler.init', 'ad', 'controller', 'triggerNotifyAdTime', 'after',
		'module.deleteModule', 'ad', 'controller', 'triggerDeleteModuleAds', 'before',
		'moduleObject.proc', 'ad', 'controller', 'triggerBeforeModuleProc', 'before'
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->config = $this->getConfig();
	}

	/**
	 * 모듈 설치
	 * 
	 * @return BaseObject
	 **/
	public function moduleInstall()
	{
		foreach($triggers as $trigger)
		{
			getController('module')->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return $this->makeObject();
	}

	/**
	 * 광고 모듈 삭제
	 * 
	 * @return BaseObject
	 */
	public function moduleUninstall()
	{
		// 모듈 삭제
		$output = executeQueryArray('ad.getAllModule', null, array('module_srl'));
		if($output->data)
		{
			foreach($output->data as $module)
			{
				getController('ad')->deleteModule($module->module_srl);
			}
		}

		/* 트리거 삭제 */
		foreach($triggers as $trigger)
		{
			getController('module')->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}
		/* 트리거 삭제 끝 */

		return $this->makeObject();
	}

	/**
	 * 업데이트가 필요한지 확인
	 * 
	 * @return boolean
	 **/
	public function checkUpdate()
	{
		// 트리거 존재 여부 확인
		foreach($triggers as $trigger)
		{
			// 트리거가 없으면 업데이트가 필요한 것이다
			if(!getModel('module')->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('ad', 'publish_status'))
		{
			return true;
		}
		if(!$oDB->isColumnExists('ad', 'publish_date'))
		{
			return true;
		}

		return false;
	}

	/**
	* 모듈 업데이트 시 실행되는 메소드
	*
	* @return BaseObject
	**/
	public function moduleUpdate()
	{
		foreach($triggers as $trigger)
		{
			if(!getModel('module')->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				getController('module')->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		$oDB = DB::getInstance();
		if(!$oDB->isColumnExists('ad', 'publish_status'))
		{
			$oDB->addColumn('ad', 'publish_status', 'varchar', 9, 'published');
		}
		if(!$oDB->isColumnExists('ad', 'publish_date'))
		{
			$oDB->addColumn('ad', 'publish_date', 'date');
		}

		return $this->makeObject(0, 'success_updated');
	}

	/**
	 * 캐시 파일 재생성
	 *
	 * @return void
	 **/
	public function recompileCache()
	{
		// 모든 캐시 파일을 삭제한다
		getController('ad')->deleteAllCache();
	}

	/**
	 * XE 및 PHP 버전에 따른 Object 객체 생성
	 * 
	 * @return BaseObject
	 */
	public function makeObject($code = 0, $msg = 'success')
	{
		return class_exists('BaseObject') ? new BaseObject($code, $msg) : new Object($code, $msg);
	}

	/**
	 * 모듈 설정을 가져옵니다
	 * 
	 * @return mixed
	 **/
	public function getConfig()
	{
		static $config = null;
		if(is_null($config))
		{
			// 대시보드 설정 구함
			$config = getModel('module')->getModuleConfig('ad');
			if(!is_object($config))
			{
				$config = new stdClass();
			}

			if(!isset($config->admin_member))
			{
				$config->admin_member = [];
			}
		}
		return $config;
	}

	/**
	 * 관리자 action인지 확인
	 * 
	 * @return boolean
	 */
	protected function _isAdminAct()
	{
		return in_array($this->act, $this->adminActList);
	}
}

if(!function_exists('ad_image'))
{
	/**
	 * 광고 이미지를 출력하는 함수입니다.
	 * 
	 * @param $document_srl 문서 번호
	 * @return string
	 */
	function ad_image($document_srl = null)
	{
		// $document_srl이 숫자가 아닌 경우 실행 종료
		if(!is_numeric($document_srl))
		{
			return null;
		}
	
		$oAd = getModel('ad')->getAd($document_srl);
	
		$result = '<a href="%s" target="%s"><img src="%s" alt="%s"></a>';
		$result = sprintf($result, $oAd->getUrl(), $oAd->getUrlTarget(), $oAd->getBannerPath(), $oAd->getAdContentText());

		return $result;
	}
}

if(!function_exists('ad_image_by_name'))
{
	/**
	 * 특정 영역에 해당하는 광고를 출력합니다.
	 * 
	 * @param $name 영역 이름
	 * @return string
	 */
	function ad_image_by_name($name)
	{
		return null;
	}
}