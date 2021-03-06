<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2015 osCommerce

  Released under the GNU General Public License
*/

  use OSC\OM\HTML;
  use OSC\OM\OSCOM;

  require('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
  if (!isset($_SESSION['customer_id'])) {
    $_SESSION['navigation']->set_snapshot();
    OSCOM::redirect('login.php');
  }

// if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($_SESSION['cart']->count_contents() < 1) {
    OSCOM::redirect('shopping_cart.php');
  }

  $OSCOM_Language->loadDefinitions('checkout_shipping_address');

  require('includes/classes/order.php');
  $order = new order;

// if the order contains only virtual products, forward the customer to the billing page as
// a shipping address is not needed
  if ($order->content_type == 'virtual') {
    $_SESSION['shipping'] = false;
    $_SESSION['sendto'] = false;
    OSCOM::redirect('checkout_payment.php');
  }

  $error = false;
  $process = false;
  if (isset($_POST['action']) && ($_POST['action'] == 'submit') && isset($_POST['formid']) && ($_POST['formid'] == $_SESSION['sessiontoken'])) {
// process a new shipping address
    if (tep_not_null($_POST['firstname']) && tep_not_null($_POST['lastname']) && tep_not_null($_POST['street_address'])) {
      $process = true;

      if (ACCOUNT_GENDER == 'true') $gender = HTML::sanitize($_POST['gender']);
      if (ACCOUNT_COMPANY == 'true') $company = HTML::sanitize($_POST['company']);
      $firstname = HTML::sanitize($_POST['firstname']);
      $lastname = HTML::sanitize($_POST['lastname']);
      $street_address = HTML::sanitize($_POST['street_address']);
      if (ACCOUNT_SUBURB == 'true') $suburb = HTML::sanitize($_POST['suburb']);
      $postcode = HTML::sanitize($_POST['postcode']);
      $city = HTML::sanitize($_POST['city']);
      $country = HTML::sanitize($_POST['country']);
      if (ACCOUNT_STATE == 'true') {
        if (isset($_POST['zone_id'])) {
          $zone_id = HTML::sanitize($_POST['zone_id']);
        } else {
          $zone_id = false;
        }
        $state = HTML::sanitize($_POST['state']);
      }

      if (ACCOUNT_GENDER == 'true') {
        if ( ($gender != 'm') && ($gender != 'f') ) {
          $error = true;

          $messageStack->add('checkout_address', ENTRY_GENDER_ERROR);
        }
      }

      if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_FIRST_NAME_ERROR);
      }

      if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_LAST_NAME_ERROR);
      }

      if (strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_STREET_ADDRESS_ERROR);
      }

      if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_POST_CODE_ERROR);
      }

      if (strlen($city) < ENTRY_CITY_MIN_LENGTH) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_CITY_ERROR);
      }

      if (ACCOUNT_STATE == 'true') {
        $zone_id = 0;

        $Qcheck = $OSCOM_Db->prepare('select zone_id from :table_zones where zone_country_id = :zone_country_id');
        $Qcheck->bindInt(':zone_country_id', $country);
        $Qcheck->execute();

        $entry_state_has_zones = ($Qcheck->fetch() !== false);

        if ($entry_state_has_zones == true) {
          $Qzone = $OSCOM_Db->prepare('select distinct zone_id from :table_zones where zone_country_id = :zone_country_id and (zone_name = :zone_name or zone_code = :zone_code)');
          $Qzone->bindInt(':zone_country_id', $country);
          $Qzone->bindValue(':zone_name', $state);
          $Qzone->bindValue(':zone_code', $state);
          $Qzone->execute();

          if (count($Qzone->fetchAll()) === 1) {
            $zone_id = $Qzone->valueInt('zone_id');
          } else {
            $error = true;

            $messageStack->add('checkout_address', ENTRY_STATE_ERROR_SELECT);
          }
        } else {
          if (strlen($state) < ENTRY_STATE_MIN_LENGTH) {
            $error = true;

            $messageStack->add('checkout_address', ENTRY_STATE_ERROR);
          }
        }
      }

      if ( (is_numeric($country) == false) || ($country < 1) ) {
        $error = true;

        $messageStack->add('checkout_address', ENTRY_COUNTRY_ERROR);
      }

      if ($error == false) {
        $sql_data_array = array('customers_id' => $_SESSION['customer_id'],
                                'entry_firstname' => $firstname,
                                'entry_lastname' => $lastname,
                                'entry_street_address' => $street_address,
                                'entry_postcode' => $postcode,
                                'entry_city' => $city,
                                'entry_country_id' => $country);

        if (ACCOUNT_GENDER == 'true') $sql_data_array['entry_gender'] = $gender;
        if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = $company;
        if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = $suburb;
        if (ACCOUNT_STATE == 'true') {
          if ($zone_id > 0) {
            $sql_data_array['entry_zone_id'] = $zone_id;
            $sql_data_array['entry_state'] = '';
          } else {
            $sql_data_array['entry_zone_id'] = '0';
            $sql_data_array['entry_state'] = $state;
          }
        }

        $OSCOM_Db->save('address_book', $sql_data_array);

        $_SESSION['sendto'] = $OSCOM_Db->lastInsertId();

        if (isset($_SESSION['shipping'])) unset($_SESSION['shipping']);

        OSCOM::redirect('checkout_shipping.php');
      }
