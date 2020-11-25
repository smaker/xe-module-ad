<?php
/**
 * @class  adView
 * @author 퍼니XE <funnyxe@simplesoft.io>
 * @brief  광고 모듈의 view class
 **/

class adView extends ad
{
	/**
	 * 광고 모듈로 이동시킵니다
	 * 
	 * @return void
	 */
	private function _redirectToAdModule()
	{
		// 관리자 페이지에서 접속 시의 처리
		switch($this->module_info->module)
		{
			case 'admin':
				// URL 구함
				$url = getNotEncodedUrl('module','ad');

				Context::close();
				// 새 주소로 이동
				header('Location:'.$url);
				exit;
		}
	}


	/**
	 * 초기화
	 **/
	public function init()
	{
		$this->_redirectToAdModule();

		// 관리자 action인 경우
		if($this->_isAdminAct())
		{
			if(!defined('__DISABLE_DEFAULT_CSS__'))
			{
				define('__DISABLE_DEFAULT_CSS__', true);
			}

			if(!defined('DISABLE_XE_BTN_STYLES'))
			{
				define('DISABLE_XE_BTN_STYLES', true);
			}

			$this->oAdModel = $oAdModel = getModel('ad');
			// module 모듈의 model 객체 생성
			$oModuleModel = getModel('module');

			// 로그인한 회원의 권한 확인
			$logged_info = Context::get('logged_info');

			$config = getModel('ad')->getConfig();

			$admin = explode(',', trim($config->admin_member, ','));

			// 최고 관리자가 아니라면
			if($logged_info->is_admin !='Y')
			{
				if(is_array($admin) && count($admin) > 0 && (in_array($logged_info->email_address, $admin) || in_array($logged_info->user_id, $admin)))
				{

				}
				else
				{
					return $this->stop('msg_is_not_administrator');
				}
			}

			// 템플릿 경로 지정
			$template_path = sprintf('%stpl/',$this->module_path);
			$this->setTemplatePath($template_path);

			$this->setLayoutPath($this->getTemplatePath());
			$this->setLayoutFile('DashboardLayout');

			// 요청된 module_srl이 모듈의 module_srl과 다르다면
			$module_srl = Context::get('module_srl');
			if(!$module_srl && $this->module_srl) {
				$module_srl = $this->module_srl;
				Context::set('module_srl', $module_srl);
			}

			// 요청받은 모듈의 정보를 구함
			if($module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
				if(!$module_info) {
					Context::set('module_srl','');
				} else {
					ModuleModel::syncModuleToSite($module_info);
					$this->module_info = $module_info;
					Context::set('module_info',$module_info);
				}
			}

			// 기본 설정 구해서 Context::set()
			Context::set('config', $this->config);

			/**
			 * 공용 css / js 파일 로드
			 * 
			 * @todo 템플릿 파일에서 직접 추가하는 방식으로 개선 예정
			 */
			Context::addJsFile($this->module_path.'tpl/js/ad_admin.js');

			// 모듈 분류 구함
			$module_category = $oModuleModel->getModuleCategories();
			Context::set('module_category', $module_category);

			// 레이아웃 변경
			$this->setLayoutPath($this->getTemplatePath());
			$this->setLayoutFile('DashboardLayout');

			// 대시보드 메뉴 구함
			$this->dashboard_menus = $this->getDashboardMenus();
			$this->module_menus = $this->getModuleMenus();
			Context::set('dashboard_menus', $this->dashboard_menus);
			Context::set('module_menus', $this->module_menus);

			// 브라우저 제목 지정
			Context::setBrowserTitle(Context::getLang('ad_module'));
		}
		else
		{
			if(!$this->module_srl)
			{
				return $this->stop('msg_invalid_request');
			}

			if(!$this->module_info->ad_type)
			{
				$this->module_info->ad_type = 'text';
			}

			$this->ad_type = $this->module_info->ad_type;

			// 로그인 정보 구하기
			$logged_info = Context::get('logged_info');

			// 광고 모듈의 model 객체 생성
			$this->oAdModel = $oAdModel = getModel('ad');
			Context::set('oAdModel',$oAdModel);

			// 스킨 경로 지정
			$template_path = sprintf('%sskins/%s/',$this->module_path, $this->module_info->skin?$this->module_info->skin:'xe_default');
			if(!is_dir($template_path)) {
				$this->module_info->skin = 'xe_default';
				$template_path = sprintf('%sskins/%s/',$this->module_path, $this->module_info->skin);
			}
			$this->setTemplatePath($template_path);

			// 공용 JS 파일 로드
			Context::addJsFile($this->module_path.'tpl/js/ad.js');

			// 광고 시간 범위를 구해서 set
			if($this->module_info->use_time == 'Y') {
				$ad_time_range = getModel('ad')->getAdTimeRange($this->module_info);
				Context::set('ad_time_range', $ad_time_range);
			}
		}
	}

