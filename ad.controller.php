<?php
/**
 * @class  adController
 * @author 퍼니XE <funnyxe@simplesoft.io>
 * @brief  ad 모듈의 controller class
 **/

class adController extends ad
{
	/**
	 * 초기화
	 * 
	 * @return void
	 **/
	public function init()
	{
		$oAdModel = getModel('ad');

		if(!$this->module_info->ad_type)
		{
			$this->module_info->ad_type = 'text';
		}
		if(!$this->module_info->ad_time_range)
		{
			$this->module_info->ad_time_range = $oAdModel->getDefaultAdTimeRange();
		}

		// 필요한 변수를 셋팅
		$this->ad_type = $this->module_info->ad_type;
		$this->ad_time_range = $this->module_info->ad_time_range;
		$this->use_time = $this->module_info->use_time=='Y'?true:false;
		$this->each_highlight_point = $this->module_info->each_highlight_point=='Y'?true:false;
		$this->point->register = (int)$this->module_info->ad_point;
		$this->point->highlight = (int)$this->module_info->highlight_point;

		$logged_info = Context::get('logged_info');
		$config = $oAdModel->getConfig();

		$admin = explode(',', trim($config->admin_member, ','));

		if($this->_isAdminAct())
		{
			// 최고 관리자가 아니라면
			if($logged_info->is_admin !='Y')
			{
				if(is_array($admin) && count($admin) > 0 && (in_array($logged_info->email_address, $admin) || in_array($logged_info->user_id, $admin)))
				{

				}
				else
				{
					return $this->stop($lang->msg_is_not_administrator);
				}
			}
		}
	}

