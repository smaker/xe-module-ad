
/* 권한 등록 후 알림 메세지 */
function completeInsertGrant(ret_obj) {
    location.reload();
}

/* 관리자 아이디 등록/ 제거 */
function doInsertAdmin() {
    var fo_obj = get_by_id("fo_obj");
    var sel_obj = fo_obj._admin_member;
    var admin_id = fo_obj.admin_id.value;
    if(!admin_id) return;

    var opt = new Option(admin_id,admin_id,true,true);
    sel_obj.options[sel_obj.options.length] = opt;

    fo_obj.admin_id.value = '';
    sel_obj.size = sel_obj.options.length;
    sel_obj.selectedIndex = -1;

    var members = new Array();
    for(var i=0;i<sel_obj.options.length;i++) {
        members[members.length] = sel_obj.options[i].value;
    }

	jQuery(fo_obj.admin_member).val(members.join(','));
    fo_obj.admin_id.focus();
}

function doDeleteAdmin() {
    var fo_obj = get_by_id("fo_obj");
    var sel_obj = fo_obj._admin_member;
    sel_obj.remove(sel_obj.selectedIndex);

    sel_obj.size = sel_obj.options.length;
    sel_obj.selectedIndex = -1;

    var members = new Array();
    for(var i=0;i<sel_obj.options.length;i++) {
        members[members.length] = sel_obj.options[i].value;

    }
    jQuery(fo_obj.admin_member).val(members.join(','));
}

/* 모듈 생성 후 */
function completeInsertModule(ret_obj, response_tags) {
	var page = ret_obj['page'];
	var module_srl = ret_obj['module_srl'];

	alert(ret_obj['message']);

	var url = current_url.setQuery('act','dispAdModuleInfo');
	if(module_srl) url = url.setQuery('module_srl',module_srl);
	if(page) url.setQuery('page',page);
	location.href = url;
}

/* 모듈 삭제 후 */
function completeDeleteModule(ret_obj) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];
	var page = ret_obj['page'];
	alert(message);

	var url = current_url.setQuery('act','dispAdModuleList').setQuery('module_srl','');
	if(page) url = url.setQuery('page',page);
	location.href = url;
}


/* 공지 등록 후 */
function completeInsertNotice(ret_obj, response_tags) {
	var error = ret_obj['error'];
	var message = ret_obj['message'];

	alert(message);

	var url = current_url.setQuery('act','dispAdNoticeList');
	location.href = url;
}

/* 일괄 설정 */
function doCartSetup(url) {
	var module_srl = new Array();
	jQuery('#fo_list input[name=cart]:checked').each(function() {
		module_srl[module_srl.length] = jQuery(this).val();
	});

	if(module_srl.length<1) return;

	url += "&module_srls="+module_srl.join(',');
	popopen(url,'modulesSetup');
}