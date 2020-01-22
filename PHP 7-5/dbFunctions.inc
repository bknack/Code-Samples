<?php
  /**
   * Code Sample Notes:
   *
   * This is a set of functions that made it much easier to work with PDO!
   *
   * Notice that there are PHP 7 types etc. commented out. This file was designed to work with PHP 7. When the
   * decision was taken to go with Google App Engine, we dropped to PHP 5 so we had a fast fully supported version of
   * PHP. Hence, the commenting out.
   *
   * I hoped we could go back to PHP 7 one day ;-)
   */

  /**
   * Bruce A. Knack
   * Silicon Surfers
   *
   * U-Support@SiliconSurfers.com
   * SiliconSurfers.com
   *
   * Project: U V0
   * Created: 2016-03-07.1051
   *
   * Created with JetBrains PhpStorm.
   */
/*declare(strict_types                                     = 1);*/

  define('U_sInstanceConnectionName'                       , 'u' . P_sTLD . ':us-central1:u' . P_sTLD);
  define('P_sPassword'                                     , getenv('P_sPassword'));
  define('P_sUser'                                         , getenv('P_sUser'));

  define('DBF_sNoErrorMessage'                             , 'DBF_sNoErrorMessage');               // This string would NEVER happen, so it makes a good "no error message" string

  $aPreparedQueries                                        = [];

  // ToDo Long Term: Find a way to only prepare statements once ACROSS multiple connections (use stored procedures?)

  // N.B.: json_Die on failure (except insert failure)!
  /**
   * Execute a Query
   *
   * N.B.: This returns lastInsertId(), rather than the "more traditional" rowCount!!!
   *
   * @param                                                PDO                 $dbhQuery
   * @param                                                PDOStatement        $dbQuery
   * @param                                                array               $aData
   * @param                                                bool=               $bSessionInsert               (optional)
   * @param                                                bool=               $bRollBackRequired            (optional)
   * @return                                               bool|int
   */
  function Execute_Query(
/*  PDO          */$dbhQuery,
/*  PDOStatement */$dbQuery,
/*  array        */$aData,
/*               */$bSessionInsert                         = false,
/*               */$bRollBackRequired                      = false
  ) {
    $biReturnValue                                         = false;
    try {                                                                                          // Load the execute object with the data
      if ($dbQuery->execute($aData)) {
        $biReturnValue                                     = $dbhQuery->lastInsertId();            // return $dbQuery->rowCount();
      }
    } catch (PDOException $peException) {
      if ($bRollBackRequired) {
        $dbhQuery->rollBack();
      }
      json_Die($peException, $bSessionInsert);                                                     // N.B.: Actually EXITs if $bSessionInsert is false.
    }

  return $biReturnValue;
  }

  /**
   * Construct json type error message
   *
   * @param                                                PDOException        $peException
   * @param                                                bool=               $bSessionInsert               (optional)
   */
  function json_Die(
/*  PDOException */$peException,
/*               */$bSessionInsert                         = false
  )/*: void */{
    if ($bSessionInsert) {
      if ('23000' === $peException->getCode()) {                                                   // Insert failures (if we're inserting) are OK!
  return;
      }
    }

    error_log($peException->getMessage());

    if (ini_get('display_errors') == "0") {
exit();
    } else {
exit(json_encode(["status" => "ERROR", "message" => $peException->getMessage()]));
    }
  }

  /**
   * Perform General Query
   *
   * N.B.: This returns lastInsertId(), rather than the "more traditional" rowCount!!!
   *
   * @param                                                PDO                 $dbhQuery
   * @param                                                string              $sQuery
   * @param                                                array               $aData
   * @param                                                bool=               $bSessionInsert               (optional)
   * @return                                               bool|int
   */
  function Perform_Query(
/*  PDO          */$dbhQuery,
/*               */$sQuery,
/*  array        */$aData,
/*               */$bSessionInsert                         = false
  ) {
    $dbQuery                                               = Prepare_Query($dbhQuery, $sQuery);

  return Execute_Query($dbhQuery, $dbQuery, $aData, $bSessionInsert);
  }

  // N.B.: json_Die on failure!
  /**
   * Prepare a Query only once!
   * N.B.: We keep a list of previously prepared statements so as not to prepare any statement more than once.
   *
   * @param                                                PDO                 $dbhQuery
   * @param                                                string              $sQuery
   * @return                                               PDOStatement
   */
  function Prepare_Query(
/*  PDO          */$dbhQuery,
/*               */$sQuery
  )/*: PDOStatement */{
    global $aPreparedQueries;

    if (!isset($aPreparedQueries[$sQuery])) {
      $dbQuery                                               = null;
      try {                                                                                        // Prepare the query...
        $dbQuery                                             = $dbhQuery->prepare($sQuery);
      } catch (PDOException $peException) {
json_Die($peException);
      }

      $aPreparedQueries[$sQuery]                             = $dbQuery;
  return $dbQuery;
    } else {
  return $aPreparedQueries[$sQuery];
    }
  }

  // N.B.: json_Die on failure!
  /** @noinspection PhpDocSignatureInspection */
  /**
   * Connect to the database.
   *
   * @param                                                string=             $sDataBaseName                (optional)
   * @param                                                string=             $sInstanceConnectionName      (optional)
   * @return                                               PDO
   */
  function U_DB_Connect(
/*               */$sDataBaseName                          = P_sUser,
/*               */$sInstanceConnectionName                = U_sInstanceConnectionName
  )/*: PDO */{
    // @see: https://websitebeaver.com/php-pdo-prepared-statements-to-prevent-sql-injection
    $sDataSourceName                                       = 'mysql:unix_socket=/cloudsql/' . $sInstanceConnectionName . ';dbname=' . $sDataBaseName . ';charset=utf8mb4';
    $aPDOOptions                                           = [
      PDO::ATTR_DEFAULT_FETCH_MODE                         => PDO::FETCH_ASSOC,                    // Make the default fetch be an associative array
      PDO::ATTR_EMULATE_PREPARES                           => false,                               // Turn off emulation mode for "real" prepared statements
      PDO::ATTR_PERSISTENT                                 => false                                // ToDo: Can we set this to true after modifying stuff in Apache and mySQL?
    ];

    try {
      $dbHandle                                            = new PDO($sDataSourceName, P_sUser, P_sPassword, $aPDOOptions);

      /** @noinspection PhpUndefinedVariableInspection */
      $dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);                          // Set the PDO error mode to exception
    } catch (PDOException $peException) {
json_Die($peException);
    }

  /** @noinspection PhpUndefinedVariableInspection */
  return $dbHandle;
  }

  // N.B.: VOID returned!
  /**
   * Calls Perform_Query to delete data from the database.
   *
   * @param                                                PDO                 $dbhDelete
   * @param                                                string              $sDeleteQuery
   * @param                                                array               $aDeleteData
   */
  function U_DB_Delete(
/*  PDO          */$dbhDelete,
/*               */$sDeleteQuery,
/*  array        */$aDeleteData
  )/*: void */{
    Perform_Query($dbhDelete, $sDeleteQuery, $aDeleteData);
  }

  /**
   * Calls Perform_Query to insert data into the database.
   *
   * @param                                                PDO                 $dbhInsert
   * @param                                                string              $sInsertQuery
   * @param                                                array               $aInsertData
   * @param                                                bool=               $bSessionInsert     (optional)
   * @return                                               bool
   */
  function U_DB_Insert(
/*  PDO          */$dbhInsert,
/*               */$sInsertQuery,
/*  array        */$aInsertData,
/*               */$bSessionInsert                         = false
  ) {
    $biReturn                                              = Perform_Query($dbhInsert, $sInsertQuery, $aInsertData, $bSessionInsert);
    if (!$bSessionInsert && (false === $biReturn)) {
      $sErrorMessage                                       = 'SERVER ERROR! U_DB_Insert:: Error: Returned false';
      error_log($sErrorMessage);

      if (ini_get('display_errors') == "0") {
  exit();
      } else {
  exit(json_encode(["status" => "ERROR", "message" => $sErrorMessage]));
      }
    }

  return $biReturn;
  }

  /**
   * Performs multiple inserts. @see Perform_Query above.
   *
   * @see https://stackoverflow.com/questions/5526917/how-to-do-a-batch-insert-in-mysql
   * @param                                                PDO                 $dbhInsert
   * @param                                                string              $sInsertQuery
   * @param                                                array               $aMultiInsertData
   */
  function U_DB_Multi_Insert(
/*  PDO          */$dbhInsert,
/*               */$sInsertQuery,
/*  array        */$aMultiInsertData
  )/*: void */{
    $dbInsert                                              = null;

    $dbInsert                                              = Prepare_Query($dbhInsert, $sInsertQuery);
    // N.B.: This will POWER thru the entire array of data to be added.
    // Even if each update is failing, it will keep going!
    $dbhInsert->beginTransaction();                                                                // Helps speed up our inserts.
      foreach ($aMultiInsertData as $aInsertData) {
        Execute_Query($dbhInsert, $dbInsert, $aInsertData, false, true);
      }
    $dbhInsert->commit();
  }

  /**
   * Performs multiple updates. @see Perform_Query above.
   *
   * @param                                                PDO                 $dbhUpdate
   * @param                                                string              $sUpdateQuery
   * @param                                                array               $aMultiUpdateData
   */
  function U_DB_Multi_Update(
/*  PDO          */$dbhUpdate,
/*               */$sUpdateQuery,
/*  array        */$aMultiUpdateData
  )/*: void */{
    // ToDo: See the following if you need to INSERT multiple rows:
    // //stackoverflow.com/questions/1176352/pdo-prepared-inserts-multiple-rows-in-single-query

    $dbUpdate                                              = null;

    $dbUpdate                                              = Prepare_Query($dbhUpdate, $sUpdateQuery);
    // N.B.: This will POWER thru the entire array of data to be added.
    // Even if each update is failing, it will keep going!
    $dbhUpdate->beginTransaction();                                                                // Helps speed up our updates.
      foreach ($aMultiUpdateData as $aData) {
        Execute_Query($dbhUpdate, $dbUpdate, $aData, false, true);
      }
    $dbhUpdate->commit();
  }

  /**
   * Selects data from the database.
   *
   * We Don't use Perform_Query because Execute_Query (which it calls):
   * 1. Does not perform a fetchAll.
   * 2. May perform a bRollBackRequired
   * 3. Handles bSessionInsert.
   *
   * In other words, this is simpler and to the point.
   *
   * @param                                                PDO                 $dbhSelect
   * @param                                                string              $sSelectQuery
   * @param                                                array               $aSelectData
   * @return                                               bool|array
   */
  function U_DB_Select(
/*  PDO          */$dbhSelect,
/*               */$sSelectQuery,
/*  array        */$aSelectData
  ) {

    $biReturnValue                                         = false;

    $dbSelectQuery                                         = Prepare_Query($dbhSelect, $sSelectQuery);
    try {                                                                                          // Load the execute object with the data
      if ($dbSelectQuery->execute($aSelectData)) {
        $biReturnValue                                     = $dbSelectQuery->fetchAll();
      }
    } catch (PDOException $peException) {
json_Die($peException);
    }

  return $biReturnValue;
  }

  /**
   * Calls Perform_Query to update data in the database.
   *
   * @param                                                PDO                 $dbhUpdate
   * @param                                                string              $sUpdateQuery
   * @param                                                array               $aUpdateData
   */
  function U_DB_Update(
/*  PDO          */$dbhUpdate,
/*               */$sUpdateQuery,
/*  array        */$aUpdateData
  )/*: void */{
    Perform_Query($dbhUpdate, $sUpdateQuery, $aUpdateData);
  }