	/**
	 * 광고 등록
	 * 
	 * @return void
	 **/
	public function procAdRegister()
	{
		// 로그인 정보 구함
		$logged_info = Context::get('logged_info');

		// 권한 확인
		if(!$this->grant->register_ad)
		{
			return $this->makeObject(-1, 'msg_not_permitted');
		}

		// 입력 받은 항목 검사
		$obj = Context::getRequestVars();
		switch($this->ad_type)
		{
			case 'text':
				unset($obj->banner_image);
				unset($obj->banner_movie);
				$obj->ad_type = 'linead';
				break;
			case 'image':
				unset($obj->ad_content);
				unset($obj->banner_movie);
				$banner_url = removeHackTag(trim($obj->banner_image));
				$obj->ad_type = 'banner';
				break;
			case 'movie':
				unset($obj->ad_content);
				unset($obj->banner_image);
				$banner_url = removeHackTag(trim($obj->banner_movie));
				$obj->ad_type = 'banner';
				break;
		}
		$obj->module_srl = $this->module_srl;
		if($obj->is_notice !='Y'|| !$this->grant->manager) $obj->is_notice = 'N';

		// 광고 모듈의 model 객체 생성
		$oAdModel = getModel('ad');

		// 관리자가 아니라면
		if(!$this->grant->manager && $obj->is_notice != 'Y') {
			// 등록 제한 확인 (공지 제외)
			$ad_limit = (int)$tihs->module_info->ad_limit;
			$daily_limit = (int)$this->module_info->daily_limit;

			// 전체 등록 제한
			if($ad_limit) {
				$args->member_srl = $logged_info->member_srl;
				$args->is_notice = 'N';
				$count = $oAdModel->getAdCount($this->module_srl, $args);

				$m = $oAdModel->getMessageCode('ad_limit_over');
				if($count>$ad_limit) return $oAdModel->returnMessage(-1, sprintf($m, $ad_limit));
			}

			// 하루 등록 제한
			if($daily_limit) {
				$args->start_date = date('Ymd').'000000';
				$args->member_srl = $logged_info->member_srl;
				$args->is_notice = 'N';
				$count = $oAdModel->getAdCount($this->module_srl, $args);

				$m = $oAdModel->getMessageCode('daily_limit_over');
				if($count>$daily_limit) return $oAdModel->returnMessage(-1, sprintf($m, $daily_limit));
			}
		}

		// 배너 용도로 사용 시의 처리
		if(!$obj->ad_content) {
			$obj->ad_content = trim($obj->site_name);
			$obj->used_style = $obj->used_style?'|@|site_'.$banner_url:'site_'.$banner_url;

			// 배너 이미지가 없으면 에러
			if(!is_uploaded_file($_FILES['banner_image']['tmp_name']))
			{
				return $this->makeObject(-1, '배너 이미지를 선택해주세요.');
			}
		}

		// URL에 입력된 주소를 걸러냄 (최고 관리자가 아니라면 문제가 될만한 문자를 걸러냄) 
		if($logged_info->is_admin != 'Y') $obj->url = removeHackTag($obj->url);

		// 광고 내용이 없으면 에러
		settype($obj->ad_content, 'string');
		if($obj->ad_content == '')
		{
			return $this->makeObject(-1, 'msg_invalid_request');
		}

		// 기본값 지정
		$obj->title = $obj->ad_content;
		$obj->content = $obj->ad_content;
		$obj->allow_comment = 'N';
		$obj->lock_comment = 'Y';
		if($obj->url && !preg_match('/^([a-z]+):\/\//i',$obj->url)) $obj->url = 'http://'.$obj->url;
		if(!in_array($obj->url_target,array('_self','_blank'))) $obj->url_target = '_blank';

		// 광고 강조 권한이 없으면 각종 옵션을 해제
		if(!$this->grant->highlight_ad) {
			unset($obj->ad_color);
			unset($obj->ad_bgcolor);
			unset($obj->used_style);
		}

		// 사용된 옵션을 배열로 변환
		$used_style = $obj->used_style?explode('|@|',$obj->used_style):array();

		// 글자색과 배경색이 같으면 에러
		if($obj->ad_color && $obj->ad_bgcolor && $obj->ad_color == $obj->ad_bgcolor && $obj->ad_color != -1 && $obj->ad_bgcolor != -1)
		{
			return $oAdModel->returnMessage(-1, 'invalid_color');
		}

		if($obj->ad_color == -1)
		{
			$obj->ad_color = null;
		}

		if($obj->ad_bgcolor == -1)
		{
			$obj->ad_bgcolor = null;
		}

		// 강조 포인트 계산
		if($obj->is_notice != 'Y' && ($obj->used_style || $obj->ad_color || $obj->ad_bgcolor)) {
			if($this->each_highlight_point) {
				if(count($used_style)) $highlight_point += count($used_style) * $this->point->highlight;
				if($obj->ad_color) $highlight_point += $this->point->highlight;
				if($obj->ad_bgcolor) $highlight_point += $this->point->highlight_point;
			} else {
				$highlight_point = $this->point->highlight;
			}
		}

		// 지정된 글자색이 있으면 배열에 저장
		if($obj->ad_color) {
			$used_style[] = 'text_'.$obj->ad_color;
			unset($obj->ad_color);
		}

		// 지정된 배경색이 있으면 배열에 저장
		if($obj->ad_bgcolor) {
			$used_style[] = 'bg_'.$obj->ad_bgcolor;
			unset($obj->ad_bgcolor);
		}

		if(is_array($used_style)) $obj->used_style = join('|@|',$used_style);
		else $obj->used_style = '';

		// 포인트 모듈의 model / controller 객체 생성
		$oPointModel = getModel('point');
		$oPointController = getController('point');

		// 포인트 확인 (공지 제외)
		if($obj->is_notice !='Y') {
			$ad_point = $this->point->register;
			// 광고 시간을 사용할 경우의 처리
			if($this->module_info->ad_point_rate && $this->use_time) {
				$ad_point *= $obj->ad_time / $this->module_info->ad_point_rate;
				$ad_point += $highlight_point;
			}

			// 광고 소모 포인트가 0보다 크면 포인트 확인
			if($ad_point>0) {
				$prev_point = $oPointModel->getPoint($logged_info->member_srl);
				if($ad_point > $prev_point) return $oAdModel->returnMessage(-1, 'not_enough_point');
			}
		}

		// 광고 시간의 유효성을 확인 (파이어폭스의 Firebug, IE8의 개발자 도구 등을 이용한 조작 방지)
		if($this->use_time) {
			$obj->ad_time = (int)$obj->ad_time;
			$ad_time_range = explode(',',$this->ad_time_range);
			$ad_time_range[] = -1;

			// 광고 시간이 무제한인데 관리자 권한이나 무제한 광고 권한이 없으면 에러
			if($obj->ad_time == -1 && (!$this->grant->unlimited_ad && !$this->grant->manager)) return $oAdModel->returnMessage(-1, 'invalid_ad_time');
			if(!$obj->ad_time || !in_array($obj->ad_time, $ad_time_range)) return $oAdModel->returnMessage(-1, 'invalid_ad_time');
		}

		// 문서 모듈의 controller 객체 생성
		$oDocumentController = getController('document');

		// 광고 존재 여부 확인
		$oAd = $oAdModel->getAd($obj->document_srl, $this->grant->manager);

		// 이미 존재하면 에러
		if($oAd->isExists() && $oAd->document_srl == $obj->document_srl) {
			return $this->makeObject(-1, 'msg_invalid_request');
		} else {
			// 문서 등록
			$output = $oDocumentController->insertDocument($obj);
			$msg_code = 'success_registed';
			$obj->document_srl = $output->get('document_srl');

			// 오류 발생시 멈춤
			if(!$output->toBool()) return $output;

			// 광고 등록
			$ad = new stdClass();
			$ad->module_srl = $obj->module_srl;
			$ad->document_srl = $obj->document_srl;
			$ad->ad_time = $obj->ad_time;
			$ad->ad_type = $obj->ad_type;
			$ad->url = $obj->url;
			$ad->url_target = $obj->url_target;
			$ad->style = $obj->used_style;
			$ad->start_date = date('YmdHis');
			$ad->publish_status = 'published';
			$output = $this->insertAd($ad);
			if(!$output->toBool()) return $output;

			$file_info = Context::get('banner_image');

			$output = getController('file')->insertFile($file_info, $this->module_srl, $obj->document_srl);

			$documentSrl = array($obj->document_srl);
			getController('file')->setFilesValid($obj->document_srl);
			getController('document')->updateUploaedCount($documentSrl);

			// 포인트 차감 (공지 제외)
			if($ad_point>0 && $obj->is_notice !='Y') $oPointController->setPoint($logged_info->member_srl, $ad_point, 'minus');
		}

		// 결과 반환
		$this->add('mid', Context::get('mid'));

		$returnUrl = getNotEncodedUrl('', 'mid', Context::get('mid'));

		// 성공 메시지
		$this->setMessage($msg_code);
		$this->setRedirectUrl($returnUrl);
	}

