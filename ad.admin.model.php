<?php
/**
 * @class  AdAdminModel
 * @author 퍼니XE <funnyxe@simplesoft.io>
 * @brief  광고 모듈의 admin model 객체
 **/

class adAdminModel extends ad
{

	/**
	 * 초기화
	 **/
	public function init()
	{
	}

	/**
	 * @brief Common:: module's permission displaying page in the module
	 * Available when using module instance in all the modules
	 */
	function getModuleGrantHTML($module_srl, $source_grant_list)
	{
		if(!$module_srl)
		{
			return;
		}

		// get member module's config
		$oMemberModel = getModel('member');
		$member_config = $oMemberModel->getMemberConfig();
		Context::set('member_config', $member_config);

		$oModuleModel = getModel('module');
		$columnList = array('module_srl', 'site_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl, $columnList);
		// Grant virtual permission for access and manager
		$grant_list = new stdClass();
		$grant_list->access = new stdClass();
		$grant_list->access->title = Context::getLang('grant_access');
		$grant_list->access->default = 'guest';
		if(count($source_grant_list))
		{
			foreach($source_grant_list as $key => $val)
			{
				if(!$val->default) $val->default = 'guest';
				if($val->default == 'root') $val->default = 'manager';
				$grant_list->{$key} = $val;
			}
		}
		$grant_list->manager = new stdClass();
		$grant_list->manager->title = Context::getLang('grant_manager');
		$grant_list->manager->default = 'manager';
		Context::set('grant_list', $grant_list);
		// Get a permission group granted to the current module
		$default_grant = array();
		$args = new stdClass();
		$args->module_srl = $module_srl;
		$output = executeQueryArray('module.getModuleGrants', $args);
		if($output->data)
		{
			foreach($output->data as $val)
			{
				if($val->group_srl == 0) $default_grant[$val->name] = 'all';
				else if($val->group_srl == -1) $default_grant[$val->name] = 'member';
				else if($val->group_srl == -2) $default_grant[$val->name] = 'site';
				else if($val->group_srl == -3) $default_grant[$val->name] = 'manager';
				else
				{
					$selected_group[$val->name][] = $val->group_srl;
					$default_grant[$val->name] = 'group';
				}
			}
		}
		Context::set('selected_group', $selected_group);
		Context::set('default_grant', $default_grant);
		Context::set('module_srl', $module_srl);
		// Extract admin ID set in the current module
		$admin_member = $oModuleModel->getAdminId($module_srl);
		Context::set('admin_member', $admin_member);
		// Get a list of groups
		$oMemberModel = getModel('member');
		$group_list = $oMemberModel->getGroups($module_info->site_srl);
		Context::set('group_list', $group_list);

		//Security			
		$security = new Security();
		$security->encodeHTML('group_list..title');
		$security->encodeHTML('group_list..description');
		$security->encodeHTML('admin_member..nick_name');

		// Get information of module_grants
		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($this->module_path.'tpl', 'module_grants');
	}

	/**
	 * @brief Common:: skin setting page for the module
	 */
	function getModuleSkinHTML($module_srl)
	{
		return $this->_getModuleSkinHTML($module_srl, 'P');
	}


	/**
	 * Skin setting page for the module
	 *
	 * @param $module_srl sequence of module
	 * @param $mode P or M
	 * @return string The HTML code
	 */
	function _getModuleSkinHTML($module_srl, $mode)
	{
		$mode = $mode === 'P' ? 'P' : 'M';

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!$module_info) return;

		if($mode === 'P')
		{
			if($module_info->is_skin_fix == 'N')
			{
				$skin = $oModuleModel->getModuleDefaultSkin($module_info->module, 'P', $module_info->site_srl);
			}
			else
			{
				$skin = $module_info->skin;
			}
		}
		else
		{
			if($module_info->is_mskin_fix == 'N')
			{
				$skin = $oModuleModel->getModuleDefaultSkin($module_info->module, 'M', $module_info->site_srl);
			}
			else
			{
				$skin = $module_info->mskin;
			}
		}

		$module_path = './modules/'.$module_info->module;

		// Get XML information of the skin and skin sinformation set in DB
		if($mode === 'P')
		{
			$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin);
			$skin_vars = $oModuleModel->getModuleSkinVars($module_srl);
		}
		else
		{
			$skin_info = $oModuleModel->loadSkinInfo($module_path, $skin, 'm.skins');
			$skin_vars = $oModuleModel->getModuleMobileSkinVars($module_srl);
		}

		if(count($skin_info->extra_vars)) 
		{
			foreach($skin_info->extra_vars as $key => $val) 
			{
				$group = $val->group;
				$name = $val->name;
				$type = $val->type;
				if($skin_vars[$name]) 
				{
					$value = $skin_vars[$name]->value;
				}
				else $value = '';
				if($type=="checkbox")
				{
					$value = $value?unserialize($value):array();
				}

				$value = empty($value) ? $val->default : $value;
				$skin_info->extra_vars[$key]->value= $value;
			}
		}

		Context::set('module_info', $module_info);
		Context::set('mid', $module_info->mid);
		Context::set('skin_info', $skin_info);
		Context::set('skin_vars', $skin_vars);
		Context::set('mode', $mode);

		//Security
		$security = new Security(); 
		$security->encodeHTML('mid');
		$security->encodeHTML('module_info.browser_title');
		$security->encodeHTML('skin_info...');

		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($this->module_path.'tpl', 'skin_config');
	}
}
