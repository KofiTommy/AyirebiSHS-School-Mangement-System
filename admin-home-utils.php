<?php
/* Shared "home" resolver for admin-facing pages.
   Remembers which admin dashboard the user last came from (classic admin.php
   or the executive dashboard) so Home / back buttons return them to the
   right one. Include this file anywhere the helper is needed. */
if(!function_exists('admin_home_dashboard_url')){
    function admin_home_dashboard_url(){
        if(isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'super_user'){
            return 'super.php';
        }
        if(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === 'administrator'){
            $preferred = isset($_SESSION['ADMIN_HOME_DASHBOARD']) ? $_SESSION['ADMIN_HOME_DASHBOARD'] : '';
            if($preferred === 'admin.php' || $preferred === 'executive-dashboard.php'){
                return $preferred;
            }
            return 'executive-dashboard.php';
        }
        return 'admin.php';
    }
}