		/**
		 * @brief 광고 수정
		 */
		function procAdModify()
		{
			$obj = Context::getRequestVars();

			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			// 문서 모듈의 controller 객체 생성
			$oDocumentController = getController('document');

			// 광고 존재 여부 확인
			$oAd = $oAdModel->getAd($obj->document_srl, $this->grant->manager);
			if(!$oAd->isExists() || $oAd->document_srl != $obj->document_srl) return $oAdModel->returnMessage(-1, 'not_exists_ad');

			// 문서 업데이트
			$oDocumentController->updateDocument($oAd, $obj);

			// 광고 업데이트
			$this->updateAd($obj);

			$this->add('mid', Context::get('mid'));

			$this->setMessage('success_updated');
		}

		/**
		 * 광고 수정
		 * 
		 * @return void
		 */
		public function procAdModifyAd()
		{
			$obj = Context::getRequestVars();

			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			// 문서 모듈의 controller 객체 생성
			$oDocumentController = getController('document');

			// 광고 존재 여부 확인
			$oAd = $oAdModel->getAd($obj->document_srl, $this->grant->manager);
			if(!$oAd->isExists() || $oAd->document_srl != $obj->document_srl) return $oAdModel->returnMessage(-1, 'not_exists_ad');

			$obj->title = $obj->ad_content;
			$obj->content = $obj->ad_content;

			// 문서 업데이트
			$oDocumentController->updateDocument($oAd, $obj);

			// 글자색과 배경색이 같을 경우 오류
			if($obj->ad_color && $obj->ad_bgcolor && $obj->ad_color == $obj->ad_bgcolor && $obj->ad_color != -1 && $obj->ad_bgcolor != -1)
			{
				return $this->makeObject(-1, 'msg_invalid_color');
			}

			if($obj->ad_color == -1)
			{
				$obj->ad_color = null;
			}
	
			if($obj->ad_bgcolor == -1)
			{
				$obj->ad_bgcolor = null;
			}

			// 사용한 글자색을 배열에 저장
			if($obj->ad_color)
			{
				$used_style[] = 'text_'.$obj->ad_color;
				unset($obj->ad_color);
			}

			// 사용한 배경색을 배열에 저장
			if($obj->ad_bgcolor)
			{
				$used_style[] = 'bg_'.$obj->ad_bgcolor;
				unset($obj->ad_bgcolor);
			}

			if(in_array('bold', $obj->used_style))
			{
				$used_style[] = 'bold';	
			}

			if(in_array('underline', $obj->used_style))
			{
				$used_style[] = 'underline';
			}

			if(in_array('italic', $obj->used_style))
			{
				$used_style[] = 'italic';	
			}
	
			// 사용한 광고 옵션 & 스타일 배열을 합치기
			if(is_array($used_style)) $obj->used_style = join('|@|',$used_style);
			else $obj->used_style = array();

			$file_info = $_FILES['banner_image'];
			if(is_uploaded_file($file_info['tmp_name']))
			{
				// 기존에 업로드한 배너 이미지 삭제
				getController('file')->deleteFiles($obj->document_srl);

				$output = getController('file')->insertFile($file_info, $this->module_srl, $obj->document_srl);

				$documentSrl = array($obj->document_srl);
				getController('file')->setFilesValid($obj->document_srl);
				getController('document')->updateUploaedCount($documentSrl);
			}

			// 광고 업데이트
			$output = $this->updateAd($obj);
	
			// 광고 시간의 유효성을 확인 (파이어폭스의 Firebug, IE8의 개발자 도구 등을 이용한 조작 방지)
			if($this->use_time) {
				$obj->ad_time = (int)$obj->ad_time;
				$ad_time_range = explode(',',$this->ad_time_range);
				$ad_time_range[] = -1;

				// 광고 시간이 무제한인데 관리자 권한이나 무제한 광고 권한이 없으면 에러
				if($obj->ad_time == -1 && (!$this->grant->unlimited_ad && !$this->grant->manager)) return $oAdModel->returnMessage(-1, 'invalid_ad_time');
				if(!$obj->ad_time || !in_array($obj->ad_time, $ad_time_range)) return $oAdModel->returnMessage(-1, 'invalid_ad_time');
			}

			$returnUrl = Context::get('success_return_url');

			$this->setMessage('success_updated');
			$this->setRedirectUrl($returnUrl);
		}


