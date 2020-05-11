<?php

if(!defined('__XE__'))
{
    exit;
}

class adMobile extends adView
{
    public function init()
    {
        $path = sprintf('%sm.skins/%s', $this->module_path . $this->module_info->mskin);
        $this->setTemplatePath($path);

        // 광고 시간 범위를 구해서 set
        if($this->module_info->use_time == 'Y')
        {
            $ad_time_range = getModel('ad')->getAdTimeRange($this->module_info);
            Context::set('ad_time_range', $ad_time_range);
        }
    }

    public function dispAdContent()
    {
        parent::dispAdContent();
    }
}