<?php
namespace Drupal\cs_pdf\Controller;

use Drupal\cs_cart\Controller;
use Drupal\Core\Entity\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use mikehaertl\wkhtmlto\Pdf                                as PdfWkHtmlTo;
// @see: https://stackoverflow.com/questions/32505951/pdftk-server-on-os-x-10-11 (if you're using a Mac)
use mikehaertl\pdftk\Pdf                                   as Pdftk;

/**
* Controller routines for products routes.
*/
class PDFController extends ControllerBase {

  /**
   * /add/product/pdf/parts_search/page_4?search_api_fulltext=green&f%5B0%5D=product_family%3A1545&f%5B1%5D=product_variation_type%3Aquick_connectors&
   *
   * @param                                                string              $sViewName
   * @param                                                string              $sViewDisplay
   *
   * @see: https://drupal.stackexchange.com/questions/253816/how-do-i-execute-a-view-in-a-custom-module-and-print-the-output-as-html-markup
   * @return                                               array|void
   */
  public function processPDF($sViewName, $sViewDisplay) {
    if ((!class_exists('mikehaertl\wkhtmlto\Pdf')) || (!class_exists('mikehaertl\pdftk\Pdf'))) {            // Flag on the play!
      // ToDo: Add some kind of notice!
  return;                                                                                          // Fast exit!
    };

    $aCutSheets                                            = [];

    // N.B.: The path to WkHTMLToPDF may need adjustment!
    $sPathToWkHTMLToPDF                                    = '/usr/local/bin/wkhtmltopdf';
    $sPublicPath                                           = \Drupal::service('file_system')->realpath(file_default_scheme() . "://");

    $aQuery                                                = \Drupal::request()->query->all();
    if (!is_null($aQuery['print_page'])) {
      unset($aQuery['print_page']);
      $sPrintPage                                          = 'y';
    }
    if (!is_null($aQuery['cart_page'])) {
      unset($aQuery['cart_page']);
      $sCartPage                                          = 'y';
    }
    if (!isset($sPrintPage)) {
      $sTopHTML                                            = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Parts Search - Results</title>

  <style>
    body {
      font-family: Montserrat, sans-serif;
      font-size: 14px;
      width: 800px;
    }
    .border {
      border: 1px solid black;
      min-height: 250px;
      padding: 10px;
    }
    table {
      margin-top: 20px;
    }
    td img {
      vertical-align: middle;
    }
    .image {
      border-right: 1px solid black;
      margin-right: 25px;
      padding-right: 10px;
      width: 255px;
    }
    .details {
      width: 575px;
    }
    .details ul {
      margin: 0;
    }
    .bold {
      font-weight: bold;
    }
  </style>
</head>
<body>';
    } else {
      $sTopHTML                                            = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Product Details</title>

  <style>
    body {
      font-family: Montserrat, sans-serif;
      font-size: 18px;
      width: 300px;
    }
    table {
      margin-top: 20px;
    }
    td img {
      height: 2in;
      margin-right: 0.18in;
      vertical-align: middle;
      width: 2in;
    }
    td img:last-child {
      margin-right: 0;
    }
    .image {
      border-bottom: 1px solid black;
      display: inline-block;
      min-width: 6.5in;
      padding-bottom: 10px;
    }
    .sku {
      font-size: 26px;
      width: 300px;
    }
    .label {
      font-weight: bold;
    }
    .image,
    .details {
      display: block;
      margin: 20px 1in 0 1in;
      min-width: 6.5in;
    }
  </style>
</head>
<body>';
    }
    $sBottomHTML                                           = '
</body>
</html>';

    if (!isset($sCartPage)) {
      $oView                                               = Views::getView($sViewName);
      $oView->setDisplay($sViewDisplay);

      if ('' !== $aQuery['search_api_fulltext']) {
        $aWhere[]                                          = 'search_api_fulltext:' . $aQuery['search_api_fulltext'];
        $oView->exposed_raw_input                          = array_merge($aWhere, (array)$oView->exposed_raw_input);
      }
      $oView->setItemsPerPage($aQuery['iNumberOfItems']);

      $oView->build();
      $oView->preExecute();

      $aFilters                                            = [];
      if (!isset($aQuery['?f'])) {
        foreach ($aQuery['?f'] as $sQuery) {
          $aFilters[]                                      = $sQuery;
        }
      }
      if (isset($aQuery['f'])) {
        foreach ($aQuery['f'] as $sQuery) {
          $aFilters[]                                      = $sQuery;
        }
      }

      $bAddingOR                                           = false;
      $bAddOrCondition                                     = false;
      $oQuery                                              = $oView->query->getSearchApiQuery();
      $oOrCondition                                        = $oQuery->createConditionGroup('OR');  // Just in case
      $sKeepLastField                                      = '';
      $sLastField                                          = '';
      $sLastValue                                          = '';
      foreach ($aFilters as $iKey => $sFilter) {
        $sField                                            = strtok($sFilter, ':');
        if ('product_variation_type' !== $sField) {
          $sField                                          = 'attribute_' . $sField;
        } else {
          $sField                                          = 'type';
        }
        $sValue                                            = strtok(':');

        if ($sLastField === $sField) {
          if (!$bAddingOR) {                                                                       // Start adding to an OR conditionGroup
            $bAddingOR                                     = true;
          }
        } else {
          if ($bAddingOR) {                                                                        // Stop adding to an OR conditionGroup
            $bAddOrCondition                               = true;
          }
        }

        if ('' !== $sLastField) {
          if ($bAddingOR) {
            $oOrCondition->addCondition($sLastField, $sLastValue, '=');
          } else {
            $oQuery->addCondition(      $sLastField, $sLastValue, '=');
          }
          if ($bAddOrCondition) {
            $bAddingOR                                     = false;
            $bAddOrCondition                               = false;
            $oQuery->addConditionGroup($oOrCondition);
            $oOrCondition                                  = $oQuery->createConditionGroup('OR');  // Just in case
          }
        }

        $sKeepLastField                                    = $sLastField;

        $sLastField                                        = $sField;
        $sLastValue                                        = $sValue;
      }

      if ($sKeepLastField === $sField) {
        if (!$bAddingOR) {                                                                         // Start adding to an OR conditionGroup
          $bAddingOR                                       = true;
        }
      } else {
        if ($bAddingOR) {                                                                          // Stop adding to an OR conditionGroup
          $bAddingOR                                       = false;
          $oQuery->addConditionGroup($oOrCondition);
        }
      }

      if ('' !== $sKeepLastField) {
        if ($bAddingOR) {
          $oOrCondition->addCondition($sField, $sValue, '=');
          $oQuery->addConditionGroup($oOrCondition);
        } else {
          $oQuery->addCondition(      $sField, $sValue, '=');
        }
      }

      $oView->execute();

      $aParts                                                = [];
      foreach ($oView->result as $iRow => $oRow) {
        $oObjectEntity                                       = $oRow->_object->getEntity();
        try {
          $oField                                            = $oObjectEntity->get('field_part');
        } catch (\Exception $oException) {
          $oField                                            = null;
        }
        if (is_null($oField)) {
          $aParts[$iRow]['sku']->sLabel                      = 'Part No.';
          $aParts[$iRow]['sku']->sValue                      = trim($oObjectEntity->getSku());
        } else {
          $aParts[$iRow]['field_part']->sLabel               = 'Part No.';
          $aParts[$iRow]['field_part']->sValue               = trim($oField->getValue()[0]['value']);
        }

        $aParts[$iRow]['product']->sLabel                    = 'Product Category';
        $aParts[$iRow]['product']->sValue                    = trim($oObjectEntity->getProduct()->label());
        foreach ($oObjectEntity->getAttributeValues() as $oAttribute) {
          $aParts[$iRow][$oAttribute->getAttribute()->id()]->sLabel    = trim($oAttribute->getAttribute()->label());
          $aParts[$iRow][$oAttribute->getAttribute()->id()]->sValue    = trim($oAttribute->getName());
        }

        try {
          $oField                                            = $oObjectEntity->get('field_same_profile_as');
        } catch (\Exception $oException) {
          $sField                                            = null;
        }
        if (!is_null($oField)) {
          $aParts[$iRow]['field_same_profile_as']->sLabel    = 'Same Profile As';
          $aParts[$iRow]['field_same_profile_as']->sValue    = trim($oField->getValue()[0]['value']);
        }

        $aCutSheets[$aParts[$iRow]['product']->sValue]       = $aParts[$iRow]['product']->sValue;    // Set aCutSheets
      }
    } else {
      $oCart                                               = \Drupal\cs_cart\Controller\EmailPdfController::getCartItems();
      if (!is_null($oCart)) {
        $aParts                                            = [];

        foreach ($oCart->getItems() as $iRow => $oRow) {
          $oObjectEntity                                   = $oRow->getPurchasedEntity();
          try {
            $oField                                        = $oObjectEntity->get('field_part');
          } catch (\Exception $oException) {
            $oField                                        = null;
          }
          if (is_null($oField)) {
            $aParts[$iRow]['sku']->sLabel                  = 'Part No.';
            $aParts[$iRow]['sku']->sValue                  = trim($oObjectEntity->getSku());
          } else {
            $aParts[$iRow]['field_part']->sLabel           = 'Part No.';
            $aParts[$iRow]['field_part']->sValue           = trim($oField->getValue()[0]['value']);
          }

          $aParts[$iRow]['product']->sLabel                = 'Product Category';
          $aParts[$iRow]['product']->sValue                = trim($oObjectEntity->getProduct()->label());
          foreach ($oObjectEntity->getAttributeValues() as $oAttribute) {
            $aParts[$iRow][$oAttribute->getAttribute()->id()]->sLabel     = trim($oAttribute->getAttribute()->label());
            $aParts[$iRow][$oAttribute->getAttribute()->id()]->sValue     = trim($oAttribute->getName());
          }

          try {
            $oField                                        = $oObjectEntity->get('field_same_profile_as');
          } catch (\Exception $oException) {
            $sField                                        = null;
          }
          if (!is_null($oField)) {
            $aParts[$iRow]['field_same_profile_as']->sLabel     = 'Same Profile As';
            $aParts[$iRow]['field_same_profile_as']->sValue     = trim($oField->getValue()[0]['value']);
          }

          $aCutSheets[$aParts[$iRow]['product']->sValue]   = $aParts[$iRow]['product']->sValue;    // Set aCutSheets
        }
      }
    }

    $oPDFWkHTMLTo                                          = new PdfWkHtmlTo([
      'binary'                                             => $sPathToWkHTMLToPDF,
      'header-html'                                        => file_create_url('public://') . 'import/header.html',
      'orientation'                                        => 'portrait',
      'page-size'                                          => 'letter',
    ]);

/*
    foreach ($aCutSheets as $iCutSheet => $sCutSheet) {                                            // First, the aCutSheets...
      $sCutSheet                                           = strtolower(preg_replace("/[^a-zA-Z]/", '', $sCutSheet));
      $oPDFWkHTMLTo->addPage(file_create_url('public://') . 'import/' . $sCutSheet . '.html');
    }
*/

    $sImagePath                                            = $sPublicPath . '/import/images/';
    $sImageURL                                             = file_create_url('public://') . 'import/images/';
    $sPartsHTML                                            = $sTopHTML;

    // Now, the parts...
    $iPartPerPageCount                                     = 0;
    foreach ($aParts as $iPart => $aPart) {
      $iPartPerPageCount                                  += 1;
      if (5 === $iPartPerPageCount) {
        $iPartPerPageCount                                 = 1;
        $sPartsHTML                                       .= $sBottomHTML;
        $oPDFWkHTMLTo->addPage($sPartsHTML);                                                       // Add the sPartsHTML

        $sPartsHTML                                        = $sTopHTML;
      }

      if (!isset($sPrintPage)) {
        $sPartsHTML                                       .= '
  <table class="border">
    <tr>
      <td class="image">';
        // The image...
        if (file_exists($sImagePath . $aPart['sku']->sValue . '-1.png')) {
          $sPartsHTML                                     .= '
        <img alt="part: ' . $aPart['sku']->sValue . '" src="' . $sImageURL . $aPart['sku']->sValue . '-1.png" height="250" width="250" />';
        }
        $sPartsHTML                                       .= '
      </td>
      <td class="details">
        <ul>';

        foreach ($aPart as $sMachineName => $oData) {
          if (('Product Family' === $oData->sLabel) || ('Product Category' === $oData->sLabel) || ('Part No.' === $oData->sLabel)) {
            $sBold                                         = ' class="bold"';
          } else {
            $sBold                                         = '';
          }

          $sPartsHTML                                     .= '
          <li' . $sBold . '>
            ' . $oData->sLabel . ' : ' . $oData->sValue . '
          </li>';
        }

        $sPartsHTML                                       .= '
        </ul>
      </td>
    </tr>
  </table>';
      } else {
        $sPartsHTML                                       .= '
  <table>
    <tr>
      <td class="image">';
        // The image...
        if (file_exists($sImagePath . $aPart['sku']->sValue . '-1.png')) {
          $sPartsHTML                                     .= '
        <img alt="part: ' . $aPart['sku']->sValue . '" src="' . $sImageURL . $aPart['sku']->sValue . '-1.png" />';
        }
        if (file_exists($sImagePath . $aPart['sku']->sValue . '-2.png')) {
          $sPartsHTML                                     .= '
        <img alt="part: ' . $aPart['sku']->sValue . '" src="' . $sImageURL . $aPart['sku']->sValue . '-2.png" />';
        }
        if (file_exists($sImagePath . $aPart['sku']->sValue . '-3.png')) {
          $sPartsHTML                                     .= '
        <img alt="part: ' . $aPart['sku']->sValue . '" src="' . $sImageURL . $aPart['sku']->sValue . '-3.png" />';
        }
        $sPartsHTML                                       .= '
      </td>
    </tr>
    <tr>
      <td class="details">
        <div class="sku">';
        $sPartsHTML                                       .= '
          ' . $aPart['sku']->sValue;
        $sPartsHTML                                       .= '
        </div>';

        foreach ($aPart as $sMachineName => $oData) {
          if (!('Part No.' === $oData->sLabel)) {
            $sPartsHTML                                   .= '
        <div>
          <span class="label">' . $oData->sLabel . ':</span> ' . $oData->sValue . '
        </div>';
          }
        }

        $sPartsHTML                                       .= '
      </td>
    </tr>
  </table>';
      }
    }
    $sPartsHTML                                           .= $sBottomHTML;

    $oPDFWkHTMLTo->addPage($sPartsHTML);                                                           // Add the sPartsHTML

/*
    if (!$oPDFWkHTMLTo->saveAs('/path/to/PartsSearch.pdf')) {                                      // Save the PDF
      $oError                                              = $oPDFWkHTMLTo->getError();
      // ... handle error here
    }
*/
    if (!isset($sPrintPage)) {
      $sPDFFile                                            = 'PartsSearch.pdf';
    } else {
      $sPDFFile                                            = 'ProductDetails.pdf';
    }

/*
    if (!$oPDFWkHTMLTo->send($sPDFFile)) {
      $oError                                              = $oPDFWkHTMLTo->getError();
        // ... handle error here
    }
*/

    $sPDFFileName                                          = tempnam(\Drupal::service('file_system')->realpath("temporary://"), 'temp-pdf');
//    $sPDFFileName                                          = \Drupal::service('file_system')->realpath("public://") . '/pdfs/exports/test-start.pdf';
    if (!$oPDFWkHTMLTo->saveAs($sPDFFileName)) {
      $oError                                              = $oPDFWkHTMLTo->getError();
        // ... handle error here
    }

    $sLetters                                              = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $aPDFFiles                                             = [];
    $oPDFtk                                                = new Pdftk();
    $iCutSheet                                             = 0;
    foreach ($aCutSheets as $sCutSheet) {                                                          // First, the aCutSheets...
      $sHandle                                             = substr($sLetters, $iCutSheet, 1);
      $iCutSheet++;
      $sCutSheet                                           = strtolower(preg_replace("/[^a-zA-Z]/", '', $sCutSheet));
      $aPDFFiles[]                                         = $sCutSheet;
      $oPDFtk->addFile(\Drupal::service('file_system')->realpath("public://") . '/pdfs/cutsheets/' . $sCutSheet . '.pdf', $sHandle);
    }
    $sHandle                                               = substr($sLetters, $iCutSheet, 1);
    $oPDFtk->addFile($sPDFFileName, $sHandle);

    foreach ($aPDFFiles as $iCutSheet => $sThisPDFFile) {
      $sHandle                                             = substr($sLetters, $iCutSheet, 1);
      $oPDFtk->cat(1, 'end', $sHandle);
    }
    $sHandle                                               = substr($sLetters, count($aCutSheets), 1);
    $oPDFtk->cat(1, 'end', $sHandle);
    
    if (!$oPDFtk->send($sPDFFile)) {
//    if (!$oPDFtk->send(\Drupal::service('file_system')->realpath("public://") . '/pdfs/exports/test.pdf')) {                                                                  // ... or send to client as file download
      $oError                                              = $oPDFtk->getError();
      // ... handle error here
    }
/* */
  }

}