		/**
		 * @brief 광고 삭제
		 */
		function procAdDelete() {
			$document_srl = Context::get('document_srl');

			// 광고 삭제
			$this->deleteAd($document_srl);

			$this->add('mid', Context::get('mid'));

			$this->setMessage('success_deleted');
		}
		/**
		 * @brief insert Ad
		 **/
		public function insertAd($args) {
			// check $args
			if(!$args || !$args->document_srl) return;
			$args->ad_time = (int)$args->ad_time;
			if($this->module_info->use_time == 'Y' && !$args->ad_time) return $oAdModel->returnMessage(-1, 'input_ad_time');
			if($this->module_info->use_time != 'Y') $args->ad_time = -1;

			// module srl
			$module_srl = $args->module_srl;
			unset($args->module_srl);

			// create model object of ad module
			$oAdModel = getModel('ad');

			// set end date
			if($this->module_info->use_time == 'Y' && $args->ad_time != -1) $args->end_date = date('YmdHis',$oAdModel->dateAdd('h',$args->ad_time,strtotime($args->start_date)));
			else $args->end_date = -1;

			// set default value
			if($args->url && !preg_match('/^([a-z]+):\/\//i',$args->url)) $args->url = 'http://'.$args->url;
			if(!in_array($args->url_target,array('_self','_target'))) $args->url_target = '_blank';
			if(!$args->start_date) $args->start_date = date('YmdHis');

			// execute query
			$obj = new stdClass();
			$obj->document_srl = $args->document_srl;
			$obj->module_srl = $module_srl;
			$obj->start_date = $args->start_date;
			$obj->end_date = $args->end_date;
			$obj->ad_time = $args->end_date==-1?-1:$args->end_date - $args->start_date;
			$obj->ad_type = $args->ad_type;
			$obj->url = $args->url;
			$obj->url_target = $args->url_target;
			$obj->style = $args->style;
			$obj->publish_status = $args->publish_status;
			$obj->publish_date = $args->publish_date;

			$output = executeQuery('ad.insertAd', $obj);

			return $output;
		}

		/**
		 * 광고 수정
		 **/
		public function updateAd($args)
		{
			// 매게변수 검사
			if(!$args || !$args->document_srl) return;

			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			// get ad info
			$oAd = $oAdModel->getAd($args->document_srl, $this->grant->manager);
			if(!$oAd->isExists())
			{
				return $this->makeObject(-1, 'msg_invalid_request');
			}

			// set default value
			if($args->url && !preg_match('/^([a-z]+):\/\//i',$args->url)) $args->url = 'http://'.$args->url;
			if(!in_array($args->url_target,array('_self','_target'))) $args->url_target = '_blank';

			$args->content = $args->ad_content;

			$oDocumentController = getController('document');
			$oDocumentController->updateDocument($oAd, $args);

			// execute query
			$obj = new stdClass();
			$obj->document_srl = $args->document_srl;
			$obj->url = $args->url;
			$obj->url_target = $args->url_target;
			$obj->style = $args->used_style;
			$obj->publish_status = $args->publish_status;
			$obj->publish_date = $args->publish_date;
			$output = executeQuery('ad.updateAd', $obj);

			return $output;
		}