		/**
		 * 광고 컨텐츠 목록
		 **/
		public function dispAdContent()
		{
			$document_srl = Context::get('document_srl');
			if($document_srl)
			{
				return $this->dispAdContentView($document_srl);
			}

			$args = new stdClass();
			// 목록을 구하기 위한 대상 모듈에 대한 옵션 설정
			$args->module_srl = $this->module_srl;

			// 지정된 정렬값이 없다면 정렬 값을 지정함
			$args->order_type = 'asc';
			$args->end_date = date('YmdHis');
			$args->with_page = true;

			// 일반 글을 구해서 context set
			$output = getModel('ad')->getAdList($args);

			Context::set('ad_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			$this->setTemplateFile('content');
		}

		/**
		 * 광고 컨텐츠 보기
		 */
		public function dispAdContentView($document_srl = 0)
		{
			if(!$document_srl)
			{
				return $this->AdContentViewFailed();
			}

			$args = new stdClass();
			$args->query_id = 'ad.getAd';

			$oAd = getModel('ad')->getAd($document_srl, $args);
			if(!$oAd->isExists())
			{
				return $this->AdContentViewFailed();
			}

			if($this->ad_type == 'text' || $this->ad_type == 'image')
			{
				$logged_info = Context::get('logged_info');

				$oDB = DB::getInstance();
				$oDB->begin();

				// 확장 변수 구함
				$extra_vars = $oAd->getExtraVars();

				// 게시자에게 포인트 지급
				if($extra_vars->point)
				{
					$member_srl = $oAd->get('member_srl');

					// point 모듈의 controller 객체 생성
					$oPointController = getController('point');
					$oPointController->setPoint($member_srl, $extra_vars->point, 'add');
				}

				// session에 정보로 클릭 수를 증가하였다고 생각하면 패스
				if($_SESSION['readed_ad'][$document_srl])
				{
					$oDB->commit();
					Context::close();
					header('Location:'.getNotEncodedUrl('', 'mid', $this->module_info->mid));
					exit;
				}

				 // 광고 게시자 ip와 현재 접속자의 ip가 동일하면 패스
				if($oAd->get('ipaddress') == $_SERVER['REMOTE_ADDR'])
				{
					$_SESSION['readed_ad'][$document_srl] = true;
					$oDB->commit();
					Context::close();
					header('Location:'.getNotEncodedUrl('', 'mid', $this->module_info->mid));
					exit;
				}

				// 광고 게시자와 로그인 한 회원이 일치하면 세션 등록하고 패스
				if($logged_info->member_srl == $member_srl)
				{
					$_SESSION['readed_ad'][$document_srl] = true;
					$oDB->commit();
					Context::close();
					header('Location:'.getNotEncodedUrl('', 'mid', $this->module_info->mid));
					exit;
				}

				// 클릭수 증가
				$args->document_srl = $document_srl;
				$output = executeQuery('ad.updateClickCount', $args);

				// 문제가 있을 경우 되돌림 (rollback)
				if(!$output->toBool())
				{
					$oDB->rollback();
					return $output;
				}

				// 커밋
				$oDB->commit();

				// 세션 등록
				$_SESSION['readed_ad'][$document_srl] = true;

				// 해당 URL로 이동
				if($oAd->getUrl())
				{
					Context::close();
					header('Location:'.$oAd->getUrl());
					exit;
				}
			}
			else
			{
				return $this->AdContentViewFailed();
			}
		}

		public function AdContentViewFailed()
		{
			Context::set('act', 'dispAdContent');
		}

		/**
		 * 광고 삭제
		 **/
		public function dispAdDelete()
		{
			// 로그인 정보 구함
			$logged_info = Context::get('logged_info');

			// 로그인 상태가 아니라면 에러
			if(!$logged_info) return getModel('ad')->returnMessage(-1, 'please_login');

			// 요청 받은 광고 번호를 구함
			$document_srl = Context::get('document_srl');

			// ad model의 객체 생성
			$oAdModel = getModel('ad');

			// 등록된 광고 내용을 구함
			$oAd = $oAdModel->getAd($document_srl);
			if(!$oAd->isExists()) return $oAdModel->returnMessage(-1, 'not_exists_ad');
			if(!$oAd->isGranted()) return new Object(-1, 'msg_not_permitted');

			Context::set('oAd', $oAd);

			$this->setTemplateFile('delete');
		}
		
		/**
		 * @brief 광고 수정 (작업중)
		 **/
		function dispAdModify() {
			// 로그인 정보 구함
			$logged_info = Context::get('logged_info');

			// 로그인 상태가 아니라면 에러
			if(!$logged_info) return getModel('ad')->returnMessage(-1, 'please_login');

			// 요청 받은 광고 번호를 구함
			$document_srl = Context::get('document_srl');

			// 등록된 광고 내용을 구함
			$oAd = getModel('ad')->getAd($document_srl);
			if(!$oAd->isExists()) return getModel('ad')->returnMessage(-1, 'not_exists_ad');
			if(!$oAd->isGranted()) return new BaseObject(-1, 'msg_not_permitted');

			Context::set('oAd', $oAd);

			$this->setTemplateFile('modify');
		}

		/**
		 * 광고 수정
		 * 
		 * @return void
		 */
		public function dispAdModifyAd()
		{
			// 로그인 정보 구함
			$logged_info = Context::get('logged_info');

			$oAdModel = getModel('ad');

			// 로그인 상태가 아니라면 에러
			if(!$logged_info)
			{
				return $oAdModel->returnMessage(-1, 'please_login');
			}

			// 요청 받은 광고 번호를 구함
			$document_srl = Context::get('document_srl');

			// 등록된 광고 내용을 구함
			$oAd = $oAdModel->getAd($document_srl);
			if(!$oAd->isExists()) return $oAdModel->returnMessage(-1, 'not_exists_ad');
			if(!$oAd->isGranted()) return $this->makeObject(-1, 'msg_not_permitted');

			Context::set('oAd', $oAd);

			$ad_time_range = getModel('ad')->getAdTimeRange($this->module_info);
			Context::set('ad_time_range', $ad_time_range);

			$this->setTemplateFile('ModifyAd');
		}

		/**
		 * @brief 대시보드 메인
		 */
		public function dispAdDashboard()
		{
			$args = new stdClass();
			// 목록을 구하기 위한 목록 수/ 페이지 목록 수에 대한 옵션 설정
			$args->list_count = 10;
			$args->page_count = 1;

			// 검색과 정렬을 위한 변수 설정
			$args->sort_index = 'D.list_order';
			$args->order_type = 'asc';
			$args->is_notice = 'N';
			$args->ad_type = 'linead';

			// 오늘 등록된 광고 목록 구함
			$output = getModel('ad')->getTotalLinead($args);

			Context::set('text_ad', $output->data);
			Context::set('total_text_ad_count', $output->total_count);

			$args = new stdClass();
			// 목록을 구하기 위한 목록 수/ 페이지 목록 수에 대한 옵션 설정
			$args->list_count = 10;
			$args->page_count = 1;

			// 검색과 정렬을 위한 변수 설정
			$args->sort_index = 'list_order';
			$args->order_type = 'asc';

			// 공지를 제외한
			$args->is_notice = 'N';

			// 모든 광고 출력
			//$args->select_all_ad = 'Y';
			$args->ad_type = 'banner';

			// 오늘 등록된 광고 목록 구함
			$output = getModel('ad')->getAdList($args);

			Context::set('image_ad', $output->data);
			Context::set('total_image_ad_count', $output->total_count);

			// 브라우저 제목 지정
			$this->setPageTitle(Context::getLang('_dashboard'));

			// 템플릿 파일 지정
			$this->setTemplateFile('_dashboard');
		}

		/**
		 * @brief 대시보드 > 기본 설정
		 */
		public function dispAdConfig()
		{
			global $lang;

			$oMemberModel = getModel('member');
			$member_config = $oMemberModel->getMemberConfig();
			Context::set('member_config', $member_config);

			// 브라우저 제목 지정
			$this->setPageTitle($lang->ads->dashboard_menus[Context::get('act')]);

			Context::set('admin_member', getModel('ad')->getAdminId());

			// 템플릿 파일 지정
			$this->setTemplateFile('Config');
		}

		/**
		 * 대시보드 > 광고 관리
		 **/
		public function dispAdManageList()
		{
			global $lang;

			$args = new stdClass();

			// 목록을 구하기 위한 대상 모듈/ 페이지 수/ 목록 수/ 페이지 목록 수에 대한 옵션 설정
			$args->page = Context::get('page');
			$args->list_count = $module_info->list_count;
			$args->page_count = $module_info->page_count;

			// 검색과 정렬을 위한 변수 설정
			$args->search_target = Context::get('search_target');
			$args->search_keyword = Context::get('search_keyword');

			// 지정된 정렬값이 없다면 정렬 값을 지정함
			$args->sort_index = 'list_order';
			$args->order_type = 'asc';

			// 공지를 제외한
			$args->is_notice = 'N';

			// 모든 광고
			$args->select_all_ad = 'Y';

			$args->status = Context::get('status');
			$args->ad_type = Context::get('ad_type');

			if($args->status == 'ended')
			{
				$output = getModel('ad')->getEndedAdList($args);
			}
			else
			{
				$output = getModel('ad')->getAdList($args);
			}

			Context::set('ad_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			// 템플릿에서 사용할 변수를 Context::set()
			if($this->module_srl)
			{
				Context::set('module_srl', $this->module_srl);
			}

			Context::set('module_info', $this->module_info);

			// 브라우저 제목 지정
			$this->setPageTitle($lang->ads->dashboard_menus[Context::get('act')]);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('AdList');
		}

		/**
		 * 광고 모듈 목록
		 **/
		public function dispAdModuleList()
		{
			global $lang;

			$args = new stdClass();
			// 등록된 ad 모듈을 불러와 세팅
			$args->sort_index = 'module_srl';
			$args->page = Context::get('page');
			$args->list_count = 20;
			$args->page_count = 10;
			$args->s_module_category_srl = Context::get('module_category_srl');
			$output = executeQueryArray('ad.getAdModuleList', $args);
			ModuleModel::syncModuleToSite($output->data);

			// 템플릿에 쓰기 위해서 context::set()
			Context::set('module_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			// 브라우저 제목 지정
			$this->setPageTitle($lang->ads->dashboard_menus[Context::get('act')]);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('ModuleList');
		}

	   /**
		 * 선택된 광고 모듈의 정보 출력 (바로 정보 입력으로 변경)
		**/
		public function dispAdModuleInfo()
		{
			$this->dispAdInsertModule();
		}

		/**
		 * 광고 모듈 추가 폼 출력
		 * 
		 * @return void|Object|BaseObject
		 **/
		public function dispAdInsertModule()
		{
			Context::set('isLayoutDrop', '');

			if(!in_array($this->module_info->module, array('admin', 'ad')))
			{
				return $this->makeObject(-1, 'msg_invalid_request');
			}

			// 스킨 목록을 구해옴
			$skin_list = getModel('module')->getSkins($this->module_path);
			Context::set('skin_list',$skin_list);

			// 레이아웃 목록을 구해옴
			$oLayoutModel = getModel('layout');
			$layout_list = $oLayoutModel->getLayoutList();
			Context::set('layout_list', $layout_list);


			$mskin_list = getModel('module')->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);
	
			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

			// Javascript Filter 적용
			Context::addJsFilter($this->module_path.'tpl/filter/','insert_module.xml');

			$module = $this->oAdModel->getLangCode('module', 'dashboard');

			// module_srl의 유무에 따른 브라우저 제목 지정
			if(Context::get('module_srl')) {
				$this->setPageTitle($module->setup);
			} else {
				$this->setPageTitle($module->make);
			}

			$ads = Context::getLang('ads');

			$script = '<script>
			var msg_choose_color = \'%s\';
			var msg_choose_correct_color = \'%s\';
			var msg_already_inserted = \'%s\';
			var msg_cannot_delete_default_color = \'%s\';
			';
			if($this->module_info->use_time == 'Y' || $this->module_info->use_color == 'Y' || $this->module_info->use_bgcolor == 'Y') {
				$script .= 'jQuery(document).ready(function($){';
				if($this->module_info->use_time == 'Y') $script .= 'initAdTimeRange();';
				if($this->module_info->use_color == 'Y') $script .= 'initAdColorRange(document.getElementById(\'selColorRange\'));';
				if($this->module_info->use_bgcolor == 'Y') $script .= 'initAdBgColorRange();';
				$script .= '});';
			}
			$script .= '</script>';
			Context::addHtmlFooter(sprintf($script, $ads->msg->choose_color, $ads->msg->choose_correct_color, $ads->msg->already_inserted, $ads->msg->cannot_delete_default_color));

			$template_path = sprintf('%stpl/',$this->module_path);

			$this->setTemplatePath($template_path);
			// 템플릿 파일 지정
			$this->setTemplateFile('ModuleInsert');
		}

		/**
		 * 광고 모듈 삭제 화면 출력
		 * 
		 * @return void
		 **/
		public function dispAdDeleteModule()
		{
			if(!Context::get('module_srl'))
			{
				return $this->dispAdAdminModuleList();
			}

			if(!in_array($this->module_info->module, array('admin', 'ad'))) return $this->makeObject(-1, 'msg_invalid_request');

			$module_info = Context::get('module_info');

			// 광고 갯수 구함
			$oAdModel = getModel('ad');
			$ad_count = getModel('ad')->getAdCount($module_info->module_srl);
			$module_info->ad_count = $ad_count;

			Context::set('module_info',$module_info);

			// ad model의 객체 생성
			$oAdModel = getModel('ad');

			// Javascript Filter 적용
			Context::addJsFilter($this->module_path.'tpl/filter/','delete_module.xml');

			$module = $oAdModel->getLangCode('module', 'dashboard');

			// 브라우저 제목 지정
			$this->setPageTitle($module->delete);

			// 템플릿 파일 지정
			$this->setTemplateFile('ModuleDelete');
		}

		/**
		 * 권한 관리 출력
		 **/
		public function dispAdModuleGrantInfo()
		{
			// 공통 모듈 권한 설정 페이지 호출
			$oAdAdminModel = getAdminModel('ad');
			$grant_content = $oAdAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
			Context::set('grant_content', $grant_content);

			// 브라우저 제목 지정
			$this->setPageTitle($this->module_menus[Context::get('act')]);

			// 템플릿 경로 지정
			$template_path = sprintf('%stpl/',$this->module_path);
			$this->setTemplatePath($template_path);
			// 템플릿 파일 지정
			$this->setTemplateFile('ModuleGrant');
		}

		/**
		 * @brief 스킨 정보 보여줌
		 **/
		public function dispAdModuleSkinInfo()
		{
			// 공통 모듈 권한 설정 페이지 호출
			$oAdAdminModel = getAdminModel('ad');
			$skin_content = $oAdAdminModel->getModuleSkinHTML($this->module_info->module_srl);
			Context::set('skin_content', $skin_content);

			// 브라우저 제목 지정
			$this->setPageTitle($this->module_menus[Context::get('act')]);

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('SkinInfo');
		}

		/**
		 * 공지 관리
		 **/
		public function dispAdNoticeList()
		{
			global $lang;

			$oModuleModel = getModel('module');
			$oAdModel = getModel('ad');

			$args = new stdClass();
			// 목록을 구하기 위한 페이지 수/ 목록 수/ 페이지 목록 수에 대한 옵션 설정
			$args->page = Context::get('page');
			$args->list_count = $module_info->list_count;
			$args->page_count = $module_info->page_count;

			// 검색과 정렬을 위한 변수 설정
			$args->search_target = Context::get('search_target');
			$args->search_keyword = Context::get('search_keyword');

			// 지정된 정렬값이 없다면 정렬 값을 지정함
			$args->sort_index = 'list_order';
			$args->order_type = 'asc';

			// 만약 검색어가 있으면 list_count를 search_list_count 로 이용
			if($args->search_keyword) $args->list_count = $this->search_list_count;

			$args->is_notice = 'Y';
			$args->with_page = true;
			$args->select_all_ad = 'Y';

			// 공지를 구해서 context set
			$output = getModel('ad')->getAdList($args);
			Context::set('notice_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			// 브라우저 제목 지정
			$this->setPageTitle($lang->ads->dashboard_menus[Context::get('act')]);

			// 템플릿 파일 지정
			$this->setTemplateFile('NoticeIndex');
		}

		/**
		 * 공지 작성
		 **/
		public function dispAdNoticeWrite()
		{
			$module = $this->oAdModel->getLangCode('module', 'dashboard');
			$notice = $this->oAdModel->getLangCode('notice', 'dashboard');

			$args = new stdClass();
			if(!Context::get('module_srl'))
			{
				// 등록된 광고 모듈을 구해옴
				$args->sort_index = 'module_srl';
				$args->page = Context::get('page');
				$args->list_count = 20;
				$args->page_count = 10;
				$args->s_module_category_srl = Context::get('module_category_srl');
				$output = executeQueryArray('ad.getAdModuleList', $args);
				ModuleModel::syncModuleToSite($output->data);

				// 템플릿에 쓰기 위해서 context::set
				Context::set('module_list', $output->data);
				Context::set('total_count', $output->total_count);
				Context::set('total_page', $output->total_page);
				Context::set('page', $output->page);
				Context::set('page_navigation', $output->page_navigation);

				// 브라우저 제목 지정
				$this->setPageTitle($notice->write.' > '.$module->select);
			} else {
				// module_srl로 mid 찾기
				$oModuleModel = getModel('module');
				$module_info = $oModuleModel->getModuleInfoByModuleSrl((int)Context::get('module_srl'));

				// mid를 찾았다면 mid와 module_srl을 set, 찾지 못했다면 에러 출력
				if($module_info->mid) {
					Context::set('mid', $module_info->mid);
					Context::set('module_srl', $module_info->module_srl);
				} else {
					return $this->oAdModel->returnMessage(-1, 'module_is_not_exists');
				}

				// Js Filter 적용
				Context::addJsFilter($this->module_path.'tpl/filter/','insert_notice.xml');

				// 브라우저 제목 지정
				$this->setPageTitle($notice->write);
			}

			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('NoticeWrite');
		}

		/**
		 * @brief ID 찾기 팝업
		 **/
		function dispAdFindUserId()
		{
			// 템플릿 파일 지정
			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('FindUserID');
		}

		/**
		 * @brief 대시보드에서 사용되는 페이지 제목 제어 함수
		 */
		public function setPageTitle($title)
		{
			$this->page_title = $title;

			// '타이틀 이름 - 광고 모듈' 형식으로 변경
			$browser_title = sprintf('%s - %s', $title, Context::getLang('ad_module'));
			Context::setBrowserTitle($browser_title);
		}

		/**
		 * @biref 현재 페이지 제목을 구하는 함수
		 */
		public function getPageTitle($title)
		{
			return $this->page_title;
		}

		/**
		 * @biref 대시보드의 메뉴를 구하는 함수
		 */
		function getDashboardMenus() {
			return $this->oAdModel->getLangCode('dashboard_menus');
		}

		/**
		 * @biref 모듈의 탭 메뉴를 구하는 함수
		 */
		function getModuleMenus() {
			$module = $this->oAdModel->getLangCode('module', 'dashboard');
			return $module->tab_menus;
		}

		public function dispAdPopupList()
		{
			$args = new stdClass();
			$output = executeQueryArray('ad.getPopupList', $args);

			$popup_list = array();

			Context::set('popup_list', $popup_list);

			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('PopupList');
		}

		public function dispAdPopupInsert()
		{
			$args = new stdClass();
			$args->module = 'ad';

			$target_modules = getModel('module')->getMidList($args);

			Context::set('target_modules', $target_modules);

			$document_srl = Context::get('document_srl');

			$oDocument = getModel('document')->getDocument($document_srl);

			Context::set('oDocument', $oDocument);

			$this->setTemplatePath($this->module_path . 'tpl');
			$this->setTemplateFile('PopupInsert');
		}
	}