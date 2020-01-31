<?php /** @noinspection SqlResolve */
  /**
   * Plugin Name: Product Locations CSV Importer
   * Description: Import product locations with custom fields.
   * Author: Silicon Surfers
   * Text Domain: Product Locations CSV Importer
   * Version: 1.1
   */

  /**
   * Reads two CSV files and parses their headers.
   * Merges the Products and Locations files and places them in WordPress.
   * N.B.: Uses Google's Map API to assign real world locations (especially longitude and latitude) to each product in each store.
   *       This can be costly!
   *
   * Always checks the DB first, so that Google Maps API is ONLY used if the location is nat already know in the DB.
   *
   * Data is inserted into WordPress in such a way that WP Store Location can use it correctly.
   *
   * N.B.: All <File Name> files are MASTER FILES!! NOT additions or partial modifications to what exists!
   *
   */

  define('PLCI_bFLAG_PRIVATE'                              , false);                               // DO NOT begin by flaging all Locations private. N.B.: This means that any locatoins that are no longer valid will have to be removed by hand.
  define('PLCI_bLOAD_ALL_PRODUCTS'                         , false);                               // Load a FAKE store with ALL PRODUCTS so no one wonders why their favorite item is missing from the drop down.

  define('PLCI_bLOCATIONS_ONLY'                            , false);                               // Do NOT touch terms,
  define('PLCI_bTERMS_ONLY'                                , true);                                // Skip all loading of Locations and go directly to just handling Product Locations (Terms).
  if (PLCI_bTERMS_ONLY) {
    define('PLCI_bGEOCODING'                               , false);                               // DO NOT GEOCODE!
  } else {
    define('PLCI_bGEOCODING'                               , false);                               // Handle GEOCODING
  }

  define('PLCI_bHANDLE_ZIP_CODE'                           , true);                                // Deal with normal AND enhanced Zip Codes.
  define('PLCI_bREQIRE_LAT_LNG'                            , true);                                // ONLY load aLocations that have LAT and LNG defined.
  define('PLCI_bUPDATE_ADDRESSES'                          , true);                                // Allow address updating. N.B.: With this off, no bGEOCODING will happen.

  define('PLCI_iLOAD_FILE_CHUNK'                           , 2);
  define('PLCI_iMAX_LINE_COUNT'                            , 250);
  define('PLCI_iMAX_LINE_COUNT2'                           , PLCI_iMAX_LINE_COUNT * 3);

  define('PLCI_sDELIMITER'                                 , ',');
  define('PLCI_sPOST_TYPE'                                 , 'wpsl_stores');
  define('PLCI_sTAXONOMY_SLUG'                             , 'wpsl_store_category');

  if (!defined('WP_LOAD_IMPORTERS')) {
return;
  }

  require_once ABSPATH . 'wp-admin/includes/import.php';

  if (!class_exists('WP_Importer')) {
    $sClassWPImporter                                      = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
    if (file_exists($sClassWPImporter)) {
      /** @noinspection PhpIncludeInspection */
      require_once $sClassWPImporter;
    }
  }

  require dirname(__FILE__) . '/plci_CSV_Helper.php';
  require dirname(__FILE__) . '/plci_Post_Helper.php';

  if (class_exists('WP_Importer')) {
    /**
     * Class Product_Locations_CSV_Importer
     */
    class Product_Locations_CSV_Importer extends WP_Importer {
      private $aDistributors                               = [];
      private $aLocationsMaster;
      private $aIDsMaster;
      private $aMissingUPCs                                = [];
      private $aProducts                                   = [];

      private $bCompletedHandlingLocations;
      private $bShortUPC;
      private $bStoreColumn;

      private $iFirstLine;
      private $iLine;
      private $iLocationsFileId;
      private $iProductsFileId;
      private $iStep;
      private $iUpdated;

      private $sGoogleAPIKey;

      /**
       * @see      https://stackoverflow.com/questions/31553959/phpstorm-method-not-found-in-subject-class
       * @var      \plci_CSV_Helper
       */
      private $oLocations;

      private $sLocationsFileName;
      private $sProductsFileName;
      private $sDistributorsClause;

      private function Add_All_Products(
      ) {
        // ToDo: Need to just delete them ALL first including ALL Products (wp_post) and wp_terms, wp_term_relationships & wp_term_taxonomy.
        //       N.B.: Locations are missing on purpose, so don't need deleting.
        $AAP_aNO_META                                      = [];
        $AAP_bIS_INSERT                                    = false;
        $AAP_bNO_LOCATION                                  = false;

        $aPost['post_title']                               = 'Store #: 0';
        $aPost['post_status']                              = 'publish';
        $aPost['post_type']                                = PLCI_sPOST_TYPE;

        foreach ($this->aProducts as $iProduct => $aProduct) {                                     // Set Products
          $aTerms                                          = $this->Assign_aTerms($aProduct);

          $this->Save_Post($aPost, $AAP_aNO_META, $aTerms, $AAP_bIS_INSERT, $AAP_bNO_LOCATION);
          unset($aTerms);                                                                          // save memory space!
        }
      }

      /**
       * Only add new wp_postmeta stuff if we don't already have it.
       * N.B.: In this case we'll also have to add the wp_post entry as well.
       *       We'll NEED this because the rest of the code will expect at least one wp_post with it's associated wp_postmeta address stuff in place!
       *
       * @return   bool
       */
      private function Add_Locations_Lat_Lng(
      ) {
        $ALLL_aNO_TERMS                                    = [];

        $ALLL_bNEW_LOCATION                                = true;

        $sOriginalAddress                                  = (string)$this->oLocations->Get_Cell('Street Address') . ' ' .
                                                             (string)$this->oLocations->Get_Cell('City') . ' ' .
                                                             (string)$this->oLocations->Get_Cell('State') . ' ' .
                                                             $this->Load_Zip()
        ;
        $sOriginalAddress                                  = strtolower(str_replace('  ', ' ', $sOriginalAddress));
        if (isset($this->aIDsMaster[$sOriginalAddress])) {                                         // We found a match, so no need to change anything!
      return true;
        }

        // if we're here, we didn't find an existing address. This MAY  mean we didn't find this store!
        $oStore                                            = $this->Get_Store_Info();
        $aPost['post_name']                                = $oStore->sPostName;
        $aPost['post_title']                               = $oStore->sPostTitle;

        $aPost['post_content']                             = $sOriginalAddress;
        $aPost['post_status']                              ='publish';
        $aPost['post_type']                                = PLCI_sPOST_TYPE;

        $aMeta                                             = $this->Handle_GeoLocation($sOriginalAddress);

        // If the $aPost already exists, we need to set it's ID so it can be UPDATED rather than INSERTED!
        $aGetPost                                          = $this->Get_Post($oStore->sPostName);
        if (true === ($bIsUpdate                           = (0 < count($aGetPost)))) {
          $aPost['ID']                                     = $aGetPost[0]->ID;
        }
        $oResult                                           = $this->Save_Post($aPost, $aMeta, $ALLL_aNO_TERMS, $bIsUpdate, $ALLL_bNEW_LOCATION);
        if ($oResult->Is_Error()) {
          echo '<br><br>' . esc_html($oResult->Get_Error()->get_error_message()) . '<br>';
      return false;
        } else {                                                                                   // Add this to our lookup arrays (stops repeated lookups on the same address)...
          $oLocation                                       = new stdClass();
          $oLocation->sPostName                            = $oResult->Get_Post()->post_name;
          $oLocation->wpsl_address                         = $aMeta['wpsl_address'];
          $oLocation->wpsl_city                            = $aMeta['wpsl_city'   ];
          $oLocation->wpsl_state                           = $aMeta['wpsl_state'  ];
          $oLocation->wpsl_zip                             = $aMeta['wpsl_zip'    ];
          if (isset($aMeta['wpsl_lat'])) {
            $oLocation->wpsl_lat                           = $aMeta['wpsl_lat'    ];
            $oLocation->wpsl_lng                           = $aMeta['wpsl_lng'    ];
          }

          $this->Add_To_Lookup_Arrays((int   )$oResult->Get_Post()->ID, $sOriginalAddress, $oLocation);
        }
      return true;
        }

      /**
       * @return   bool
       */
      private function Add_Product_Locations(
      ) {
        $APL_aNO_META                                      = [];
        $APL_bIS_UPDATE                                    = true;
        $APL_bLOCATION_EXISTS                              = false;

        if (false === $this->Read_To_Current_Row()) {
    return false;
        }

        $this->iLine                                       = 0;
        echo 'Adding Products for Product Locations from ' . $this->iFirstLine . ' to ' . ($this->iFirstLine + PLCI_iMAX_LINE_COUNT2) . '<br>';
        while (false !== ($this->oLocations->Read_Row_CSV())) {
          $this->iLine++;

          if (PLCI_iMAX_LINE_COUNT2 < $this->iLine) {
$this->Restart();
// N.B.: RESTART: Multiple Restarts are expected!
          }

          if (false === ($sUPC                             = $this->Handle_aMissingUPCs())) {
        continue;
          }

          $oError                                          = new WP_Error();

          $oStore                                          = $this->Get_Store_Info();

          $aPost['post_name']                              = $oStore->sPostName;
          $aPost['post_title']                             = $oStore->sPostTitle;
          $aPost['post_status']                            ='publish';
          $aPost['post_type']                              = PLCI_sPOST_TYPE;
          $aGetPost                                        = $this->Get_Post($oStore->sPostName);
          if (true === ($bIsUpdate                         = (0 < count($aGetPost)))) {
            $aPost['ID']                                   = $aGetPost[0]->ID;
          }

          $aTerms                                          = $this->Assign_aTerms($this->aProducts[$sUPC]);

          if (!$oError->get_error_codes()) {
            $oResult                                       = $this->Save_Post($aPost, $APL_aNO_META, $aTerms, $bIsUpdate, $APL_bLOCATION_EXISTS);
            if ($oResult->Is_Error()) {
              $oError                                      = $oResult->Get_Error();
            }
          }

          foreach ($oError->get_error_messages() as $sMessage) {                                   // show error messages
            echo '<br><br>' . esc_html($sMessage) . '<br>';
          }

          unset($aPost);                                                                           // save memory space!
          unset($aTerms);                                                                          // save memory space!
        }
        $this->oLocations->Close_CSV();

      return true;
      }

      /**
       * @param    int            $iID
       * @param    string         $sOriginalAddress
       * @param    \stdClass      $oLocation
       */
      private function Add_To_Lookup_Arrays(
                   $iID,
                   $sOriginalAddress,
                   $oLocation
      ) {
        $this->aIDsMaster[$sOriginalAddress]             = $iID;
        $this->aLocationsMaster[$sOriginalAddress]       = $oLocation;
      }

      /**
       * @param    array          $aProduct
       * @return   array
       */
      private function Assign_aTerms(
        array      $aProduct
      ) {
        $aTerms                                            = [];
        $aTerms[PLCI_sTAXONOMY_SLUG]                       = [];
        $aTerms[PLCI_sTAXONOMY_SLUG][]                     = $aProduct['sBrand'];
        $aTerms[PLCI_sTAXONOMY_SLUG][]                     = $aProduct['sDescription'];
        $aTerms[PLCI_sTAXONOMY_SLUG][]                     = (string)$aProduct['fNetWtOz'] . 'oz.-' . $aProduct['sOriginalUPC'];

      return $aTerms;
      }

      /**
       * @return   bool
       */
      private function Create_List_Of_Distributors(
      ) {
        if ($this->bStoreColumn) {
          if (false === $this->oLocations->Reset_CSV()) {
      return false;
          }
          while (false !== ($bnResult                      = $this->oLocations->Read_Row_CSV())) {
            if (is_null($bnResult)) {                                                              // We've got some kind of a problem!
      return false;
            }
            $sDistributor                                  = $this->oLocations->Get_Cell('Store');
            if (false === in_array($sDistributor, $this->aDistributors)) {
              $this->aDistributors[]                       = $sDistributor;
            }
          }
        } else {
          $this->aDistributors[]                           = 'walmart';
        }
      return true;
      }

      private function Create_Locations_Master_File(
      ) {
        global $wpdb;

        /*
         * Grab all current Google Address Information...
         */
        // @see: https://stackoverflow.com/questions/20780422/wordpress-get-plugin-directory
        $sSelectQuery                                      = "
select
  wp.post_name,
  wp.post_content, 
  wpm.meta_key,
  wpm.meta_value
from 
  {$wpdb->prefix}postmeta                                  as wpm,
  {$wpdb->prefix}posts                                     as wp
where
  (wp.post_type                                            = 'wpsl_stores')
and
  (wp.ID                                                   = wpm.post_id)
and
  (wpm.meta_key                                            like 'wpsl_%')
order by 
  wp.post_name
";
        $aPosts                                            = $wpdb->get_results($sSelectQuery);
        if (!is_null($aPosts)) {
          $aLocations                                      = [];
          $iLocation                                       = -1;
          foreach ($aPosts as $iPost => $oPost) {
            if ('wpsl_address' === $oPost->meta_key) {
              $iLocation++;
              $aLocations[$iLocation]['post_name'   ]      = $oPost->post_name;
              $aLocations[$iLocation]['post_content']      = $oPost->post_content;
            }
            $sMetaValue                                    = str_replace(',', '\,', trim($oPost->meta_value));
            $aLocations[$iLocation][$oPost->meta_key]      = $sMetaValue;
          }

          $sPluginsDirectory                               = ABSPATH . 'wp-content/plugins/product-locations-csv-importer/';
          rename($sPluginsDirectory . 'Locations Master File.csv', $sPluginsDirectory . 'Locations Master File.' . (string)time() . '.old');
          $rLocationsMasterFile                            = fopen($sPluginsDirectory . 'Locations Master File.csv', 'w');

          fwrite($rLocationsMasterFile, 'post_name, sStatus, sOriginalAddress, wpsl_address, wpsl_city, wpsl_state, wpsl_zip, wpsl_country, wpsl_country_iso, wpsl_lat, wpsl_lng');
          foreach ($aLocations as $iLocation => $aLocation) {
            if ((!isset($aLocation['wpsl_lat'])) || ('' === $aLocation['wpsl_lat'])) {
              $sStatus                                     = 'lookup required';
            } else {
              $sStatus                                     = 'current';
            }

            fwrite($rLocationsMasterFile,
              "\r\n" .
              $aLocation['post_name'       ] . ', ' .
              $sStatus                       . ', ' .
              $aLocation['post_content'    ] . ', ' .
              $aLocation['wpsl_address'    ] . ', ' .
              $aLocation['wpsl_city'       ] . ', ' .
              $aLocation['wpsl_state'      ] . ', ' .
              $aLocation['wpsl_zip'        ] . ', ' .
              $aLocation['wpsl_country'    ] . ', ' .
              $aLocation['wpsl_country_iso']
            );

            if (isset($aLocation['wpsl_lat'])) {
              fwrite($rLocationsMasterFile,
                ', ' . $aLocation['wpsl_lat'] .
                ', ' . $aLocation['wpsl_lng']
              );
            }
          }

          fclose($rLocationsMasterFile);
          echo 'Locations Master File.csv ready!';
        }
      }

      /**
       * @param    integer        $iObjectId
       * @return   bool
       */
      private function Delete_A_Product_From_DB(
                                  $iObjectId
      ) {
        global $wpdb;

        /*
         * Delete from wp_terms
         */
        $iObjectId                                         = $wpdb->_real_escape($iObjectId);
        $sDeleteQuery                                      = "
delete 
  wt
from 
  {$wpdb->prefix}terms                                     as wt,
  {$wpdb->prefix}term_relationships                        as wtr
where
  (wt.term_id                                              = wtr.term_taxonomy_id)
and
  (wtr.object_id                                           = {$iObjectId})
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_term_taxonomy
         */
        $sDeleteQuery                                      = "
delete
  wtt
from 
  {$wpdb->prefix}term_taxonomy                             as wtt,
  {$wpdb->prefix}term_relationships                        as wtr
where
  (wtt.term_taxonomy_id                                    = wtr.term_taxonomy_id)
and
  (wtr.object_id                                           = {$iObjectId})    
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_term_relationships based on term_taxonomy_id and wp_term_taxonomy.taxonomy = 'wpsl_store_category'.
         */
        $sDeleteQuery                                      = "
delete 
from 
  {$wpdb->prefix}term_relationships
where
  object_id                                                = {$iObjectId}
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

      return true;
      }

      /**
       * @param    string         $sPostTitle
       * @return   bool
       */
      private function Delete_Post_Title_From_DB(
        $sPostTitle
      ) {
        global $wpdb;

        /*
         * Delete from wp_postmeta based on post_id and wp_posts.post_type = 'wpsl_stores'.
         */
        $sPostTitle                                        = $wpdb->_real_escape($sPostTitle);
        $sDeleteQuery                                      = "
delete 
  wpm
from 
  {$wpdb->prefix}postmeta                                  as wpm,
  {$wpdb->prefix}posts                                     as wp
where
  (wp.post_type                                            = 'wpsl_stores')
and 
  (wp.post_title                                           = '{$sPostTitle}')
and
  (wp.ID                                                   = wpm.post_id);
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_terms
         */
        $sDeleteQuery                                      = "
delete 
  wt
from 
  {$wpdb->prefix}terms                                     as wt,
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}posts                                     as wp
where
  (wp.post_type                                            = 'wpsl_stores')
and 
  (wp.post_title                                           = '{$sPostTitle}')
and 
  (wp.ID                                                   = wtr.object_id)
and
  (wt.term_id                                              = wtr.term_taxonomy_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_term_taxonomy
         */
        $sDeleteQuery                                      = "
delete
  wtt
from 
  {$wpdb->prefix}term_taxonomy                             as wtt,
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}posts                                     as wp
where
  (wp.post_type                                            = 'wpsl_stores')
and 
  (wp.post_title                                           = '{$sPostTitle}')
and 
  (wp.ID                                                   = wtr.object_id)
and
  (wtt.term_taxonomy_id                                    = wtr.term_taxonomy_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_term_relationships based on term_taxonomy_id and wp_term_taxonomy.taxonomy = 'wpsl_store_category'.
         */
        $sDeleteQuery                                      = "
delete 
  wtr
from 
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}posts                                     as wp
where
  (wp.post_type                                            = 'wpsl_stores')
and 
  (wp.post_title                                           = '{$sPostTitle}')
and 
  (wp.ID                                                   = wtr.object_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_posts based on post_type = 'wpsl_stores'
         */
        $sDeleteQuery                                      = "
delete 
from 
  {$wpdb->prefix}posts 
where 
  (post_type                                               = 'wpsl_stores')
and 
  (post_title                                              = '{$sPostTitle}')
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

      return true;
      }

      /**
       * N.B.: We don't try to remove the import file enteries. These are actually tied to physical files now in the WP file structure. The WP system will remove them in due course.
       *
       * @return   bool
       */
      private function Delete_Products_From_DB(
      ) {
        global $wpdb;

        /*
         * Delete from wp_term_relationships based on term_taxonomy_id and wp_term_taxonomy.taxonomy = 'wpsl_store_category'.
         */
        $sDeleteQuery                                      = "
delete 
  wtr
from 
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}term_taxonomy                             as wtt
where
  (wtt.taxonomy                                            = 'wpsl_store_category')
and 
  (wtt.term_taxonomy_id                                    = wtr.term_taxonomy_id);
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_terms based on term_id and wp_term_taxonomy.taxonomy = 'wpsl_store_category'.
         */
        $sDeleteQuery                                      = "
delete 
  wt
from 
  {$wpdb->prefix}terms                                     as wt,
  {$wpdb->prefix}term_taxonomy                             as wtt
where
  (wtt.taxonomy                                            = 'wpsl_store_category') 
and 
  (wtt.term_id                                             = wt.term_id);
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /*
         * Delete from wp_term_taxonomy based on taxonomy = 'wpsl_store_category'.
         */
        $sDeleteQuery                                      = "
delete
from 
  wp_term_taxonomy
where
  taxonomy                                                 = 'wpsl_store_category';
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        echo '<p><strong>' . __('All products have been DELETED.', 'product-locations-csv-importer') . '</strong></p>';

      return true;
      }

      /**
       * @return    bool
       */
      private function Flag_Product_Locations_Private_In_DB(
      ) {
        global $wpdb;

        /**
         * Update all wp_posts in aDistributors to 'private'
         */
        $sUpdateQuery                                      = "
update 
  {$wpdb->prefix}posts                                     as wp
set
  wp.post_status                                           = 'private'
where 
  wp.post_type                                             = 'wpsl_stores'
{$this->sDistributorsClause}
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sUpdateQuery))) {
      return false;
        }

      return true;
      }

      private function Footer(
      ) {
        echo '</div>';
      }

      /**
       * @param    string         $sOriginalAddress
       *
       * @return   array|boolean
       */
      private function Get_Lat_Lon(
                   $sOriginalAddress
      ) {
        if (!PLCI_bUPDATE_ADDRESSES || !PLCI_bGEOCODING) {
      return false;
        }
        echo 'Getting geocoding for: ' . $sOriginalAddress . '...<br>';

        $aGeoLocation                                      = [];
        $sLocation                                         = str_replace(' ', '+', $sOriginalAddress);    // Formatted address
        $sURL                                              = 'https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->sGoogleAPIKey . '&address=' . $sLocation . '&sensor=false';
        $iRetryCount                                       = 0;
        while ($iRetryCount < 3) {                                                                 // Send request and receive json data by address or zip
          $oCURLHandle                                     = curl_init($sURL);
          curl_setopt($oCURLHandle, CURLOPT_RETURNTRANSFER, 1);
          $sGeocode                                        = curl_exec($oCURLHandle);

          if (curl_getinfo($oCURLHandle, CURLINFO_HTTP_CODE) === 200) {
            $oOutput                                       = json_decode($sGeocode);

            if (isset($oOutput) && is_object($oOutput) && isset($oOutput->results) && isset($oOutput->results[0])) {   // Get latitude and longitute from json data
              $aGeoLocation['fLat']                        = $oOutput->results[0]->geometry->location->lat;
              $aGeoLocation['fLng']                        = $oOutput->results[0]->geometry->location->lng;
              $aGeoLocation['aAddressComponents']          = $oOutput->results[0]->address_components;

              if (!empty($aGeoLocation)) {                                                         // Return latitude and longitude of the given address
                curl_close($oCURLHandle);
      return $aGeoLocation;
              }
            }
          } else {
            curl_close($oCURLHandle);
            $oError                                        = json_decode($sGeocode);
            if (isset($oError) && is_object($oError) && isset($oError->error_message)) {
      return false;
            }
          }

          $iRetryCount++;
          if ($iRetryCount < 3) {
            sleep(65 * $iRetryCount);
          }
        }

      return false;
      }

      /**
       * N.B.: This code might become BROKEN if WP Store Locator makes changes!!
       *
       * @return   false|string
       */
      private function Get_sGoogleAPIKey(
      ) {
        global $wpdb;

        $sSelectQuery                                      = "
select             
  * 
from
  {$wpdb->prefix}options
where
  option_name                                              = 'wpsl_settings'
";
        $aResults                                          = $wpdb->get_results($sSelectQuery, OBJECT);
        if (is_null($aResults)) {
      return false;
        }
        if (!isset($aResults[0]->option_value)) {
      return false;
        }

        $sResults                                          = $aResults[0]->option_value;
        if (is_null($aResults)) {
      return false;
        }

        // json_decode isn't working so I'm going to use substr. YUCK!
        $sResults                                          = substr($sResults, strpos($sResults, 'api_server_key') + 19);
        $sResults                                          = substr($sResults, strpos($sResults, '"') + 1);
      return substr($sResults, 0, strpos($sResults, '"'));
      }

      /**
       * @param    string         $sPostName
       * @return   array
       */
      private function Get_Post(
                   $sPostName
      ) {
        global $wpdb;

        $sSelectQuery                                      = "
select
  ID,
  post_content,
  post_type
from 
  {$wpdb->prefix}posts
where
  post_type                                                = 'wpsl_stores'
and
  post_name                                                = '{$wpdb->_real_escape($sPostName)}';
";
      return $wpdb->get_results($sSelectQuery);
      }

      /**
       * @return  \stdClass
       */
      private function Get_Store_Info(
      ) {
        $oStore                                            = new stdClass();

        if ($this->bStoreColumn) {
          $oStore->sPostTitle                              = (string)$this->oLocations->Get_Cell('Store');
          $iStoreNumber                                    = (int   )$this->oLocations->Get_Cell('Store Number');
        } else {
          $oStore->sPostTitle                              = 'Walmart';
          $iStoreNumber                                    = (int   )$this->oLocations->Get_Cell('Store Nbr');
        }

        $oStore->sPostName                                 = strtolower(str_replace(' ', '-', $oStore->sPostTitle)) . '-' . (string)$iStoreNumber;
      return $oStore;
      }

      private function Greet(
      ) {
        echo '<p>' . __('Choose a products and a locations CSV (.csv) file to upload, then click Upload files and import.', 'product-locations-csv-importer') . '</p>';
        echo '<p>' . __('Requirements:', 'product-locations-csv-importer') . '</p>';
        echo '<ol>';
        echo '<li>' . __('Select UTF-8 as charset.', 'product-locations-csv-importer') . '</li>';
        echo '<li>' . sprintf(__('You must use field delimiter as "%s"', 'product-locations-csv-importer'), PLCI_sDELIMITER) . '</li>';
        echo '<li>' . __('You must quote all text cells.', 'product-locations-csv-importer') . '</li>';
        echo '</ol>';

        $aArgs                                             = [
          'iStep'                                          => 1,
          'sLF'                                            => $this->sLocationsFileName,
          'sPF'                                            => $this->sProductsFileName
        ];

        WP_Import_Uploads_Form(add_query_arg($aArgs));
      }

      /**
       * Check for $this->aMissingUPCs...
       *
       * @return   string|bool
       */
      private function Handle_aMissingUPCs(
      ) {
        $sUPC                                            = (string)$this->oLocations->Get_Cell('UPC');
        if (!array_key_exists($sUPC, $this->aProducts)) {
          if (array_key_exists($sUPC, $this->aMissingUPCs)) {
            $this->aMissingUPCs[$sUPC]++;
          } else {
            $this->aMissingUPCs[$sUPC]                   = 1;
          }
      return false;                                                                                // No matching $sProductId in $aProducts
        }

      return $sUPC;
      }

      /**
       * @param    string         $sOriginalAddress
       * @return   array
       */
      private function Handle_GeoLocation(
                   $sOriginalAddress
      ) {
        $aMeta                                             = [];
        if (false === ($aGeoLocation                       = $this->Get_Lat_Lon($sOriginalAddress))) {
          $aMeta['wpsl_address']                           = (string)$this->oLocations->Get_Cell('Street Address');
          $aMeta['wpsl_city']                              = (string)$this->oLocations->Get_Cell('City');
          $aMeta['wpsl_state']                             = (string)$this->oLocations->Get_Cell('State');
          $aMeta['wpsl_zip']                               = $this->Load_Zip();
          $aMeta['wpsl_country']                           = 'United States';
          $aMeta['wpsl_country_iso']                       = 'US';
        } else {
          $aMeta['wpsl_lat']                               = (string) $aGeoLocation['fLat'];
          $aMeta['wpsl_lng']                               = (string) $aGeoLocation['fLng'];

          $aAddressComponents                              = $aGeoLocation['aAddressComponents'];
          $iAddressComponentsCount                         = count($aAddressComponents);
          $sStreetNumber                                   = '';
          $bLocality                                       = false;
          for ($iAddressComponent = 0; $iAddressComponent < $iAddressComponentsCount; $iAddressComponent++) {
            switch ($aAddressComponents[$iAddressComponent]->types[0]) {
              case 'street_number':
                $sStreetNumber                             = $aAddressComponents[$iAddressComponent]->short_name;
            break;

              case 'route':
                if ('' === $sStreetNumber) {
                  $sStreetAddress                          = $aAddressComponents[$iAddressComponent]->short_name;
                } else {
                  $sStreetAddress                          = $sStreetNumber . ' ' . $aAddressComponents[$iAddressComponent]->short_name;
                }
                $aMeta['wpsl_address']                     = $sStreetAddress;
            break;

              case 'locality':
                $aMeta['wpsl_city']                        = $aAddressComponents[$iAddressComponent]->short_name;
                $bLocality                                 = true;
            break;

              case 'administrative_area_level_3':
                if (!$bLocality) {
                  $aMeta['wpsl_city']                      = $aAddressComponents[$iAddressComponent]->short_name;
                }
            break;

              case 'administrative_area_level_1':
                $aMeta['wpsl_state']                       = $aAddressComponents[$iAddressComponent]->short_name;
            break;

/**
 * Canned as 'United States' and country_iso canned as 'US'
              case 'country':
                $aMeta['wpsl_country']                     = $aAddressComponents[$iAddressComponent]->long_name;
            break;
*/

              case 'postal_code':
                if (5 < strlen($this->Load_Zip())) {
                  $aMeta['wpsl_zip']                       = $aAddressComponents[$iAddressComponent]->long_name;
                } else {
                  $aMeta['wpsl_zip']                       = $aAddressComponents[$iAddressComponent]->short_name;
                }
            break;
            }
          }
        }
      return $aMeta;
      }

      /**
       * @param    \WP_Error|int|bool  $oResult
       * @return   bool|int
       */
      private function Handle_WP_Errors(
                   $oResult
      ) {
        if (is_wp_error($oResult)) {
          echo $oResult->get_error_message();
      return false;
        }

      return $oResult;
      }

      private function Header(
      ) {
        echo '<div class="wrap">';
        echo '<h2>'. __('Import CSV', 'product-locations-csv-importer').'</h2>';
      }

      private function Import(
      ) {
        $aFile['fileLocations']                            = WP_Import_Handle_Uploads($this->iStep, 'importLocations', 'sLF', 'iLI');
        if (isset($aFile['fileLocations']['error'])) {
          echo '<p><strong>' . __('Sorry, there has been an error.', 'product-locations-csv-importer') . '</strong><br />';
          echo esc_html($aFile['fileLocations']['error']) . '</p>';
      return false;
        } else if (!file_exists($aFile['fileLocations']['sFile'])) {
          echo '<p><strong>' . __('Sorry, there has been an error.', 'product-locations-csv-importer') . '</strong><br />';
          printf(__('The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'product-locations-csv-importer'), esc_html($aFile['file']));
          echo '</p>';
      return false;
        }

        $aFile['fileProducts']                             = WP_Import_Handle_Uploads($this->iStep, 'importProducts' , 'sPF', 'iPI');
        if (isset($aFile['fileProducts']['error'])) {
          echo '<p><strong>' . __('Sorry, there has been an error.', 'product-locations-csv-importer') . '</strong><br />';
          echo esc_html($aFile['fileProducts']['error']) . '</p>';
      return false;
        } else if (!file_exists($aFile['fileProducts']['sFile'])) {
          echo '<p><strong>' . __('Sorry, there has been an error.', 'product-locations-csv-importer') . '</strong><br />';
          printf(__('The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', 'product-locations-csv-importer'), esc_html($aFile['file']));
          echo '</p>';
      return false;
        }

        $this->iLocationsFileId                            = (int) $aFile['fileLocations']['iId'];
        $this->iProductsFileId                             = (int) $aFile['fileProducts' ]['iId'];

        $this->sLocationsFileName                          = get_attached_file($this->iLocationsFileId);
        $this->sProductsFileName                           = get_attached_file($this->iProductsFileId);

        if (false === $this->Handle_WP_Errors($this->Process_Posts())) {
      return false;
        }

      return true;
      }

      private function Load_Locations_And_IDs_Master(
      ) {
        global $wpdb;

        $this->sDistributorsClause                         = "
and (
    (wp.post_name                                          like '{$wpdb->_real_escape(strtolower(str_replace(' ', '-', $this->aDistributors[            0])))}-%')
";
        $iMaxDistributors                                  = count($this->aDistributors);
        for($iDistributor = 1; $iDistributor < $iMaxDistributors; $iDistributor++) {
          $this->sDistributorsClause                      .= "
  or
    (wp.post_name                                          like '{$wpdb->_real_escape(strtolower(str_replace(' ', '-', $this->aDistributors[$iDistributor])))}-%')
";
        }
        $this->sDistributorsClause                        .= ")";
        $sSelectCurrentQuery                               = "
select
  wp.ID                                                    as iID,
  wp.post_name                                             as sPostName,
  wp.post_content                                          as sOriginalAddress,
  address.meta_value                                       as wpsl_address,
  city.meta_value                                          as wpsl_city,
  state.meta_value                                         as wpsl_state,
  zip.meta_value                                           as wpsl_zip,
  lat.meta_value                                           as wpsl_lat,
  lng.meta_value                                           as wpsl_lng
from 
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}postmeta                                  as address,
  {$wpdb->prefix}postmeta                                  as city,
  {$wpdb->prefix}postmeta                                  as state,
  {$wpdb->prefix}postmeta                                  as zip,
  {$wpdb->prefix}postmeta                                  as lat,
  {$wpdb->prefix}postmeta                                  as lng
where
  (wp.post_type                                            = 'wpsl_stores')
{$this->sDistributorsClause}
and
  (wp.ID                                                   = address.post_id)
and
  (address.meta_key                                        = 'wpsl_address')
and
  (wp.ID                                                   = city.post_id)
and
  (city.meta_key                                           = 'wpsl_city')
and
  (wp.ID                                                   = state.post_id)
and
  (state.meta_key                                          = 'wpsl_state')
and
  (wp.ID                                                   = zip.post_id)
and
  (zip.meta_key                                            = 'wpsl_zip')
and
  (wp.ID                                                   = lat.post_id)
and
  (lat.meta_key                                            = 'wpsl_lat')
and
  (wp.ID                                                   = lng.post_id)
and
  (lng.meta_key                                            = 'wpsl_lng')
";

        $sSelectLookupRequiredQuery                        = "
select
  wp.ID                                                    as iID,
  wp.post_name                                             as sPostName,
  wp.post_content                                          as sOriginalAddress, 
  address.meta_value                                       as wpsl_address,
  city.meta_value                                          as wpsl_city,
  state.meta_value                                         as wpsl_state,
  zip.meta_value                                           as wpsl_zip,
  ''                                                       as wpsl_lat,
  ''                                                       as wpsl_lng
from 
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}postmeta                                  as address,
  {$wpdb->prefix}postmeta                                  as city,
  {$wpdb->prefix}postmeta                                  as state,
  {$wpdb->prefix}postmeta                                  as zip
where
  (wp.post_type                                            = 'wpsl_stores')
{$this->sDistributorsClause}
and
  (wp.ID                                                   = address.post_id)
and
  (address.meta_key                                        = 'wpsl_address')
and
  (wp.ID                                                   = city.post_id)
and
  (city.meta_key                                           = 'wpsl_city')
and
  (wp.ID                                                   = state.post_id)
and
  (state.meta_key                                          = 'wpsl_state')
and
  (wp.ID                                                   = zip.post_id)
and
  (zip.meta_key                                            = 'wpsl_zip')
and
  (not exists
    (select 
       * 
     from
       {$wpdb->prefix}postmeta                             as lookup_required
     where
       (wp.ID                                              = lookup_required.post_id)
     and
       (lookup_required.meta_key                           = 'wpsl_lat')
    )
  )
";

        $aLocations                                        = [];
        $aCurrentPosts                                     = $wpdb->get_results($sSelectCurrentQuery);
        if (!is_null($aCurrentPosts)) {
          $aLocations                                      = $aCurrentPosts;
        }

        if (!PLCI_bREQIRE_LAT_LNG) {
          $aLookupRequiredPosts                            = $wpdb->get_results($sSelectLookupRequiredQuery);
          if (!is_null($aCurrentPosts)) {
            $aLocations                                    = array_merge($aLocations, $aLookupRequiredPosts);
          }
        }

        if (0 === count($aLocations)) {                                                            // It's ok to have no matched aLocations!
      return true;
        }

        foreach ($aLocations as $oLocation) {                                                      // Create the array lookups so we won't have to hit the DB again.
          $iID                                             = (int   )$oLocation->iID;
          $sOriginalAddress                                = (string)$oLocation->sOriginalAddress;

          unset($oLocation->iID);                                                                  // Save a tiny bit of memory!
          unset($oLocation->sOriginalAddress);                                                     // Save a tiny bit of memory!

          $this->Add_To_Lookup_Arrays($iID, $sOriginalAddress, $oLocation);
        }
      return true;
      }

      /**
       * @return   bool
       */
      private function Load_Products(
      ) {
        // Can we open, read and parse the Products file...
        if (false === ($oProducts                          = new plci_CSV_Helper($this->sProductsFileName, 'Products ', $this->iProductsFileId))) {
      return false;
        }

        while (false !== ($oProducts->Read_Row_CSV())) {
          $sOriginalUPC                                    = (string)$oProducts->Get_Cell('MANUF ID');
          if ($this->bShortUPC) {                                                                  // No -, so we need to adjust MANUF ID
            $sUPC                                          = substr($sOriginalUPC, 2, -2);
            $sUPC                                          = str_replace('-', '', $sUPC);
          } else {                                                                                 // We found -, so we need to add leading '00'
            $sUPC                                          = '00' . $sOriginalUPC;
            $sUPC                                          = substr($sUPC, 0, 9) . substr($sUPC, 10);
          }

          $sDescription                                    = (string)$oProducts->Get_Cell('PRODUCT DESCRIPTION');
          $sNetWtOz                                        = (string)$oProducts->Get_Cell('NET WT OZ');
          $sBrand                                          = (string)$oProducts->Get_Cell('BRAND');

          if (is_null($sOriginalUPC) || is_null($sDescription) || is_null($sNetWtOz) || is_null($sBrand)) {
        continue;                                                                                  // skip this stuff!
          }

          $this->aProducts[$sUPC]['sBrand']                = $sBrand;
          $this->aProducts[$sUPC]['sDescription']          = $sDescription;
          $this->aProducts[$sUPC]['fNetWtOz']              = (float )$sNetWtOz;
          $this->aProducts[$sUPC]['sOriginalUPC']          = $sOriginalUPC;
        }
        $oProducts->Close_CSV();

      return true;
      }

      /**
       * @return   false|string
       */
      private function Load_Zip(
      ) {
        $sZipCode                                          = (string)$this->oLocations->Get_Cell('Zip Code');
        if (is_null($sZipCode)) {
      return '';
        } else {
          if (PLCI_bHANDLE_ZIP_CODE) {                                                             // Make sure there are no missing leading 0s
            if (false === strpos($sZipCode, '-')) {
      return substr('00000' . $sZipCode, -5);
            } else {                                                                               // Long zip code
      return substr('00000' . $sZipCode, -10);
            }
          } else {
      return $sZipCode;
          }
        }
      }

      /**
       * Read through the Locations file to our current row.
       *
       * @return   bool
       */
      private function Read_To_Current_Row(
      ) {
        // N.B.: $this.iFirstLine is set in Dispatch!
        if (false === $this->oLocations->Reset_CSV()) {
      return false;
        }
        for ($iLineCount = 0; $iLineCount < $this->iFirstLine; $iLineCount++) {                    // Read to current row
          $this->oLocations->Read_Row_CSV();
        }
      return true;
      }

      private function Remove_Existing_Products(
      ) {
        global $wpdb;

        /**
         * Delete from wp_term_taxonomy
         */
        $sDeleteQuery                                      = "
delete 
  wtt
from 
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}term_taxonomy                             as wtt
where
  wp.post_type                                             = 'wpsl_stores'
{$this->sDistributorsClause}
and
  (wp.ID                                                   = wtr.object_id)
and
  (wtr.term_taxonomy_id                                    = wtt.term_taxonomy_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /**
         * Delete from wp_terms
         */
        $sDeleteQuery                                      = "
delete 
  wt
from 
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}term_relationships                        as wtr,
  {$wpdb->prefix}terms                                     as wt
where
  wp.post_type                                             = 'wpsl_stores'
{$this->sDistributorsClause}
and
  (wp.ID                                                   = wtr.object_id)
and
  (wtr.term_taxonomy_id                                    = wt.term_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

        /**
         * Delete from wp_term_relationships
         */
        $sDeleteQuery                                      = "
delete
  wtr
from 
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}term_relationships                        as wtr
where
  wp.post_type                                             = 'wpsl_stores'
{$this->sDistributorsClause}
and
  (wp.ID                                                   = wtr.object_id)
";
        if (false === $this->Handle_WP_Errors($wpdb->query($sDeleteQuery))) {
      return false;
        }

      return true;
      }

      private function Restart(
      ) {
        if ($this->bCompletedHandlingLocations) {
          $this->iFirstLine                               += PLCI_iMAX_LINE_COUNT2;
          $aArgs                                           = [
            'iFL'                                          => $this->iFirstLine,
            'iStep'                                        => 2,
            'sLF'                                          => $this->sLocationsFileName,
            'iLI'                                          => $this->iLocationsFileId,
            'sPF'                                          => $this->sProductsFileName,
            'iPI'                                          => $this->iProductsFileId,
            'iU'                                           => $this->iUpdated,
            'bMC'                                          => 1
          ];
        } else {
          $this->iFirstLine                               += PLCI_iMAX_LINE_COUNT;
          $aArgs                                           = [
            'iFL'                                          => $this->iFirstLine,
            'iStep'                                        => 2,
            'sLF'                                          => $this->sLocationsFileName,
            'iLI'                                          => $this->iLocationsFileId,
            'sPF'                                          => $this->sProductsFileName,
            'iPI'                                          => $this->iProductsFileId,
            'iU'                                           => $this->iUpdated,
            'bMC'                                          => 0
          ];
        }
        $sRedirectURL                                      = get_http_origin() . esc_url(wp_nonce_url(add_query_arg($aArgs)));
        echo '<META HTTP-EQUIV=REFRESH CONTENT="0;url=' . $sRedirectURL . '">';
die;
      }

      /**
       * @param    array          $aPost
       * @param    array          $aMeta
       * @param    array          $aTerms
       * @param    boolean        $bIsUpdate
       * @param    boolean        $bNewLocation
       *
       * @return \plci_Post_Helper
       */
      private function Save_Post(
        array                     $aPost,
        array                     $aMeta,
        array                     $aTerms,
                                  $bIsUpdate,
                                  $bNewLocation
      ) {
        if ($bIsUpdate) {                                                                          // Add or update the post
          $oPost                                           = plci_Post_Helper::Get_By_ID($aPost['ID']);
          if (PLCI_bUPDATE_ADDRESSES && $bNewLocation) {
            $oPost->Update($aPost);
          }
        } else {
          $oPost                                           = plci_Post_Helper::Add($aPost);
        }

        if (PLCI_bUPDATE_ADDRESSES) {
          if ($bNewLocation && (0 < count($aMeta))) {
            $oPost->Set_Meta($aMeta);                                                              // Set meta data
          }
        }

        if (0 < count($aTerms)) {
          foreach ($aTerms as $sTaxonomySlug => $aNames) {                                         // Set terms
            $oPost->Set_Object_Terms($sTaxonomySlug, $aNames);
          }
        }
      return $oPost;
      }

      /**
       * If the UPC column is absent, we are out of business.
       *
       * @return   bool
       */
      private function Set_Locations_Characteristics(
      ) {
        // Is there a bStoreColumn?
        $this->oLocations->Read_Row_CSV();
        $this->bStoreColumn                                =  $this->oLocations->Get_Cell('Store');

        // Make sure we have a proper UPC column in the Locations file...
        if (false === ($sTestUPC                           = $this->oLocations->Get_Cell('UPC'))) {
      return $$this->oLocations->Close_CSV('Error: UPC column not found in ');
        }

        // What size of UPC are we dealing with?
        $this->bShortUPC                                   = (false === strpos($sTestUPC, '-'));

      return true;
      }

      private function Summarize_Results(
      ) {
        global $wpdb;

        $aTerms                                            = get_terms(
          PLCI_sTAXONOMY_SLUG,
          [
            'hide_empty'                                   => 0,
            'fields'                                       => 'ids'
          ]
        );

        if (!empty($aTerms)) {
          wp_update_term_count_now($aTerms, PLCI_sTAXONOMY_SLUG);
        }

        echo '<span style="font-family: monospace;white-space: pre;">';
        echo '<br>Total lines processed: ' . str_pad((string)($this->iFirstLine + $this->iLine)                  , 6, ' ', STR_PAD_LEFT);
        echo '<br>Updates              : ' . str_pad((string) $this->iUpdated                                    , 6, ' ', STR_PAD_LEFT);
        echo '<br>New lines            : ' . str_pad((string)($this->iFirstLine + $this->iLine - $this->iUpdated), 6, ' ', STR_PAD_LEFT);
        echo '</span><br>';

        arsort($this->aMissingUPCs);
        foreach($this->aMissingUPCs as $iMissingUPC => $iMissingUPCCount) {
          echo '<br>No product found for UPC: <span style="font-family: monospace">' . (string)$iMissingUPC . '</span>.';
          echo ' # of stores affected: <span style="font-family: monospace;white-space: pre;">' . str_pad((string)$iMissingUPCCount, 5, ' ', STR_PAD_LEFT) . '</span>';
        }

        $sSelectQuery                                      = "
select distinct
  post_name, post_content
from   
  {$wpdb->prefix}posts
where  
  post_type                                                = 'wpsl_stores'
and
not EXISTS (
  select 
    post_id
  from
    {$wpdb->prefix}postmeta
  where
    meta_key                                               = 'wpsl_lat'
  and
    post_id                                                = {$wpdb->prefix}posts.ID
);
";
        $aResults                                          = $wpdb->get_results($sSelectQuery, OBJECT);
        if (0 < count($aResults)) {
          echo '<br><br>The following locations could not be resolved by Google\'s Map API. This means they will not be available in the search...';
          foreach ($aResults as $oResult) {
            echo '<br>' . $oResult->post_name . ': ' . $oResult->post_content;
          }
        }
      }

      private function Count_Stores(
        $sStoreName
      ) {
        global $wpdb;
        $sSelectQuery                                      = "
select
  count(wp.ID) as NumberOfDists
from
  {$wpdb->prefix}posts as wp
where
  wp.post_type = 'wpsl_stores'
and
  wp.post_title = '{$sStoreName}'
";
        $aResults                                          = $wpdb->get_results($sSelectQuery, OBJECT);
        echo '<br>';
        echo '<br>' . $sStoreName . ': ' . $aResults[0]->NumberOfDists;
      }

      /**
       * N.B.: We don't try to remove the import file enteries. These are actually tied to physical files now in the WP file structure. The WP system will remove them in due course.
       */
      public function Delete_Import_From_DB(
      ) {
//        $this->Create_Locations_Master_File();
        global $wpdb;
        $sQuery                                            = "
update {$wpdb->prefix}posts
set post_status = 'publish'
where post_status = 'private'
and post_type = 'wpsl_stores'
";

/*
        $sDeleteQuery                                      = "
delete
from {$wpdb->prefix}posts
where post_type = 'wpsl_stores' and 
      post_content = '';
";
*/
/*
        $sDeleteQuery                                      = "
delete 
from
  {$wpdb->prefix}posts
where (
    post_title = '{$wpdb->_real_escape('AG Birmingham')}' 
  or
    post_title = '{$wpdb->_real_escape('Bi-Lo')}'
  or
    post_title = '{$wpdb->_real_escape('Food City')}'
  or
    post_title = '{$wpdb->_real_escape('Food Lion')}'
  or
    post_title = '{$wpdb->_real_escape('Fresco y Mas')}'
  or
    post_title = '{$wpdb->_real_escape('Galaxy')}'
  or
    post_title = '{$wpdb->_real_escape('Harris Teeter')}'
  or
    post_title = '{$wpdb->_real_escape('IGA')}'
  or
    post_title = '{$wpdb->_real_escape('Ingles')}'
  or
    post_title = '{$wpdb->_real_escape('JH Harvey')}'
  or
    post_title = '{$wpdb->_real_escape('Laurel Grocery')}'
  or
    post_title = '{$wpdb->_real_escape('Lowes Foods')}'
  or
    post_title = '{$wpdb->_real_escape('Nash Finch - Fresh Foods')}'
  or
    post_title = '{$wpdb->_real_escape("Nash Finch - Hill's")}'
  or
    post_title = '{$wpdb->_real_escape('Nash Finch - Lumberton')}'
  or
    post_title = '{$wpdb->_real_escape('Nash Finch - Piggly Wiggly')}'
  or
    post_title = '{$wpdb->_real_escape('Piggly Wiggly')}'
  or
    post_title = '{$wpdb->_real_escape('Publix')}'
  or
    post_title = '{$wpdb->_real_escape('W Lee Flowers')}'
  or
    post_title = '{$wpdb->_real_escape('Walmart')}'
)
and
  post_type = 'revision';
";
*/
/*
        $sDeleteQuery                                      = "
delete {$wpdb->prefix}postmeta, {$wpdb->prefix}posts 
from {$wpdb->prefix}postmeta, {$wpdb->prefix}posts
WHERE 
    ({$wpdb->prefix}posts.post_date > '2020-01-19') and 
    ({$wpdb->prefix}posts.post_type = 'wpsl_stores') and
    ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id);
";
*/
/*
select distinct
  wp.post_content,
  wp.post_name
from
  {$wpdb->prefix}posts                                     as wp,
  {$wpdb->prefix}postmeta                                  as wpm
where
  wp.post_type = 'wpsl_stores'
and
  wp.post_title <> 'Walmart'
and (
  not exists (
    select
      *
    from
      {$wpdb->prefix}postmeta                              as lookup_required
    where
      (wp.ID                                               = lookup_required.post_id)
    and
      (lookup_required.meta_key                            = 'wpsl_lat')
  )
);
\";
*/
        echo 'Done!';
        $aResults                                          = $wpdb->get_results($sQuery, OBJECT);
        if (0 < count($aResults)) {
          echo '<br><br>Duplicates...';
          foreach ($aResults as $oResult) {
            echo '<br>' . $oResult->ID . ' -> ' . $oResult->post_name . ': ' . $oResult->post_title;
          }
        }
die;
        $this->Count_Stores('AG Birmingham');
        $this->Count_Stores('Bi-Lo');
        $this->Count_Stores('Food City');
        $this->Count_Stores('Food Lion');
        $this->Count_Stores('Fresco y Mas');
        $this->Count_Stores('Galaxy');
        $this->Count_Stores('Harris Teeter');
        $this->Count_Stores('IGA');
        $this->Count_Stores('Ingles');
        $this->Count_Stores('JH Harvey');
        $this->Count_Stores('Laurel Grocery');
        $this->Count_Stores('Lowes Foods');
        $this->Count_Stores('Nash Finch - Fresh Foods');
        $this->Count_Stores('Nash Finch - Hill\'s');
        $this->Count_Stores('Nash Finch - Lumberton');
        $this->Count_Stores('Nash Finch - Piggly Wiggly');
        $this->Count_Stores('Piggly Wiggly');
        $this->Count_Stores('Publix');
        $this->Count_Stores('W Lee Flowers');
        $this->Count_Stores('Walmart');
die;

        $this->Delete_Post_Title_From_DB('Food Lion');
        $this->Delete_Post_Title_From_DB('Harris Teeter');
        $this->Delete_Post_Title_From_DB('Ingles');
        $this->Delete_Post_Title_From_DB('Lowes Foods');
        $this->Delete_Post_Title_From_DB('IGA');
        $this->Delete_Post_Title_From_DB('Galaxy');
        $this->Delete_Post_Title_From_DB('Publix');
        $this->Delete_Post_Title_From_DB('Food City');
        $this->Delete_Post_Title_From_DB('Piggly Wiggly');
        $this->Delete_Post_Title_From_DB('W Lee Flowers');
        $this->Delete_Post_Title_From_DB('Bi-Lo');
        $this->Delete_Post_Title_From_DB('AG Birmingham');
        $this->Delete_Post_Title_From_DB('Laurel Grocery');
        $this->Delete_Post_Title_From_DB('Nash Finch - Piggly Wiggly');
        $this->Delete_Post_Title_From_DB('JH Harvey');
        $this->Delete_Post_Title_From_DB('Nash Finch - Hill\'s');
        $this->Delete_Post_Title_From_DB('Nash Finch - Fresh Foods');
        $this->Delete_Post_Title_From_DB('Nash Finch - Lumberton');
        $this->Delete_Post_Title_From_DB('Fresco y Mas');

        echo '<p><strong>' . __('All import rows have been DELETED.', 'product-locations-csv-importer') . '</strong></p>';
      }

      /**
       * @return   bool
       */
      public function Dispatch(
      ) {                                                                        // dispatcher
        $this->bCompletedHandlingLocations                 = false;

        $this->Header();

        if (empty ($_GET['iStep'])) {
          $this->iStep                                     = 0;
        } else {
          $this->iStep                                     = (int   )$_GET['iStep'];
        }

        switch ($this->iStep) {
          case 0:
            $this->Greet();
        break;

          case 1:
            if (isset($_POST['deleteProducts'])) {
              if ('yes' === strtolower($_POST['deleteProducts'])) {
                $this->Delete_Import_From_DB();
              } else {
                echo '<p><strong>' . __('Sorry, you must supply the correct answer to ERASE imports from the database!', 'product-locations-csv-importer') . '</strong></p>';
              }
            } else {
              $this->iFirstLine                            = 0;
              $this->iUpdated                              = 0;
              check_admin_referer('import-upload');
              set_time_limit(0);

              if (false === $this->Handle_WP_Errors($this->Import())) {
      return false;
              }
            }
        break;

          case PLCI_iLOAD_FILE_CHUNK:
            if (isset($_GET['iFL'])) {
              $this->bCompletedHandlingLocations           = (bool  )$_GET['bMC'];
              $this->iFirstLine                            = (int   )$_GET['iFL'];
              $this->iUpdated                              = (int   )$_GET['iU'];

              if (false === $this->Handle_WP_Errors($this->Import())) {
      return false;
              }
            }
        break;
        }

        $this->Footer();

      return true;
      }

      public function Process_Posts(
      ) {                                                                                          // process parse csv and insert posts
        if (PLCI_bTERMS_ONLY) {
          if ((0 === $this->iFirstLine) && (false === $this->bCompletedHandlingLocations)) {
            $this->bCompletedHandlingLocations             = true;
            $this->iFirstLine                              = -1 * PLCI_iMAX_LINE_COUNT2;
$this->Restart();
          }
        }
// N.B.: RESTART: Multiple Restarts are expected!
        // Can we open, read and parse the Locations file...
        if (false === ($this->oLocations                   = new plci_CSV_Helper($this->sLocationsFileName, 'Locations ', $this->iLocationsFileId))) {
      return false;
        }

        if (false === $this->Set_Locations_Characteristics()) {                                    // Will fail if we don't find a UPC column
      return false;
        }

        if (false === $this->Create_List_Of_Distributors()) {
      return false;
        }

        if (false === $this->Load_Locations_And_IDs_Master()) {
      return false;
        }

        if (!$this->bCompletedHandlingLocations) {
          if (PLCI_bUPDATE_ADDRESSES) {                                                            // Normally, this should be true!
            if (false === ($this->sGoogleAPIKey            = $this->Get_sGoogleAPIKey())) {
      return false;
            }
          }

          if (false === $this->Read_To_Current_Row()) {
      return false;
          }

          $this->iLine                                     = 0;
          echo 'Adding Locations for Product Locations from ' . $this->iFirstLine . ' to ' . ($this->iFirstLine + PLCI_iMAX_LINE_COUNT) . '<br>';
          while (false !== ($this->oLocations->Read_Row_CSV())) {
            $this->iLine++;

            if (PLCI_iMAX_LINE_COUNT < $this->iLine) {
$this->Restart();
// N.B.: RESTART: Multiple Restarts are expected!
            }

            if (false === $this->Add_Locations_Lat_Lng()) {
      return false;
            }
          }

          if (PLCI_bFLAG_PRIVATE) {                                                                  // Any Product_Locations not updated below will remain invisible!
            if (false === $this->Flag_Product_Locations_Private_In_DB()) {
      return false;
            }
          }

          if (!PLCI_bTERMS_ONLY) {
            $this->bCompletedHandlingLocations             = true;
            $this->iFirstLine                              = -1 * PLCI_iMAX_LINE_COUNT2;
$this->Restart();
          } else {
            $this->Summarize_Results();
      return true;
          }
        }

        if (0 === $this->iFirstLine) {
          if (false === $this->Remove_Existing_Products()) {                                       // Start fresh and potentially save space if we don't replace then all
      return false;
          }
        }

        if (false === $this->Load_Products()) {
      return false;
        }

        if (PLCI_bLOAD_ALL_PRODUCTS) {                                                             // Load fake store with all the products and no location
          if (0 === $this->iFirstLine) {
            $this->Add_All_Products();
          }
        }

        $this->Add_Product_Locations();

        $this->Summarize_Results();
      return true;
      }
    }

    /**
     * Handle importer uploading and add attachment.
     *
     * @since 2.0.0
     *
     * @param      int            $iStep
     * @param      string         $sFileLocation
     * @param      string         $sFileName               = ''
     * @param      string         $sFileId                 = ''
     * @return     array                                                                           // Uploaded file's details on success, error message on failure
     */
    function WP_Import_Handle_Uploads(
                                  $iStep,
                                  $sFileLocation,
                                  $sFileName               = '',                                   // Net set yet
                                  $sFileId                 = ''                                    // Not set yet
    ) {
      if (PLCI_iLOAD_FILE_CHUNK === $iStep) {
        if (isset($_GET[$sFileName])) {
          if (isset($_GET[$sFileId])) {
  return [
    'sFile'                                              => $_GET[$sFileName],
    'iId'                                                => (int   )$_GET[$sFileId]
  ];
          } else {
  return [
    'error' => __('File Id not found.')
  ];
          }
        } else {
  return [
    'error' => __('File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.')
  ];
        }
      }

      if (!isset($_FILES[$sFileLocation])) {
  return [
    'error' => __('File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your php.ini or by post_max_size being defined as smaller than upload_max_filesize in php.ini.')
  ];
      }

     	$aOverrides                                          = [
     		'test_form'                                        => false,
     		'test_type'                                        => false,
     	];
     	$_FILES[$sFileLocation]['name']                     .= '.txt';

     	$aUpload                                             = wp_handle_upload($_FILES[$sFileLocation], $aOverrides);
     	if (isset($aUpload['error'])) {
     return $aUpload;
     	}

      $aObject                                             = [                                     // Construct the object array
     		'post_title'                                       => wp_basename($aUpload['file']),
     		'post_content'                                     => $aUpload['url'],
     		'post_mime_type'                                   => $aUpload['type'],
     		'guid'                                             => $aUpload['url'],
     		'context'                                          => 'import',
     		'post_status'                                      => 'private'
     	];

      $iId                                                 = wp_insert_attachment($aObject, $aUpload['file']);    // Save the data

     	/*
     	 * Schedule a cleanup for one day from now in case of failed
     	 * import or missing wp_import_cleanup() call.
     	 */
     	wp_schedule_single_event(time() + DAY_IN_SECONDS, 'importer_scheduled_cleanup', [$iId]);

     return [
       'sFile'                                             => $aUpload['file'],
       'iId'                                               => $iId
     ];
    }

    /**
     * Outputs the form used by the importers to accept the data to be imported
     *
     * @since 2.0.0
     *
     * @param string $action The action attribute for the form.
     */
    function WP_Import_Uploads_Form(
                   $action
    ) {
    	/**
    	 * Filters the maximum allowed upload size for import files.
    	 *
    	 * @since 2.3.0
    	 *
    	 * @see wp_max_upload_size()
    	 *
    	 * @param                                            int                 $max_upload_size    Allowed upload size. Default 1 MB.
    	 */
    	$iBytes                                              = apply_filters('import_upload_size_limit', wp_max_upload_size());
    	$iSize                                               = size_format($iBytes);
    	$aUploadDir                                          = wp_upload_dir();
    	if (!empty($aUploadDir['error'])) {
    ?>
<div class="error"><p><?php _e('Before you can upload your import file, you will need to fix the following error:'); ?></p>
<p><strong><?php echo $aUploadDir['error']; ?></strong></p></div>
    <?php } else { ?>
<form enctype="multipart/form-data" id="import-upload-form" method="post" class="wp-upload-form" action="<?php echo esc_url(wp_nonce_url($action, 'import-upload')); ?>">
  <p>
    <?php
      printf(
        '<label for="uploadProducts">%s</label> (%s)',
        __('Choose a products file from your computer:'),
        /* translators: %s: Maximum allowed file size. */
        sprintf( __('Maximum size: %s'), $iSize)
      );
    ?>
    <input type="file" id="uploadProducts" name="importProducts" size="25" />
  </p>

  <p>
    <?php
      printf(
        '<label for="uploadLocations">%s</label> (%s)',
        __('Choose a locations file from your computer:'),
        /* translators: %s: Maximum allowed file size. */
        sprintf( __('Maximum size: %s'), $iSize )
      );
    ?>
    <input type="file" id="uploadLocations" name="importLocations" size="25" />
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="max_file_size" value="<?php echo $iBytes; ?>" />
  </p>

  <strong style="color:red">The Import takes a LOONNNGGGG time. During this time, data will be MISSING!</strong>
  <?php submit_button(__('Upload files and import'), 'primary'); ?>
  <strong style="color:red">The Import takes a LOONNNGGGG time. During this time, data will be MISSING!</strong>
</form>
  <?php } ?>

<form id="delete-upload-form" method="post" class="wp-delete-form" action="<?php echo esc_url(wp_nonce_url($action, 'delete-upload')); ?>">
  <p>
    Be <em>very</em> thoughtful before you do this!<br><br>

    Reloading deleted locations is <strong>expensive</strong>.<br>
    You will be billed for use of Google's API for <strong>each location</strong> you have to load after this!<br>

    <label for="eraseProducts">ERASE ALL products and locations from the database:</label>
    <input type="text" id="eraseProducts" name="deleteProducts" size="25" />
    <input type="hidden" name="action" value="delete_inport">
  </p>

  <?php submit_button( __('Delete', 'ERASE ALL Products and Locations from DB!'), 'delete'); ?>
</form>
    <?php
    }

    function product_locations_csv_importer(
    ) {                                                               // Initialize
      $Product_Locations_CSV_Importer                                 = new Product_Locations_CSV_Importer();
      register_importer('products csv import', __('Product Locations CSV Import', ''), __('Import locations and products with custom fields and taxonomy from simple csv files.', 'product-locations-csv-importer'), [$Product_Locations_CSV_Importer, 'Dispatch']);
    }

    add_action('plugins_loaded', 'product_locations_csv_importer');
  }
