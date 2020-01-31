<?php

  /**
   * Utility class that helps us interact with various aspects of WordPress.
   *
   * Class plci_Post_Helper
   */
  class plci_Post_Helper {
    const CFS_PREFIX = 'cfs_';
    const SCF_PREFIX = 'scf_';

    private $oPost;
    private $oError;

    /**
     * @param      string         $sKey
     * @param      mixed          $mValue
     */
    private function ACF_Update_Field(
                   $sKey,
                   $mValue
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        if (function_exists('update_field')) {
          update_field($sKey, $mValue, $oPost->ID);
        } else {
          $this->Update_Meta($sKey, $mValue);
        }
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param                     $isCode
     * @param                     $sMessage
     * @param      string         $sData
     */
    private function Add_Error(
                   $isCode,
                   $sMessage,
                   $sData                                  = ''
    ) {
      if (!$this->Is_Error()) {
        $oError                                            = new WP_Error();
        $this->oError                                      = $oError;
      }
      $this->oError->add($isCode, $sMessage, $sData);
    }

    /**
     * @param        int            $iObjectId
     * @param        int            $iTermTaxonomyId
     * @param        string         $sTaxonomySlug
     */
    private function Add_Term_Relationship (
                   $iObjectId,                                                                     // object_id
                   $iTermTaxonomyId,                                                               // term_taxonomy_id
                   $sTaxonomySlug                                                                  // taxonomy_slug
    ) {
      global $wpdb;

      $iObjectId                                             = (int   )$iObjectId;
      $iTermTaxonomyId                                       = (int   )$iTermTaxonomyId;

      if (!$wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d", $iObjectId, $iTermTaxonomyId))) {
        /**
         * @see: /wp-includes/taxonomy.php wp_set_object_terms foreach...
         *
         * Fires immediately before an object-term relationship is added.
         *
         * @since 2.9.0
         * @since 4.7.0 Added the `$taxonomy` parameter.
         *
         * @param                                            int                 $iObjectId          object_id
         * @param                                            int                 $iTermTaxonomyId    term_taxonomy_id
         * @param                                            string              $sTaxonomy          Taxonomy slug.
         */
        do_action('add_term_relationship', $iObjectId, $iTermTaxonomyId, $sTaxonomySlug);
        $wpdb->insert(
          $wpdb->term_relationships,
          [
            'object_id'                                      => $iObjectId,
            'term_taxonomy_id'                               => $iTermTaxonomyId,
          ]
        );

        /**
         * @see: /wp-includes/taxonomy.php wp_set_object_terms foreach...
         *
         * Fires immediately after an object-term relationship is added.
         * @since 2.9.0
         * @since 4.7.0 Added the `$taxonomy` parameter.
         *
         * @param                                            int                 $iObjectId          object_id
         * @param                                            int                 $iTermTaxonomyId    term_taxonomy_id
         * @param                                            string              $sTaxonomy          Taxonomy slug.
         */
        do_action('added_term_relationship', $iObjectId, $iTermTaxonomyId, $sTaxonomySlug);
      }
    }

    /**
     * @param      string         $sKey
     * @param      mixed          $mValue
     */
    private function CFS_Save(
                   $sKey,
                   $mValue
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        if (function_exists('CFS')) {
          $aFieldData                                      = [$sKey => $mValue];
          $aPostData                                       = ['ID'  => $oPost->ID];
          CFS()->save($aFieldData, $aPostData);
        } else {
          $this->Update_Meta($sKey, $mValue);
        }
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param      string         $sURL
     * @param      array          $aArgs
     *
     * @return     string
     */
    private function Remote_Get(
                     $sURL,
      array          $aArgs                                = []
    ) {
      global $wp_filesystem;

      if (!is_object($wp_filesystem)) {
        WP_Filesystem();
      }

      if ($sURL && is_object($wp_filesystem)) {
        $aoResponse                                        = wp_safe_remote_get($sURL, $aArgs);
        if (!is_wp_error($aoResponse) && (200 === $aoResponse['response']['code'])) {
          $aDestination                                    = wp_upload_dir();
          $sFileName                                       = basename($sURL);
          $sFilePath                                       = $aDestination['path'] . '/' . wp_unique_filename($aDestination['path'], $sFileName);

          $sBody                                           = wp_remote_retrieve_body($aoResponse);

          if ($sBody && $wp_filesystem->put_contents($sFilePath, $sBody, FS_CHMOD_FILE)) {
    return $sFilePath;
          } else {
            $this->Add_Error('remote_get_failed', __('Could not get remote file.', 'product-locations-csv-importer'));
          }
        } elseif (is_wp_error($aoResponse)) {
          $this->Add_Error($aoResponse->get_error_code(), $aoResponse->get_error_message());
        }
      }

    return '';
    }

    /**
     * @param      mixed          $mData
     */
    private function SCF_Save(
                   $mData
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        if (class_exists('Smart_Custom_Fields_Meta') && is_array($mData)) {
          $_aData                                          = [];
          $_aData['smart-custom-fields']                   = $mData;
          $oMeta                                           = new Smart_Custom_Fields_Meta($oPost);
          $oMeta->save($_aData);
        } elseif(is_array($mData)) {
          foreach ($mData as $sKey => $aArray) {
            foreach ((array) $aArray as $mValue) {
              $this->Update_Meta($sKey, $mValue);
            }
          }
        }
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param                     $sFilePath
     * @param      array          $aData
     *
     * @return     int|\WP_Error
     */
    public function Set_Attachment(
                     $sFilePath,
                     $aData                                = []
    ) {
      $oPost                                               = $this->Get_Post();
      if ($sFilePath && file_exists($sFilePath) ) {
        $sFileName                                         = basename($sFilePath);
        $aWPFileType                                       = wp_check_filetype_and_ext($sFilePath, $sFileName);
        $sType                                             = empty($aWPFileType['type'])            ? ''               : $aWPFileType['type'];
        $sProperFileName                                   = empty($aWPFileType['proper_filename']) ? ''               : $aWPFileType['proper_filename'];
        $sFileName                                         = ($sProperFileName)                     ? $sProperFileName : $sFileName;
        $sFileName                                         = sanitize_file_name($sFileName);

        $aUploadDirectory                                  = wp_upload_dir();
        $guid                                              = $aUploadDirectory['baseurl'] . '/' . _wp_relative_upload_path($sFilePath);

        $aAttachment                                       = array_merge([
          'post_mime_type'                                 => $sType,
          'guid'                                           => $guid,
          'post_title'                                     => $sFileName,
          'post_content'                                   => '',
          'post_status'                                    => 'inherit'
        ], $aData);
        $iAttachmentId                                     = wp_insert_attachment($aAttachment, $sFilePath, ($oPost instanceof WP_Post) ? $oPost->ID : null);
        $biAttachmentMetaData                              = wp_generate_attachment_metadata($iAttachmentId, $sFilePath);
        wp_update_attachment_metadata($iAttachmentId, $biAttachmentMetaData);
    return $iAttachmentId;
      }
    // On failure
    return 0;
    }

    /**
     * @param      int            $iPostId
     */
    private function Set_Post(
                     $iPostId
    ) {
      $oPost                                                 = get_post($iPostId);
      if (is_object($oPost)) {
        $this->oPost                                         = $oPost;
      } else {
        $this->Add_Error('post_id_not_found', __('Provided Post ID not found.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param      string         $sValue
     */
    private function Update_Attachment(
                     $sValue
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        update_attached_file($oPost->ID, $sValue);
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param      string         $sKey
     * @param      mixed          $mValue
     */
    private function Update_Meta(
                   $sKey,
                   $mValue
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        update_post_meta($oPost->ID, $sKey, $mValue);
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @return     \WP_Error
     */
    public function Get_Error(
    ) {
      if (!$this->Is_Error()) {
    return new WP_Error();
      }
    return $this->oError;
    }

    /**
     * @return     mixed
     */
    public function Get_Post(
    ) {
    return $this->oPost;
    }

    /**
     * @return     bool
     */
    public function Is_Error(
    ) {
    return is_wp_error($this->oError);
    }

    /**
     * @param      array          $aData
     */
    public function Set_Meta(
      array        $aData
    ) {
      $aSCF                                                  = [];
      foreach ($aData as $sKey => $sValue) {
        $iIsCFS                                              = 0;
        $iIsSCF                                              = 0;
        $iIsACF                                              = 0;
        if (strpos($sKey, self::CFS_PREFIX) === 0) {
          $this->CFS_Save(substr($sKey, strlen(self::CFS_PREFIX)), $sValue);
          $iIsCFS                                            = 1;
        } elseif(strpos($sKey, self::SCF_PREFIX) === 0) {
          $scf_key = substr($sKey, strlen(self::SCF_PREFIX));
          $aSCF[$scf_key][] = $sValue;
          $iIsSCF                                            = 1;
        } else {
          if (function_exists('get_field_object')) {
            if (strpos($sKey, 'field_') === 0) {
              $fobj                                          = get_field_object($sKey);
              if (is_array($fobj) && isset($fobj['key']) && $fobj['key'] == $sKey) {
                $this->ACF_Update_Field($sKey, $sValue);
                $iIsACF                                      = 1;
              }
            }
          }
        }
        if (!$iIsACF && !$iIsCFS && !$iIsSCF) {
          $this->Update_Meta($sKey, $sValue);
        }
      }
      $this->SCF_Save($aSCF);
    }

    /**
     * @param      array          $aData
     */
    public function Update(
      array        $aData
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        $aData['ID']                                       = $oPost->ID;
      }
      if ($aData['post_type'] == 'attachment' && !empty($aData['media_file'])) {
        $this->Update_Attachment($aData['media_file']);
        unset($aData['media_file']);
      }
      $iPostId                                             = wp_update_post($aData, true);
      if (is_wp_error($iPostId)) {
        $this->Add_Error($iPostId->get_error_code(), $iPostId->get_error_message());
      } else {
        $this->Set_Post($iPostId);
      }
    }

    /**
     * @param      array          $aData
     *
     * @return     \plci_Post_Helper
     */
    public static function Add(
      array        $aData
    ) {
      $oPost                                                 = new plci_Post_Helper();

      if ($aData['post_type'] == 'attachment') {
        $iPostId                                             = $oPost->Add_Media_File($aData['media_file'], $aData);
      } else {
        $iPostId                                             = wp_insert_post($aData, true);
      }

      if (is_wp_error($iPostId)) {
        $oPost->Add_Error($iPostId->get_error_code(), $iPostId->get_error_message());
      } else {
        $oPost->Set_Post($iPostId);
      }
    return $oPost;
    }

    /**
     * @param      int            $iPostId
     *
     * @return \plci_Post_Helper
     */
    public static function Get_By_ID(
                   $iPostId
    ) {
      $object                                                = new plci_Post_Helper();
      $object->Set_Post($iPostId);
    return $object;
    }

    /**
     * @param      array          $aTags
     */
    public function Set_Post_Tags(
      array        $aTags
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        wp_set_post_tags($oPost->ID, $aTags);
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param        string         $sTaxonomySlug
     * @param        array          $aNames
     * @param        bool           $bNewLocation
     */
    public function Set_Object_Terms(
                   $sTaxonomySlug,
      array        $aNames
    ) {
      global $wpdb;

      $oPost                                                 = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        $aDescription[0]                                     = 'Brand';
        $aSlug[0]                                            = 'wpsl-' . strtolower($aNames[0]);

        $aDescription[1]                                     = 'Product Description';
        $aSlug[1]                                            = 'wpsl-' . strtolower($aNames[0] . '-' . $aNames[1]);

        $aDescription[2]                                     = 'NET WT OZ';
        $aSlug[2]                                            = 'wpsl-' . strtolower($aNames[0] . '-' . $aNames[1] . '-' . $aNames[2]);

        foreach ($aNames as $iTerm => $sName) {
          $aTerm                                             = $wpdb->get_results($wpdb->prepare("SELECT term_id FROM $wpdb->terms WHERE slug = %s", $aSlug[$iTerm]));
          if (0 === count($aTerm)) {
            $wpdb->insert(
              $wpdb->terms,
              [
                'name'                                       => $sName,
                'slug'                                       => $aSlug[$iTerm],
                'term_group'                                 => 0
              ]
            );
            $aTermId[$iTerm]                                 = $wpdb->insert_id;

            // wp_term_taxonomy...
            if (0 === $iTerm) {
              $iParent                                       = 0;
            } else {
              $iParent                                       = $aTermId[$iTerm - 1];
            }
            $wpdb->insert(
              $wpdb->term_taxonomy,
              [
                'term_taxonomy_id'                           => $aTermId[$iTerm],
                'term_id'                                    => $aTermId[$iTerm],
                'taxonomy'                                   => $sTaxonomySlug,
                'description'                                => $aDescription[$iTerm],
                'parent'                                     => $iParent,
                'count'                                      => 1
              ]
            );

            // wp_term_relationships
            $wpdb->insert(
              $wpdb->term_relationships,
              [
                'object_id'                                  => $oPost->ID,
                'term_taxonomy_id'                           => $aTermId[$iTerm],
                'term_order'                                 => 0
              ]
            );
          } else {
            // wp_term_relationships
            $aTermId[$iTerm]                                 = (int   )$aTerm[0]->term_id;           // We need this handle parents properly
            $aTerm                                           = $wpdb->get_results($wpdb->prepare("SELECT term_taxonomy_id FROM $wpdb->term_relationships WHERE object_id = %d and term_taxonomy_id = %d", $oPost->ID, $aTermId[$iTerm]));
            if (0 === count($aTerm)) {
              $wpdb->insert(
                $wpdb->term_relationships,
                [
                  'object_id'                                  => $oPost->ID,
                  'term_taxonomy_id'                           => $aTermId[$iTerm],
                  'term_order'                                 => 0
                ]
              );
            }
          }
        }
      } else {
        $this->Add_Error('post_is_not_set', __('WP_Post object is not set.', 'product-locations-csv-importer'));
      }
    }

    /**
     * @param      string         $sFilePath
     * @param      null           $mData
     *
     * @return bool|int|\WP_Error
     */
    public function Add_Media_File(
                   $sFilePath,
                   $mData                                  = null
    ) {
      if (parse_url($sFilePath, PHP_URL_SCHEME)) {
        $sFilePath                                         = $this->Remote_Get($sFilePath);
      }
      $biAttachmentId                                      = $this->Set_Attachment($sFilePath, $mData);
      if ($biAttachmentId) {
    return $biAttachmentId;
      }

    return false;
    }

    /**
     * @param      string         $sFilePath
     *
     * @return     bool
     */
    public function Add_Thumbnail(
                     $sFilePath
    ) {
      $oPost                                               = $this->Get_Post();
      if ($oPost instanceof WP_Post) {
        if (parse_url($sFilePath, PHP_URL_SCHEME)) {
          $sFilePath                                       = $this->Remote_Get($sFilePath);
        }
        $bsThumbnailId = $this->Set_Attachment($sFilePath);
        if ($bsThumbnailId) {
          $biMetaId                                        = set_post_thumbnail($oPost, $bsThumbnailId);
          if ($biMetaId) {
    return true;
          }
        }
      }

    return false;
    }

    public function __destruct(
    ) {
      unset($this->post);
    }
  }