		/**
		 * 공지 등록
		 **/
		function insertNotice($args)
		{
			if(!$args || !$args->document_srl)
			{
				return $this->makeObject();
			}

			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			$oAd = $oAdModel->getAd($args->document_srl, $this->grant->manager);

			// check $args
			$args->ad_time = (int)$args->ad_time;
			$args->start_date = trim($args->start_date);
			$args->start_hour = (int)trim($args->start_hour);
			$args->start_minute = (int)trim($args->start_minute);
			$args->start_second = (int)trim($args->start_second);
			if(!$args->ad_time) $args->ad_time = -1;
			if(!$args->start_hour) $args->start_hour = '00';
			if(!$args->start_minute) $args->start_minute = '00';
			if(!$args->start_second) $args->start_second = '00';
			if(strlen($args->start_hour)<2) $args->start_hour .= '0';
			if(strlen($args->start_minute)<2) $args->start_minute .= '0';
			if(strlen($args->start_second)<2) $args->start_second .= '0';

			// set end date
			if(!$args->start_date) {
				$args->start_date = date('YmdHis');
				if($args->ad_time != -1) $args->end_date = $oAdModel->dateAdd('h',$args->ad_time,date('YmdHis'));
				else $args->end_date = -1;
			} else {
				if($args->ad_time == -1)
				{
					$args->end_date = -1;
				}
				else
				{
					$date = $args->start_date.$args->start_hour.$args->start_minute.$args->start_second;
					$args->end_date = $oAdModel->dateAdd('h',$args->ad_time,$date);
				}
			}

			// set default value
			if($args->url && !preg_match('/^([a-z]+):\/\//i',$args->url)) $args->url = 'http://'.$args->url;
			if(!in_array($args->url_target,array('_self','_target'))) $args->url_target = '_blank';

			// executeQuery
			$obj->module_srl = $args->module_srl;
			$obj->document_srl = $args->document_srl;
			$obj->start_date = $date;
			$obj->end_date = $args->end_date;
			$obj->url = $args->url;
			$obj->url_target = $args->url_target;
			$obj->style = $args->style;
			$output = executeQuery('ad.insertAd', $obj);

			return $output;
		}

		/**
		 * 광고 삭제
		 **/
		public function deleteAd($document_srl, $delete_document = true)
		{
			if(!$document_srl)
			{
				return;
			}

			$obj = new stdClass();
			$obj->document_srl = $document_srl;

			// create model class of ad module
			$oAdModel = getModel('ad');

			// create model class of document model
			$oDocumentModel = getModel('document');

			// 광고 객체 구함
			$oAd = $oAdModel->getAd($document_srl, $this->grant->manager);

			// 광고가 존재하지 않으면 에러
			if(!$oAd->isExists()) return $oAdModel->returnMessage(-1, 'not_exists_ad');

			// 문서 삭제
			if($delete_document) {
				$oDocumentController = getController('document');
				$oDocumentController->deleteDocument($document_srl);
			}

			// 광고 삭제
			$output = executeQuery('ad.deleteAd', $obj);

			return $output;
		}

		/**
		 * @brief Specify the admin ID to a module
		 */
		public function insertAdminId($admin_id)
		{
			$oMemberModel = getModel('member');
			$member_config = $oMemberModel->getMemberConfig();

			$admin_id = trim($admin_id, ',');

			if($member_config->identifier == 'email_address')
			{
				$member_info = $oMemberModel->getMemberInfoByEmailAddress($admin_id);
			}
			else
			{
				$member_info = $oMemberModel->getMemberInfoByUserID($admin_id);
			}
			if(!$member_info->member_srl) return;

			$args = new stdClass();
			$args->member_srl = $member_info->member_srl;
			$output = executeQuery('ad.insertAdminId', $args);

			return $output;
		}