// process the selected shipping destination
    } elseif (isset($_POST['address'])) {
      $reset_shipping = false;
      if (isset($_SESSION['sendto'])) {
        if ($_SESSION['sendto'] != $_POST['address']) {
          if (isset($_SESSION['shipping'])) {
            $reset_shipping = true;
          }
        }
      }

      $_SESSION['sendto'] = $_POST['address'];

      $Qcheck = $OSCOM_Db->prepare('select address_book_id from :table_address_book where address_book_id = :address_book_id and customers_id = :customers_id');
      $Qcheck->bindInt(':address_book_id', $_SESSION['sendto']);
      $Qcheck->bindInt(':customers_id', $_SESSION['customer_id']);
      $Qcheck->execute();

      if ($Qcheck->fetch() !== false) {
        if ($reset_shipping == true) unset($_SESSION['shipping']);
        OSCOM::redirect('checkout_shipping.php');
      } else {
        unset($_SESSION['sendto']);
      }
    } else {
      $_SESSION['sendto'] = $_SESSION['customer_default_address_id'];

      OSCOM::redirect('checkout_shipping.php');
    }
  }

// if no shipping destination address was selected, use their own address as default
  if (!isset($_SESSION['sendto'])) {
    $_SESSION['sendto'] = $_SESSION['customer_default_address_id'];
  }

  $breadcrumb->add(NAVBAR_TITLE_1, OSCOM::link('checkout_shipping.php'));
  $breadcrumb->add(NAVBAR_TITLE_2, OSCOM::link('checkout_shipping_address.php'));

  $addresses_count = tep_count_customer_address_book_entries();

  require($oscTemplate->getFile('template_top.php'));
?>

<div class="page-header">
  <h1><?php echo HEADING_TITLE; ?></h1>
</div>

<?php
  if ($messageStack->size('checkout_address') > 0) {
    echo $messageStack->output('checkout_address');
  }
?>

<?php echo HTML::form('checkout_address', OSCOM::link('checkout_shipping_address.php'), 'post', 'class="form-horizontal"', ['tokenize' => true]); ?>

<div class="contentContainer">

