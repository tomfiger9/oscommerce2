<?php
/**
  * osCommerce Online Merchant
  *
  * @copyright (c) 2016 osCommerce; https://www.oscommerce.com
  * @license GPL; https://www.oscommerce.com/gpllicense.txt
  */

namespace OSC\OM\Module\Hooks\Shop\Session;

class Recreated
{
    public function execute($parameters) {
// reset session token
        $_SESSION['sessiontoken'] = md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());
    }
}