		/**
		 * @brief Remove the admin ID from a module
		 */
		function deleteAdminId($admin_id = '')
		{
			$oMemberModel = getModel('member');
			$member_config = $oMemberModel->getMemberConfig();

			$args = new stdClass();

			$admin_id = trim($admin_id, ',');
			if($admin_id)
			{
				$oMemberModel = getModel('member');
				if($member_config->identifier == 'email_address')
				{
					$member_info = $oMemberModel->getMemberInfoByEmailAddress($admin_id);
				}
				else
				{
					$member_info = $oMemberModel->getMemberInfoByUserID($admin_id);
				}
				if($member_info->member_srl) $args->member_srl = $member_info->member_srl;
			}

			return executeQuery('ad.deleteAdminId', $args);
		}
		/**
		 *
		 * @brief 광고 알림 처리용 trigger
		 * @return new Object
		 **/
		public function triggerNotifyAdTime(&$obj)
		{
			// 로그인 상태가 아닐 경우 실행 종료
			$logged_info = Context::get('logged_info');
			if(!$logged_info) return $this->makeObject();

			// 특정 모듈이면 실행 종료
			if(in_array(Context::get('module'),array('addon','admin','widget','editor'))) return $this->makeObject();
			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			// 알림 설정된 모듈 목록을 구함
			$cache_path = './files/cache/ad/';
			$cache_filename = sprintf('%snotify_modules.cache.php', $cache_path);

			// 광고 알림 모듈 캐시 파일이 없으면
			if(!file_exists($cache_filename)) {
				// 알림 설정된 모듈 목록을 db에서 찾음
				$module_srls = $oAdModel->getNotifyModuleSrls();

				// 캐시 설정
				FileHandler::writeFile($cache_filename, $module_srls);
			} else {
				$cache = trim(FileHandler::readFile($cache_filename));
				$modules_srl = str_replace('?>', strstr($cache, '?>'));
			}

			// 알림 설정된 모듈이 없으면 return new Object
			if(!$module_srls) return $this->makeObject();

			// 광고 알림 대상 목록 구함
			$cache_filename = sprintf('./files/cache/ad/%snotify_ad.cache.php', getNumberingPath($logged_info->member_srl));

			// 디버그 모드 (true : 활성화, false : 비활성화)
			$debug_mode = false;

			// 캐시 파일이 존재하면 읽어들이고 없다면 DB에서 찾음
			if(!file_exists($cache_filename) || (file_exists($cache_filename) && date('YmdHis',filemtime($cache_filename))+60*60 < date('YmdHis')) || $debug_mode) {
				$args->module_srl = $module_srls;
				$args->member_srl = $logged_info->member_srl;
				$args->end_date = date('YmdHis');
				$query = $oAdModel->getAdNotifyList($args);
				$notify_list = $query->data;

				// 캐시 지정
				$cache = sprintf('<?php exit(); ?>%s', serialize($query));
				FileHandler::writeFile($cache_filename, $cache);

				// 오류가 발생하거나 알림 대상 목록 결과가 없으면 종료
				if(!$query->toBool() || !$query->data) return $this->makeObject();
			} else {
				// 캐시 파일 읽기
				$cache = FileHandler::readFile($cache_filename);
				$query = unserialize(substr($cache, strpos($cache, '?>')+1));
				$notify_list = $query->data;
			}

			// 알림 대상 목록 결과가 있으면 쪽지 보내기
			if(count($notify_list)) {
				$member_srl = $logged_info->member_srl;
				$user_name = $logged_info->user_name;
				$nick_name = $logged_info->nick_name;

				// 관리자 회원 번호 구함
				$admin = $oAdModel->getAdminMemberSrl();

				// 쪽지의 제목과 내용을 미리 구해 놓음
				$title = Context::getLang('notify_title');
				$content = Context::getLang('notify_content');
				foreach($notify_list as $key => $val) {
					// 쪽지의 제목과 내용을 지정
					$title = $title['typeA'];
					$content = sprintf($content['typeA'],$nick_name,$nick_name,$val->getAdContent(),zdate($val->get('regdate')),zdate($val->get('end_date')));

					// 쪽지 보내기
					$msg_args->message_srl = getNextSequence();
					$msg_args->list_order = getNextSequence() * -1;
					$msg_args->sender_srl = $admin;
					$msg_args->receiver_srl = $member_srl;
					$msg_args->message_type = 'R';
					$msg_args->title = $title;
					$msg_args->content = $content;
					$msg_args->readed = 'N';
					$msg_args->regdate = $val->get('end_date');
					executeQuery('communication.sendMessage', $msg_args);

					// 계속 쪽지 보내는 것을 막기 위해 광고 삭제
					$this->deleteAd($val->document_srl, true, false);
				}
				// 캐시 파일 업데이트
				FileHandler::writeFile($cache_filename, 'N;');
			}
			return $this->makeObject();
		}

		/**
		 * @brief 모듈이 삭제될때 등록된 모든 광고를 삭제하는 trigger
		 * @return new Object()
		 **/
		function triggerDeleteModuleAds(&$obj) {
			$module_srl = $obj->module_srl;
			if(!$module_srl) return $this->makeObject();

			// 광고 모듈이 아니면 무시
			$oModuleModel = getModel('module');
			$oModuleInfo = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$oModuleInfo->module != 'ad') return $this->makeObject();

			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			// 등록된 광고 목록 구하기 (다중 테이블 접근을 지원하지 않아 두 번 query를 날려야 함 -_-;;)
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$args->select_document_srl = 'Y';
			$output = $oAdModel->getAdList($args);
			if(!$output->toBool()) return $output;

			$args = new stdClass();

			if(count($output->data)) {
				foreach($output->data as $key => $val) $documents[] = $val->document_srl;

				// 광고 삭제
				$args->document_srls = join(',',$documents);
				$output = executeQuery('ad.deleteModuleAds',$args);
				if(!$output->toBool()) return $output;
			}

			// 캐시 파일 업데이트
			$this->updateNotifyModuleCache();