<?php
  if ($process == false) {
?>

  <h2><?php echo TABLE_HEADING_SHIPPING_ADDRESS; ?></h2>

  <div class="contentText row">
    <div class="col-sm-8">
      <div class="alert alert-warning"><?php echo TEXT_SELECTED_SHIPPING_DESTINATION; ?></div>
    </div>
    <div class="col-sm-4">
      <div class="panel panel-primary">
        <div class="panel-heading"><?php echo TITLE_SHIPPING_ADDRESS; ?></div>

        <div class="panel-body">
          <?php echo tep_address_label($_SESSION['customer_id'], $_SESSION['sendto'], true, ' ', '<br />'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="clearfix"></div>

<?php
    if ($addresses_count > 1) {
?>

  <h2><?php echo TABLE_HEADING_ADDRESS_BOOK_ENTRIES; ?></h2>

  <div class="alert alert-info"><?php echo TEXT_SELECT_OTHER_SHIPPING_DESTINATION; ?></div>

  <div class="contentText row">

<?php
      $Qab = $OSCOM_Db->prepare('select address_book_id, entry_firstname as firstname, entry_lastname as lastname, entry_company as company, entry_street_address as street_address, entry_suburb as suburb, entry_city as city, entry_postcode as postcode, entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id from :table_address_book where customers_id = :customers_id order by firstname, lastname');
      $Qab->bindInt(':customers_id', $_SESSION['customer_id']);
      $Qab->execute();

      while ($Qab->fetch()) {
        $format_id = tep_get_address_format_id($Qab->valueInt('country_id'));
?>

      <div class="col-sm-4">
        <div class="panel panel-<?php echo ($Qab->valueInt('address_book_id') == $_SESSION['sendto']) ? 'primary' : 'default'; ?>">
          <div class="panel-heading"><?php echo HTML::outputProtected($Qab->value('firstname') . ' ' . $Qab->value('lastname')); ?></strong></div>
          <div class="panel-body">
            <?php echo tep_address_format($format_id, $Qab->toArray(), true, ' ', '<br />'); ?>
          </div>
          <div class="panel-footer text-center"><?php echo HTML::radioField('address', $Qab->valueInt('address_book_id'), ($Qab->valueInt('address_book_id') == $_SESSION['sendto'])); ?></div>
        </div>
      </div>

<?php
      }
?>

  </div>

<?php
    }
  }

  if ($addresses_count < MAX_ADDRESS_BOOK_ENTRIES) {
?>

  <h2><?php echo TABLE_HEADING_NEW_SHIPPING_ADDRESS; ?></h2>

  <div class="alert alert-info"><?php echo TEXT_CREATE_NEW_SHIPPING_ADDRESS; ?></div>

  <?php require('includes/modules/checkout_new_address.php'); ?>

<?php
  }
?>

  <div class="buttonSet">
    <div class="text-right"><?php echo HTML::hiddenField('action', 'submit') . HTML::button(IMAGE_BUTTON_CONTINUE, 'fa fa-angle-right', null, null, 'btn-success'); ?></div>
  </div>

  <div class="clearfix"></div>

  <div class="contentText">
    <div class="stepwizard">
      <div class="stepwizard-row">
        <div class="stepwizard-step">
          <button type="button" class="btn btn-primary btn-circle">1</button>
          <p><?php echo CHECKOUT_BAR_DELIVERY; ?></p>
        </div>
        <div class="stepwizard-step">
          <button type="button" class="btn btn-default btn-circle" disabled="disabled">2</button>
          <p><?php echo CHECKOUT_BAR_PAYMENT; ?></p>
        </div>
        <div class="stepwizard-step">
          <button type="button" class="btn btn-default btn-circle" disabled="disabled">3</button>
          <p><?php echo CHECKOUT_BAR_CONFIRMATION; ?></p>
        </div>
      </div>
    </div>
  </div>


<?php
  if ($process == true) {
?>

  <div class="buttonSet">
    <?php echo HTML::button(IMAGE_BUTTON_BACK, 'fa fa-angle-left', OSCOM::link('checkout_shipping_address.php')); ?>
  </div>

<?php
  }
?>

</div>

</form>

<?php
  require($oscTemplate->getFile('template_bottom.php'));
  require('includes/application_bottom.php');
?>
