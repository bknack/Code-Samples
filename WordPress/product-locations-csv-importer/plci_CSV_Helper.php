<?php

  /**
   * Utility class to allow us to work with CSV one line at a time while being able to address data on a column basis.
   *
   * Class plci_CSV_Helper
   */
  class plci_CSV_Helper {
    private $aHeaders                                      = [];
    private $aHeaderKeys                                   = [];

    private $bForceRead                                    = false;
    private $bFunctional                                   = false;

    private $rFileHandle;

    private $aRow                                           = [];

    public $iFileId;

    public $mCell;

    public $sFileName;
    public $sFileDescription;

    /**
     * plci_CSV_Helper constructor.
     *
     * Can we open, read and parse the file...
     *
     * @param      string         $sFileName
     * @param      string         $sFileDescription
     * @param      integer        $iFileId                 = -1
     */
    function __construct(
      $sFileName,
      $sFileDescription,
      $iFileId                                             = -1
    ) {
      $this->iFileId                                       = $iFileId;
      $this->sFileDescription                              = $sFileDescription;
      $this->sFileName                                     = $sFileName;

    return $this->Load_CSV();
    }

    /**
     * @param      array          $aHeaders
     * @return     bool
     */
    public function Can_Parse_Columns(
                   array          $aHeaders
    ) {
      if (!is_array($aHeaders) || count($aHeaders) == 0) {
        $this->Cleanup_File('parse headers of ');
    return false;
      }

      $sBom                                                = pack("CCC", 0xef, 0xbb, 0xbf);
      if (0 == strncmp($aHeaders[0], $sBom, 3)) {
        $aHeaders[0]                                       = substr($aHeaders[0], 3);
      }

      $aKeys                                               = array_keys($aHeaders);
      $aValues                                             = array_values($aHeaders);

      foreach ($aValues as &$sValue) {
        $sValue                                            = trim($sValue);
      }

      $this->aHeaderKeys                                   = array_combine($aValues, $aKeys);
      $this->aHeaders                                      = array_combine($aKeys, $aValues);

    return true;
    }

    private function Cleanup_File(
                   $sText
    ) {
      if (-1 !== $this->iFileId) {
        wp_import_cleanup($this->iFileId);
      }
      echo '<p><strong>' . __('Failed to ' . $sText . ' ' . $this->sFileDescription . ' file.', 'product-locations-csv-importer') . '</strong></p>';
    }

    private function Load_CSV(
    ) {
      $this->bFunctional                                   = false;

      if (false === $this->Open_CSV()) {
    return $this->bFunctional;
      }

      $this->bForceRead                                    = true;
      if (false === ($aHeaders                             = $this->Read_Row_CSV())) {
    return $this->bFunctional;
      }

      if (false === $this->Can_Parse_Columns($aHeaders)) {
    return $this->bFunctional;
      }

      $this->bFunctional                                   = true;
    return $this;
    }

    private function Open_CSV(
    ) {
      if (false === ($this->rFileHandle                    = fopen($this->sFileName, 'r'))) {
        $this->Cleanup_File('open');
    return false;
      }

    return $this->rFileHandle;
    }

    /**
     * @param      string         $sMessage                = ''
     *
     * @return     bool
     */
    public function Close_CSV(
                   $sMessage                               = ''
    ) {
      fclose($this->rFileHandle);

      if ('' !== $sMessage) {
        wp_import_cleanup($this->iFileId);

        echo '<p><strong>' . __($sMessage . $this->sFileDescription . ' file.', 'product-locations-csv-importer') . '</strong></p>';
    return false;
      }

    return true;
    }

    /**
     * @param      string         $sHeader
     *
     * @return     bool|int
     */
    public function Get_Column(
                   $sHeader
    ) {
      if (!$this->bFunctional) {
    return $this->bFunctional;
      }

      if (isset($this->aHeaderKeys[$sHeader])) {
    return $this->aHeaderKeys[$sHeader];
      }

    return false;
    }

    /**
     * N.B.: Return value has been Trimmed!
     *
     * @param      string         $sHeader
     *
     * @return     bool|mixed
     */
    public function Get_Cell(
                    $sHeader
    ) {
      if (!$this->bFunctional) {
    return $this->bFunctional;
      }

      if (isset($this->aHeaderKeys[$sHeader])) {
        $iKey                                              = $this->aHeaderKeys[$sHeader];
        if (!empty($this->aRow[$iKey])) {
          $this->mCell                                     = $this->aRow[$iKey];
    return $this->mCell;
        }
      }

    return false;
    }

    /**
     * @return     array|bool|false|null
     */
    public function Read_Row_CSV(
    ) {
      if (!$this->bForceRead && !$this->bFunctional) {
        $this->bForceRead                                  = false;
    return $this->bFunctional;
      }
      $this->bForceRead                                    = false;

      $this->aRow                                          = fgetcsv($this->rFileHandle);
      if (is_null($this->aRow)) {
        $this->Cleanup_File('read');
    return NULL;
      }
      if (false === $this->aRow) {
    return false;
      }

    return $this->aRow;
    }

    /**
     * @return     bool
     */
    public function Reset_CSV(
    ) {
      if (false === rewind($this->rFileHandle)) {
    return false;
      } else {
        $this->aRow                                        = $this->Read_Row_CSV();                // Drop the header row
      }

    return true;
    }
  }