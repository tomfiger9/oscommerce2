<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2016 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\OSCOM;

  class cfgm_navbar_modules {
    var $code = 'navbar_modules';
    var $directory;
    var $language_directory;
    var $site = 'Shop';
    var $key = 'MODULE_CONTENT_NAVBAR_INSTALLED';
    var $title;
    var $template_integration = false;

    function __construct() {
      $this->directory = OSCOM::getConfig('dir_root', $this->site) . 'includes/modules/navbar_modules/';
      $this->language_directory = OSCOM::getConfig('dir_root', $this->site) . 'includes/languages/';
      $this->title = MODULE_CFG_MODULE_CONTENT_NAVBAR_TITLE;
    }
  }