			return $this->makeObject();
		}

		/**
		 * @brief 광고 알림 모듈 캐쉬 업데이트
		 * @return none
		 **/
		public function updateNotifyModuleCache()
		{
			// 광고 모듈의 model 객체 생성
			$oAdModel = getModel('ad');

			$output = $oAdModel->getNotifyModuleSrls();
			if(!$output->data) $output->data = array();

			if(count($output->data)) foreach($output->data as $key => $val) if($val->value == 'Y') $modules[] = $val->module_srl;

			if(is_array($modules)) $modules = join(',',$modules);

			$cache = sprintf('<?php exit(); ?>%s', $modules);
			FileHandler::writeFile('./files/cache/ad/notify_modules.cache.php', $cache);
		}

		/**
		 * @brief 광고 알림 모듈 캐쉬 삭제
		 * @return none
		 **/
		function deleteNotifyModuleCache() {
			FileHandler::removeFile('./files/cache/ad/notify_modules.cache.php');
		}

		/**
		 * 모든 캐쉬 삭제
		 *
		 * @return void
		 **/
		function deleteAllCache() {
			FileHandler::removeFilesInDir('./files/cache/ad/');
		}
	

		/**
		 * 기본 설정 > 설정 저장
		 * 
		 * @return void
		 **/
		public function procAdInsertConfig()
		{
			// get request config
			$config = getModel('ad')->getConfig();
			
			// 불필요한 값을 제거합니다.
			unset($config->body);
			unset($config->_filter);
			unset($config->error_return_url);
			unset($config->act);
			unset($config->mid);
			unset($config->module);
			unset($config->xe_validator_id);

			$config->admin_member = Context::get('admin_member');
			$config->display_today_ad = Context::get('display_today_ad') ==  'Y' ? 'Y' : 'N';

			$oMemberModel = getModel('member');
			$member_config = $oMemberModel->getMemberConfig();
			Context::set('member_config', $member_config);

			// update module config
			$oModuleController = getController('module');
			$oModuleController->insertModuleConfig('ad', $config);

			$admin_member = Context::get('admin_member');

			$this->deleteAdminId($admin_member);
			$this->insertAdminId($admin_member);

			// set message
			$this->setMessage('success_saved');

			$returnUrl = getNotEncodedUrl('', 'module', 'ad', 'act', 'dispAdConfig');
			$this->setRedirectUrl($returnUrl);
		}

		/**
		 * @brief 광고 모듈 생성 및 수정
		 **/
		function procAdInsertModule($args = null) {
			// 광고 모듈의 model / controller 객체 생성
			$oModuleController = getController('module');
			$oModuleModel = getModel('module');

			// ad module info
			$args = Context::getRequestVars();
			$args->module = 'ad';
			$args->mid = $args->ad_name;

			// 기본 값외의 것들을 정리
			if($args->ad_point<1) $args->ad_point = 0;
			if($args->ad_limit<1) $args->ad_list = 0;
			if($args->daily_limit<1) $args->daily_limit = 0;
			if($args->use_notify != 'Y') $args->use_notify = 'N';
			if($args->ad_type != 'image' && $args->ad_type != 'popup') $args->ad_type = 'text';

			// module_srl이 넘어오면 원 모듈이 있는지 확인
			if($args->module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
				if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
			}

			// module_srl의 값에 따라 insert/update
			if(!$args->module_srl) {
				$output = $oModuleController->insertModule($args);
				$msg_code = 'success_registed';
			} else {
				$output = $oModuleController->updateModule($args);
				$msg_code = 'success_updated';
			}

			if(!$output->toBool()) return $output;

			// update notify module cache
			if($module_info->use_notify && $module_info->use_notify != $args->use_notify) $this->updateNotifyModuleCache();

			// return
			$this->add('page',Context::get('page'));
			$this->add('module_srl',$output->get('module_srl'));

			// set message
			$this->setMessage($msg_code);
			if (Context::get('success_return_url')){
				changeValueInUrl('mid', $args->mid, $module_info->mid);
				$this->setRedirectUrl(Context::get('success_return_url'));
			}else{
				$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'ad', 'act', 'dispAdModuleInfo', 'module_srl', $output->get('module_srl')));
			}
		}

		/**
		 * @brief delete ad module
		 **/
		function procAdDeleteModule()
		{
			$module_srl = Context::get('module_srl');

			// delete module
			$oModuleController = getController('module');
			$output = $oModuleController->deleteModule($module_srl);
			if(!$output->toBool()) return $output;

			$this->add('module','ad');
			$this->add('page',Context::get('page'));
			// set message
			$this->setMessage('success_deleted');
		}

		/**
		 * 관리자 페이지에서 선택된 광고들을 삭제
		 **/
		public function procAdDeleteChecked()
		{
			// if not selected ad, return error
			$cart = Context::get('cart');
			if(!$cart)
			{
				return $this->stop('msg_cart_is_null2');
			}

			$document_srl_list = explode('|@|', $cart);
			$ad_count = count($document_srl_list);
			if(!$ad_count)
			{
				return $this->stop('msg_cart_is_null2');
			}

			$deleted_count = 0;

			$action = Context::get('action');

			// delete selected ad
			for($i=0;$i<$ad_count;$i++) {
				$document_srl = trim($document_srl_list[$i]);
				if(!$document_srl) continue;

				if($action === 'delete')
				{
					$output = $this->deleteAd($document_srl, true, false);
				}
				elseif($action === 'publish')
				{
					$args = new stdClass();
					$args->document_srl = $document_srl;
					$args->publish_status = 'published';
					$output = executeQuery('ad.updateAdPublishStatus', $args);
				}
				elseif($action === 'wait')
				{
					$args = new stdClass();
					$args->document_srl = $document_srl;
					$args->publish_status = 'wait';
					$output = executeQuery('ad.updateAdPublishStatus', $args);
				}

	
				if(!$output->toBool()) continue;

				$deleted_count ++;
			}
			
			$returnUrl = getNotEncodedUrl('', 'module', 'ad', 'act', 'dispAdManageList');
	
			// set message
			$this->setMessage(sprintf(Context::getLang('msg_checked_ad_is_deleted'), $deleted_count));
			$this->setRedirectUrl($returnUrl);
		}

		/**
		 * @brief 공지 등록
		 **/
		function procAdNoticeWrite() {
			// 권한 체크
			if(!$this->grant->manager) return $this->makeObject(-1, 'msg_not_permitted');

			// 공지 등록 시 필요한 변수를 세팅
			$obj = Context::getRequestVars();
			$obj->is_notice = 'Y';

			// 광고 내용이 없으면 에러
			settype($obj->ad_content, 'string');
			if($obj->ad_content == '') return $this->makeObject(-1, 'msg_invalid_request');

			// 기본값 지정
			$obj->title = $obj->ad_content; // 제목
			$obj->content = $obj->ad_content; // 내용
			$obj->allow_comment = 'N'; // 댓글 허용
			$obj->lock_comment = 'Y'; // 댓글 잠금
			if($obj->url && !preg_match('/^([a-z]+):\/\//i',$obj->url)) $obj->url = 'http://'.$obj->url; // URL (형식에 맞지 않으면 앞에 http://를 붙임)
			if(!in_array($obj->url_target,array('_self','_blank'))) $obj->url_target = '_blank'; // URL 열기 대상 (_self : 현재 창, _blank : 새 창)

			// 사용한 광고 옵션을 배열로 변환
			if($obj->used_style) $used_style = explode('|@|',$obj->used_style);

			// 글자색과 배경색이 같을 경우 오류
			if($obj->ad_color && $obj->ad_bgcolor && $obj->ad_color == $obj->ad_bgcolor && $obj->ad_color != -1 && $obj->ad_bgcolor != -1)
			{
				return $this->makeObject(-1, 'msg_invalid_color');
			}

			if($obj->ad_color == -1)
			{
				$obj->ad_color = null;
			}
	
			if($obj->ad_bgcolor == -1)
			{
				$obj->ad_bgcolor = null;
			}

			// 사용한 글자색을 배열에 저장
			if($obj->ad_color) {
				$used_style[] = 'text_'.$obj->ad_color;
				unset($obj->ad_color);
			}

			// 사용한 배경색을 배열에 저장
			if($obj->ad_bgcolor) {
				$used_style[] = 'bg_'.$obj->ad_bgcolor;
				unset($obj->ad_bgcolor);
			}

			// 사용한 광고 옵션 & 스타일 배열을 합치기
			if(is_array($used_style)) $obj->used_style = join('|@|',$used_style);
			else $obj->used_style = array();

			// check ad time (Firebug나 개발자 도구 등을 통해서 임의로 조작하는 것을 막기)
			if($this->module_info->use_time == 'Y') {
				$obj->ad_time = (int)$obj->ad_time;
				$AdTimeRange = explode(',',$this->module_info->ad_time_range);
				$AdTimeRange[] = -1;
				if(!$obj->ad_time || !in_array($obj->ad_time, $AdTimeRange))
				{
					return $this->makeObject(-1, 'msg_invalid_ad_time');
				}
			}
			
			// create model class of ad module
			$oAdModel = getModel('ad');

			// create controller class of document module
			$oDocumentController = getController('document');

			// 이미 존재하는 광고인지 체크
			$oAd = $oAdModel->getAd($obj->document_srl, $this->grant->manager);

			// 이미 존재하는 경우 에러 출력
			if($oAd->isExists() && $oAd->document_srl == $obj->document_srl) {
				return $this->makeObject(-1, 'msg_invalid_request');
			// 그렇지 않으면 신규 등록
			} else {
				// insert document
				$output = $oDocumentController->insertDocument($obj);
				$msg_code = 'success_registed';
				$obj->document_srl = $output->get('document_srl');
				if(!$output->toBool()) return $output;

				// insert notice
				$ad = new stdClass();
				$ad->module_srl = $obj->module_srl;
				$ad->document_srl = $obj->document_srl;
				$ad->ad_time = $obj->ad_time;
				$ad->url = $obj->url;
				$ad->url_target = $obj->url_target;
				$ad->style = $obj->used_style;
				$ad->start_date = $obj->start_date;
				$ad->start_hour = $obj->start_hour;
				$ad->start_minute = $obj->start_minute;
				$ad->start_second = $args->start_second;
				$output = $this->insertNotice($ad);
				if(!$output->toBool())
				{
					return $output;
				}
			}

			// set message
			$this->setMessage($msg_code);
		}
	}