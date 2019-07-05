/***
 * Bruce A. Knack
 * Silicon Surfers
 *
 * U-Support@SiliconSurfers.com
 * SiliconSurfers.com
 *
 * Project: U V0
 * Created: 2016-03-07.1245
 *
 * Created with PhpStorm.
 */

/**
 * @class
 */
oClasses.oB.cInterface                                     = class {
  constructor() {
    const
      self                                                 = this,

      SS_sTransparentBackground                            = 'transparent',

      TCC_i_overlayAccount                                 = C_i_clearCover + C_sClearCover2,
      TCC_i_overlayConfirmBox                              = C_i_clearCover + C_sClearCover3,
      TCC_i_overlayContactUs                               = C_i_clearCover + C_sClearCover2,
      TCC_i_overlay2ChoiceBox                              = C_i_clearCover + C_sClearCover3,

      UF_bStopRefade                                       = true,

      WCE_bAddBackground                                   = true,

      WS_iU                                                = 0,
      WS_iFollowingList                                    = 1,
      WS_iFamousList                                       = 2,
      WS_iListedList                                       = 3,
      WS_iFollowersList                                    = 4,

      /**
       * Handles loading the aCenterIcons from the database as well as displaying them.
       *
       * @function
       */
      Load_And_Display_Center_Icons_bDemoMode              = () => {
        const
          /**
           * @param                                        {string=}           soServerData
           * @function
           */
          Handle_Success                                   = (soServerData = '') => {
            let
              aCenterIcons                                 = oT.Server_Data(soServerData, oT.SD_iArray),
              sHTML                                        = ''
            // end let


            if (false === aCenterIcons) {                                                          // ToDo: Handle this better!
          return
            }

            aCenterIcons.forEach((oCenterIcon, iCenterIcon) => {                                   // First, we create the icons...
              sHTML                                       += '<img alt="" class="' + C_centerIcon + ' ' + C_iconType + oCenterIcon.iIconType + '" height="100" id="' + C_centerIcon + iCenterIcon + '" src="' + C_aIconTypes[oCenterIcon.iIconType] + '" width="100">\n'
            })
            $(C_i_centerIconsInner).html(sHTML)                                                    // N.B.: #centerIcons (.centerIcon) rock by default (set in styles.css).

            aCenterIcons.forEach((oCenterIcon, iCenterIcon) => {                                   // Then, after they've been created, we add data to them.
              // noinspection MagicNumberJS
              $(C_i_centerIcon + String(iCenterIcon)).data('sIconType', C_iconType + oCenterIcon.iIconType)
            })
            $(C_i_centerIcons).show()
          }
        // end const

        oT.Server_Call({
          f_Handle_Error                                   : null,
          f_Handle_Success                                 : Handle_Success,
          sAsynchRequest                                   : C_sLoadCenterIconsDemo
        })
      }
    // end const

    let
      bRealExternalTap                                     = true,
      bSpinnerWaiting                                      = false,
      bWelcomeScreenDisplayed                              = false,

      iPhase1,
      iPhase2,
      iPhase3,

      iRefadeTimeout,
      iStartSpinningTimeout,

      iScrollInterval
    // end let

//<editor-fold desc="object.defines...">
    Object.defineProperty(this, 'UF_bStopRefade', {
      get                                                  : () => UF_bStopRefade
    })

    Object.defineProperty(this, 'SS_sTransparentBackground', {
      get                                                  : () => SS_sTransparentBackground
    })

    Object.defineProperty(this, 'TCC_i_overlayAccount', {
      get                                                  : () => TCC_i_overlayAccount
    })
    Object.defineProperty(this, 'TCC_i_overlayConfirmBox', {
      get                                                  : () => TCC_i_overlayConfirmBox
    })
    Object.defineProperty(this, 'TCC_i_overlayContactUs', {
      get                                                  : () => TCC_i_overlayContactUs
    })
    Object.defineProperty(this, 'TCC_i_overlay2ChoiceBox', {
      get                                                  : () => TCC_i_overlay2ChoiceBox
    })

    Object.defineProperty(this, 'WCE_bAddBackground', {
      get                                                  : () => WCE_bAddBackground
    })

    Object.defineProperty(this, 'WS_iFollowersList', {
      get                                                  : () => WS_iFollowersList
    })
    Object.defineProperty(this, 'WS_iFollowingList', {
      get                                                  : () => WS_iFollowingList
    })
    Object.defineProperty(this, 'WS_iListedList', {
      get                                                  : () => WS_iListedList
    })
    Object.defineProperty(this, 'WS_iFamousList', {
      get                                                  : () => WS_iFamousList
    })
    Object.defineProperty(this, 'WS_iU', {
      get                                                  : () => WS_iU
    })

    Object.defineProperty(this, 'bWelcomeScreenDisplayed', {
      get                                                  : () => bWelcomeScreenDisplayed,
      set                                                  : (bValue) => { bWelcomeScreenDisplayed = bValue }
    })

    Object.defineProperty(this, 'iScrollInterval', {
      get                                                  : () => iScrollInterval,
      set                                                  : (iValue) => { iScrollInterval = iValue }
    })
//</editor-fold>

    // noinspection OverlyComplexFunctionJS
    /**
     * N.B.: This function is designed to handle ALL the intricacies involved with Displaying AND clearing overlays.
     *       This INCLUDES a case statement that is aware of WHICH overlay has been fired!
     *       Keeping this all here makes sense because of the gory details associated with doing all this.
     *
     * Display_Overlay as well as clearCover (allows the overlay to be cleared if the "background" is tapped anywhere around the overlay.
     *
     * @see: https://stackoverflow.com/questions/1300242/passing-a-function-with-parameters-as-a-parameter
     * @see: https://stackoverflow.com/questions/3458553/javascript-passing-parameters-to-a-callback-function
     * @see: https://www.w3schools.com/js/js_function_parameters.asp
     * @see: https://stackoverflow.com/questions/8356227/skipping-optional-function-parameters-in-javascript
     *
     * @param                                              {boolean=}          bCriticalError
     * @param                                              {function=}         f_OnExternalTap
     * @param                                              {number=}           iButtonWidth
     * @param                                              {number=}           iProgressBarOwner
     * @param                                              {number=}           iNumberOfChoices
     * @param                                              {object=}           oEvent
     * @param                                              {string=}           sChoice1Text
     * @pa ram                                              {string=}           sChoice2Text
     * @param                                              {string=}           sClearOverlayNumber
     * @param                                              {string=}           sMessage
     * @param                                              {string=}           sOverlay
     * @function
     */
    this.Display_Overlay                                   = ({
      bCriticalError                                       = false,
      f_OnExternalTap                                      = () => {},
      iButtonWidth                                         = -1,
      iProgressBarOwner                                    = -1,
      iNumberOfChoices                                     = 0,
      oEvent                                               = {},
      sChoice1Text                                         = '',
//      sChoice2Text                                         = '',
      sClearOverlayNumber                                  = '',
      sMessage                                             = '',
      sOverlay                                             = 'Required: sOverlay'
    }) => {
      // noinspection MagicNumberJS, OverlyComplexFunctionJS
      const
        DO_iClearOverlay102                                = 102,
        DO_iSlowShow                                       = 450,                                  // Just a little shorter than the scroll effect.
        DO_iWaitBeforeRefadeShareIconMS                    = 250,
        DO_iWaitToResetOverlay                             = 490,

        jqDropDowns                                        = $(C_i_overlaySignIn + ',' + C_i_dropDownCombo + ',' + C_i_interfaceRingsLevelContainer + ',' + C_i_interfaceRingsLevel0),
        jqDropDownTexts                                    = $(C_i_dropDownCombo),
        jqOverlaySignIn                                    = $(C_i_overlaySignIn),

        jqOverlay                                          = $(sOverlay),

        jqOverlay2ChoiceBox1Button                         = $(C_i_overlay2ChoiceBox1Button),

        /**
         * This should Clear the Overlay as well as the appropriate clearCover.
         * N.B.: It also unbinds overlay2ChoiceBox and overlayConfirmBox buttons!
         *
         * @param                                          {number}            iProgressBarOwner
         * @param                                          {object}            jqOverlay
         * @param                                          {string}            sOverlay
         * @param                                          {string}            sMessage
         * @param                                          {string}            sClearOverlayNumber
         * @param                                          {object}            oEvent
         * @function
         */
        Clear_Overlay                                      = (iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent) => {
          const
            /**
             * Just keeps things cleaner than having these two lines repeat over and over.
             *
             * @param                                      {number}            iProgressBarOwner
             * @param                                      {object}            jqOverlay
             * @constructor
             */
            Hide_Overlay                                   = (iProgressBarOwner, jqOverlay) => {
              oT.ProgressBar_End(iProgressBarOwner)
              jqOverlay.hide()
            },

            jqClearCover                                   = $(C_i_clearCover + sClearOverlayNumber),
            jqdropDownListLine                             = $(C_c_dropDownListLine)
          // end const

          let
            sDNSsMessage
          // end let

          if ('undefined' !== typeof oEvent) {
            oEvent.stopImmediatePropagation()
          }

          if (C_i_dropDownHeaderBox  !== sOverlay) {                                               // Fades are handled differently for dropDownHeaderBox
            this.Perform_Fades()
          }

          jqClearCover
            .hide()
            .off(C_click)                                                                          // We MUST turn this off, we don't need or want it again!
          // end $jq

          jqOverlay.off(C_click)                                                                   // We MUST turn this off, we don't need or want it again!

          switch (sOverlay) {                                                                      // Special handling required to clear the sOverlay currently displayed
            case C_i_clearCoverWelcome4:
              this.Welcome_Clear_Elements()                                                        // Clear any #wS elements
              Hide_Overlay(iProgressBarOwner, jqOverlay)
          break

            case C_i_cropperBottomBar:
              if ('undefined' !== typeof oU.ECI) {
                oU.ECI.Remove_Crop_Dialog()
              }
          break

            case C_i_dropDownHeaderBox:
              if ('none' === $(C_i_dropDownListBatchOperations).css('display')) {
                this.Welcome_Clear_Elements()                                                      // Clear any #wS elements

                // noinspection ConstantOnRightSideOfComparisonJS
                if (jqdropDownListLine.length > 0) {
                  if ('none' === jqdropDownListLine.css('display')) {                              // The Welcome_Screen was likely up...
                    jqdropDownListLine.show()
                    jqClearCover                                                                   // Re-establish this cover!
                      .show()
                      .on(C_click, function(oEvent) {
                        self.Perform_Fades()
                        Clear_Overlay(iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent)
                      })
                    // end $jq
          // noinspection BreakStatementJS
          break
                  } else {
                    self.Perform_Fades()
                  }
                }

                $(C_i_dropDownListBox).hide()
                Hide_Overlay(iProgressBarOwner, jqOverlay)
                oB.LU.Display_U_Name()
              } else {                                                                             // Services list...
                $(C_i_dropDownListBox).hide()
                Hide_Overlay(iProgressBarOwner, jqOverlay)

                if ('undefined' !== oB.OB) {
                  oB.OB.Reset_DropDownCombo()
                }
              }
          break

            case C_i_overlayConfirmBox:
              $(C_i_overlayConfirmBoxButton).off(C_click)

              if ('undefined' === typeof oB.OB) {
                sDNSsMessage                               = ''
              } else {
                sDNSsMessage                               = C_sTapToShareMessage
              }
              // noinspection NestedSwitchStatementJS
              switch (sMessage) {                                                                  // This is fairly grotesque, but we're up against a beta test deadline!!
                case oB.RTA_sMessage:
$(C_i_signOutButton).trigger(C_click)                                                              // This is nasty because we're going to signOut in seconds no matter what!
              break

                case oB.CaI_sMessage:
                case oB.EH_sAwayTooLong:
                case oB.LL_sError:
                case oT.FR_sMessage:
                case oT.US_sUnregisterFailure:
                case oT.VU_sMessage:
/*
oT.Log_Debug({
  bForceRecord                                             : true,
  sFileURL                                                 : 'oB.Inteface.js.Display_Overlay.Clear_Overlay.switch(sOverlay).switch(sMessage)',
  sFunction                                                : oT.D_sRecord,
  sText                                                    : 'Reloading...'
})
*/
location.reload(true)                                                                              // This is nasty because we're going to reload in seconds no matter what!
              break

                case oB.EI_sMessage:
                  Hide_Overlay(iProgressBarOwner, jqOverlay)
                  $(C_c_editIcon).trigger(C_click)                                                 // This is GROSS!! Properly 'redisplay' the icon's edit box.
              break

                case sDNSsMessage:
                  Hide_Overlay(iProgressBarOwner, jqOverlay)
                  setTimeout(() => {
                    self.Refade($(C_i_shareIcon))
                  }, DO_iWaitBeforeRefadeShareIconMS)
                  oB.S.On_Boarding_Sign_Out()
              break

                default:
                  Hide_Overlay(iProgressBarOwner, jqOverlay)
                  // noinspection MagicNumberJS
                  if ('<div style="display:inline-block;width:100%">' + oB.EI_sError === sMessage.substr(0, 45 + oB.EI_sError.length)) {
                    $(C_c_editIcon).trigger(C_click)                                               // This is GROSS!! Properly 'redisplay' the icon's edit box.
                  }
              }
          break

            case C_i_overlaySignIn:
              jqOverlaySignIn.css('z-index', -1)                                                   // set to -1 so it slides under feedback box
              jqDropDowns.removeClass(C_down)

              jqDropDownTexts.show(DO_iSlowShow)
              $(C_i_accountIcon).show(DO_iSlowShow)
              $(C_i_interfaceSwitchIcon).show(DO_iSlowShow)
          break

            case C_i_overlay2ChoiceBox:
              oT.Reset_2ChoiceBox()
              $(C_c_overlay2ChoiceBoxButtons).off(C_click)
          break

            default:
              Hide_Overlay(iProgressBarOwner, jqOverlay)
          break
          }
        },

        /**
         * Displays the overlay and handles Clear_Overlay if bCriticalError is requested
         *
         * @param                                          {boolean}           bCriticalError
         * @param                                          {number}            iProgressBarOwner
         * @function
         */
        Default_Process                                    = (bCriticalError, iProgressBarOwner) => {
          jqOverlay.show()
          if (!bCriticalError) {
            jqOverlay.on(C_click, (oEvent) => {
              Clear_Overlay(iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent)
            })
          }
        },

        /**
         * Handles configuring and displaying the Snapchat button. This includes loading Snapchat's SDK.
         *
         * @function
         */
        Handle_Snapchat                                    = () => {
          const
            Handle_Message                                 = () => {
              // noinspection MagicNumberJS
              const
                iHeight                                    = 600,
                iWidth                                     = 600,
                sIconName                                  = 'Snapchat',
                sIconURL                                   = P_sSiteURL + 'snapchat.inc?go=yes&ts=' + Date.now(),

                /**
               * @param                                            {string}            sTextStatus
               * @param                                            {string}            sErrorThrown
               * @function
               */
                Handle_Error                                       = (sTextStatus = '', sErrorThrown = '') => {
                  oT.Log_Error({
                    bDisplayError                                  : true,
                    sErrorInfo                                     : 'sorry, something went wrong...<br>we weren' + C_sApostrophe + 't able to add your profile<br>hmm... try again later?',
                    sErrorThrown                                   : sErrorThrown,
                    sFileURL                                       : 'EditIcon.js(' + String(oU.oCurrentIcon.iIconType) + '.js).this.Get_Profile.Handle_Error',
                    sTextStatus                                    : sTextStatus
                  })
                },

                Message_Receive                                    = (oEvent) => {
                  let
                    oMessage
                  // end let

                  $(window).off('storage', Message_Receive)
                  if ('message' === oEvent.originalEvent.key) {
                    oMessage                                       = JSON.parse(oEvent.originalEvent.newValue)
                    if (oMessage) {
                      oB.S.Execute_Script(oB.S.ES_sIconScript, oU.oCurrentIcon.iIconType, oB.S.ES_sIconDataSubmit, oMessage)
                    } else {
                      Handle_Error('Message Receive: oEvent.originalEvent.newValue was NOT an object (oMessage): ' + oEvent.originalEvent.newValue, 'Message Receive: JSON object expected.')
                    }
                  } else {
                    Handle_Error('Message Receive: oEvent.originalEvent.key was NOT \'message\': ' + oEvent.originalEvent.key, 'Message Receive: \'message\' not found')
                  }
                }
              // end const

              let
                iLeft                                              = ($(window.top).width()  - iWidth ) / 2,
                iTop                                               = ($(window.top).height() - iHeight) / 2
              // end let

              $(window).on('storage', Message_Receive)
              open(sIconURL, sIconName, 'height=' + iHeight + ',left=' + iLeft + ',top=' + iTop + ',location=0,menubar=0,resizable=0,scrollbars=0,status=0,toolbar=0,width=' + iWidth)
            }
          // end const

          jqOverlay2ChoiceBox1Button.hide()

          window.snapKitInit                               = () => {
            // noinspection JSUnresolvedVariable, JSUnresolvedFunction
            snap.loginkit.mountButton(C_overlay2ChoiceBoxSnapchat, {                               // Mount Login Button
              clientId                                     : '7300ef48-745f-4fce-94b9-f3023c2070d9',
              redirectURI                                  : 'https://u.info/snapchat.inc',
              scopeList                                    : [
                'user.display_name'
              ],
              handleResponseCallback                       : function handleResponseCallback() {
                alert('hello!')
                // noinspection JSUnresolvedVariable, JSUnresolvedFunction
                snap.loginkit.fetchUserInfo()
                  .then(data => console.log('User info:', data))
                // end snap
              },
            })

            $(C_i_overlay2ChoiceBoxSnapchat).show()

            Handle_Message()
          }

          (function(eDocument, sScriptTag, sLoginKitSDKId) {                                       // Load the SDK asynchronously
            const
              eFirstScript                                 = eDocument.getElementsByTagName(sScriptTag)[0]
            // end const

            let
              eSnapchatKitScript
            // end let

            if (eDocument.getElementById(sLoginKitSDKId)) {                                        // Don't do this more than once!
          return
            }

            eSnapchatKitScript                               = eDocument.createElement(sScriptTag)
            eSnapchatKitScript.id                            = sLoginKitSDKId
            eSnapchatKitScript.src                           = 'https://sdk.snapkit.com/js/v1/login.js'
            eFirstScript.parentNode.insertBefore(eSnapchatKitScript, eFirstScript)
          } (document, 'script', 'loginkit-sdk'))
        }
      // end const

      if ('undefined' !== typeof C_i_overlay2ChoiceBoxSnapchat) {
        $(C_i_overlay2ChoiceBoxSnapchat).hide()                                                    // Just in case!
      }

      if (!oT.Is_Empty_Object(oEvent)) {
        oEvent.stopImmediatePropagation()
      }

      if (!bCriticalError) {
        $(C_i_clearCover + sClearOverlayNumber)
          .show()
          .on(C_click, function(oEvent) {                                                          // Allows us to clear the sOverlay if the background outside it is clicked
            if ($(C_i_dropDownSearch).hasClass(C_dataEntry) && ('1' === sClearOverlayNumber)) {
              $(C_i_dropDownButton + ',' + C_i_dropDownCombo + ',' + C_i_dropDownSearch + ',' + C_i_dropDownSearchStar + ',' + C_i_accountIcon + ',' + C_i_accountPhoto).removeClass(C_dataEntry)
              $(C_i_interfaceSwitchIcon).show()                                                    // Grumble!
            }
            Clear_Overlay(iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent)
            if (bRealExternalTap) {
              f_OnExternalTap()                                                                    // We ALSO do this!
            } else {
              bRealExternalTap                               = true                                // Ready for next time
            }
          })
        // end $jq
      }

      switch (sOverlay) {                                                                          // Special handling required to display the sOverlay
        case C_i_overlayContactUs:                                                                 // This just stops Default_Process...
          jqOverlay.show()
          // We don't want this to dissappear when it is clicked!
      break

        case C_i_overlaySignIn:
          bWelcomeScreenDisplayed ? $(C_i_joinNote).show() : $(C_i_joinNote).hide()

          jqOverlaySignIn.css('z-index', -1)                                                       // set to -1 so it slides under feedback box

          jqDropDowns.addClass(C_down)
          jqDropDownTexts.hide(DO_iSlowShow)
          setTimeout(() => {jqOverlaySignIn.css('z-index', DO_iClearOverlay102)}, DO_iWaitToResetOverlay)    // set this back to 102
          jqOverlaySignIn.on(C_click, function(oEvent) {
            Clear_Overlay(iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent)
          })
      break

        case C_i_overlayConfirmBox:
          // noinspection NestedSwitchStatementJS
          switch (sMessage) {                                                                      // This is fairly grotesque, but we're up against a beta test deadline!!
            case oB.CaI_sMessage:
            case oB.EH_sAwayTooLong:
            case oB.LL_sError:
            case oT.FR_sMessage:
            case oT.US_sUnregisterFailure:
            case oT.VU_sMessage:
              jqOverlay.css('z-index', '108')                                                      // Ensures this notice is ABOVE all else (in particular oT.VU_sMessage above Welcome4!)
          }
          $(C_i_overlayConfirmBoxButton).on(C_click, (oEvent) => {
            if (jqOverlay.hasClass(C_follow)) {                                                    // Do NOT Clear_Overlay here! @see: oB.IUL.Follow_Command.Execute_Follow_Command
              jqOverlay.removeClass(C_follow)
            } else {
              Clear_Overlay(iProgressBarOwner, jqOverlay, sOverlay, sMessage, sClearOverlayNumber, oEvent)
            }
          })
          Default_Process(bCriticalError, iProgressBarOwner)
      break

        case C_i_overlay2ChoiceBox:
          // noinspection NestedSwitchStatementJS
          switch (iNumberOfChoices) {
            case 0:                                                                                // Default to yes/no
              oT.Reset_2ChoiceBox()
          break

            case 1:
              jqOverlay2ChoiceBox1Button.hide()
          break

            case 2:
              if (-1 !== iButtonWidth) {
                jqOverlay2ChoiceBox1Button.css('left', ($(C_i_overlay2ChoiceBox).width() - iButtonWidth) / 2)
              }

              if ('Snapchat' === sChoice1Text) {
                 Handle_Snapchat()
              } else {
                jqOverlay2ChoiceBox1Button
                  .html(sChoice1Text)
                  .show()
                // end $jq
              }
          break
          }

          jqOverlay.show()                                                                         // We don't want to allow clicking the popup to make it disappear
      break

        default:
          Default_Process(bCriticalError, iProgressBarOwner)
      }
    }

    /**
     * N.B.: Everything associated with Display_Overlay is handled WITHIN that function. This is intentional.
     *
     * Display the requested sOverlayBox.
     *
     * @param                                              {boolean=}          bCriticalError
     * @param                                              {boolean=}          bDisplayOverlayBox
     * @param                                              {boolean=}          bOKButton
     * @param                                              {function=}         f_OnExternalTap
     * @param                                              {number=}           iButtonWidth
     * @param                                              {number=}           iProgressBarOwner
     * @param                                              {number=}           iNumberOfChoices
     * @param                                              {object=}           oEvent
     * @param                                              {string=}           sChoice1Text
     * @param                                              {string=}           sChoice2Text
     * @param                                              {string=}           sMessage
     * @param                                              {string=}           sOverlayBox
     * @function
     */
    this.Display_Overlay_Box                               = ({
      bCriticalError                                       = false,
      bDisplayInputBox                                     = false,
      bOKButton                                            = true,
      f_OnExternalTap                                      = () => {},
      iButtonWidth                                         = -1,
      iProgressBarOwner                                    = -1,
      iNumberOfChoices                                     = 0,
      oEvent                                               = {},
      sChoice1Text                                         = '',
      sChoice2Text                                         = '',
      sMessage                                             = 'Required: sMessage',
      sOverlayBox                                          = 'Required: sOverlayBox!'
    }) => {
      const
        jqOverlayBox                                       = $(sOverlayBox)
      // end const

      if (bCriticalError) {
        this.Hide_Spinner()                                                                        // Nothing should obsure this box
        jqOverlayBox.addClass(C_criticalError)
      } else {
        jqOverlayBox.removeClass(C_criticalError)
      }

      $(C_i_overlay2ChoiceBoxBottomBar).removeClass(C_overlay2ChoiceBoxInput + ' ' + C_overlay2ChoiceBoxInputOnly)
      if (bDisplayInputBox) {
        $(C_i_overlay2ChoiceBoxBottomBar).addClass(C_overlay2ChoiceBoxInput)
        if (1 === iNumberOfChoices) {
          $(C_i_overlay2ChoiceBoxBottomBar).addClass(C_overlay2ChoiceBoxInputOnly)
        }
      }

      if (bOKButton) {
        $(C_i_overlayConfirmBoxButton).removeClass(C_noOKButton)
      } else {
        $(C_i_overlayConfirmBoxButton).addClass(C_noOKButton)
      }

      $(sOverlayBox + ' ' + C_c_msg).html(sMessage)

      self.Display_Overlay({                                                                       // This lets the clearCover cover any lower overlays.
        bCriticalError                                     : bCriticalError,
        f_OnExternalTap                                    : f_OnExternalTap,
        iButtonWidth                                       : iButtonWidth,
        iProgressBarOwner                                  : iProgressBarOwner,
        iNumberOfChoices                                   : iNumberOfChoices,
        oEvent                                             : oEvent,
        sChoice1Text                                       : sChoice1Text,
        sChoice2Text                                       : sChoice2Text,
        sClearOverlayNumber                                : C_sClearCover3,
        sMessage                                           : sMessage,
        sOverlay                                           : sOverlayBox
      })
    }

    /**
     * @param                                              {object}            jqElement
     * @function
     */
    this.Fade                                              = (jqElement) => {
      jqElement
        .removeClass(C_unFade)
        .addClass(C_fade)
      // end $jq
    }

    /**
     * @function
     */
    this.Hide_Spinner                                      = () => {
      if (bSpinnerWaiting) {
        bSpinnerWaiting                                    = false
        clearTimeout(iStartSpinningTimeout)
      }

      if ('none' !== $(C_i_overlayWaitSpinnerImage).css('display')) {                             // Only hide it if it is visible!
        $(C_i_overlayWaitSpinnerCover5 + ',' + C_i_overlayWaitSpinnerImage).hide()

        $(C_i_accountSpinner)
          .css('visibility', 'hidden')
          .removeClass(C_waiting)
        // end $jq
      }
    }

    /**
     * Causes screen elements to fade.
     *
     * @param                                              {number}            iWaitInMS
     * @function
     */
    this.Perform_Fades                                     = (iWaitInMS = 0) => {
      let
        sAddFollowing                                      = ''
      // end let

      if (oB.IDU.bDisplayingU) {
        oB.IDU.bDisplayingU                                = false

        // noinspection MagicNumberJS
        iWaitInMS                                          = 5000
      }

      setTimeout(() => {
        if (C_iAnonymousUser !== oB.oUser.iUser) {
          sAddFollowing                                    = C_c_following + ','
        }

        this.Fade($(sAddFollowing + C_i_accountPhoto + ',' + C_c_fadable))                       // i_accountPhoto has special class handling, so does NOT have fadable class.
      }, iWaitInMS)
    }

    /**
     * @param                                              {object}            jqElement
     * @function
     */
    this.Refade                                            = (jqElement) => {
      // noinspection MagicNumberJS
      const
        R_iWait2Seconds                                    = 2000
      // end const

      this.Unfade(jqElement)

      if ('none' !== $(C_i_dropDownHeaderBox).css('display')) {                                   // N.B.: We need special handling when the dropDownHeader stuff is down. Without this, it fades when a different column set is picked!
        clearTimeout(iRefadeTimeout)
      }
      iRefadeTimeout                                       = setTimeout(() => {
        this.Fade(jqElement)
      }, R_iWait2Seconds)
    }

    /**
     * @param                                              {boolean}           bGrayScale
     * @return                                             {string}
     * @function
     */
    this.Set_GrayScale                                     = (bGrayScale) => {
    return                                                 bGrayScale ? C_grayScale : ''
    }

    /**
     * @param                                              {number=}           iWaitToStartMS,
     * @param                                              {string=}           sBackgroundColor
     * @function
     */
    this.Show_Spinner                                      = (iWaitToStartMS = 0, sBackgroundColor = 'rgba(255, 255, 255, 0.75)') => {
      if ('none' === $(C_i_overlayWaitSpinnerImage).css('display')) {                             // No sense doing this if we're already doing it!
        bSpinnerWaiting                                    = true
        iStartSpinningTimeout                              = setTimeout(() => {
          if (bSpinnerWaiting) {
            bSpinnerWaiting                                = false
            if (!oB.bWaitSpinnerCreated) {
              oB.IR.Create_Wait_Spinner()
            }
            $(C_i_overlayWaitSpinnerCover5).css('background-color', sBackgroundColor)
            $(C_i_overlayWaitSpinnerCover5 + ',' + C_i_overlayWaitSpinnerImage).show()

            $(C_i_accountSpinner)
              .css('visibility', 'visible')
              .addClass(C_waiting)
            // end $jq
          }
        }, iWaitToStartMS)
      }
    }

    /**
     * Called when a clearCover is in place and should be triggered.
     * Triggers the oB.I.Display_Overlay.Clear_Overlay callback.
     * N.B.: This has the 'side effect' of also removing any popup overlay because triggering it's associated Clear_Overlay causes it to .hide()
     *
     * @param                                              {string}            sClearCover
     * @p aram                                              {boolean}           bUnBind
     * @function
     */
    this.Trigger_Clear_Cover                               = (sClearCover/*, bUnBind*/) => {
      let
        jqClearCover                                       = $(sClearCover)
      // end let

      if ('none' !== jqClearCover.css('display')) {
        bRealExternalTap                                   = false
        jqClearCover.trigger(C_click/*, bUnBind*/)
      }
    }

    /**
     * @param                                              {object}            jqElement
     * @param                                              {boolean=}          bStopRefade
     * @function
     */
    this.Unfade                                            = (jqElement, bStopRefade = false) => {
      if ((bStopRefade) && ('undefined' !== iRefadeTimeout)) {
        clearTimeout(iRefadeTimeout)
      }
      jqElement
        .removeClass(C_fade)
        .addClass(C_unFade)
      // end $jq
    }

    /**
     * Called whenever it is possible that a clearCover is in place and should be triggered.
     * Unwinds the oB.I.Display_Overlay.Clear_Overlay callback.
     *
     * @function
     */
    this.Unwind_Clear_Cover                                = () => {
      let
        iClearCover,
        jqClearCover
      // end let

      for (iClearCover = C_iTopClearCover; iClearCover >= C_iBottomClearCover; iClearCover--) {
        jqClearCover                                       = $(C_i_clearCover + String(iClearCover))
        if ('none' !== jqClearCover.css('display')) {
          bRealExternalTap                                 = false
          jqClearCover.trigger(C_click)
      // noinspection BreakStatementJS
      break                                                                                        // We should only have one #cC### at a time, so we're done.
        }
      }
    }

    /**
     * Clears up all the elements we were using for the welcome screen.
     *
     * @param                                              {boolean}           bAddBackground
     * @function
     */
    this.Welcome_Clear_Elements                            = (bAddBackground = false) => {
      // Immediately stop everything!
      clearTimeout(iPhase1)
      clearTimeout(iPhase2)
      clearTimeout(iPhase3)

      if (bAddBackground) {
        this.Unwind_Clear_Cover()
      }

      $(C_i_addBackground3 + ',' + C_i_clearCoverWelcome4                                  + ',' + C_i_welcomeScreenJoinButton).off(C_click)
      $(C_i_addBackground3 + ',' + C_i_clearCoverWelcome4 + ',' + C_i_welcomeScreenTopText + ',' + C_i_welcomeScreenJoinButton + ',' + C_i_welcomeScreenBottomText + ',' + C_i_centerIcons).hide()
      $(C_i_addBackground3                                + ',' + C_i_welcomeScreenTopText + ',' + C_i_welcomeScreenJoinButton + ',' + C_i_welcomeScreenBottomText + ',' + C_i_dropDownHeaderBox).removeClass() // Remove all classes

      $(C_i_centerImage).show()                                                                     // Show the #centerImage

      $(C_i_dropDownSearch).prop('disabled', false)                                                 // Let folks search again!
    }

    /**
     * First show the sTopText and then wait a bit.
     * Slide the text up while fading the background almost to transparent and fading the center icons in.
     * Finally, side in "Share Your U"
     *
     * @param                                              {number}            iSource
     * @function
     */
    this.Welcome_Screen                                    = (iSource) => {
      // noinspection MagicNumberJS
      const
        WS_fFractionOfWaitTimeInMS                         = 0.5,
        WS_iButtonSlideTimeInMS                            = 800,
        WS_iWaitTimeInMS                                   = 500,

        jqclearCoverWelcome4                               = $(C_i_clearCoverWelcome4),
        jqWelcomeScreenTopText                             = $(C_i_welcomeScreenTopText),
        jqWelcomeScreenJoinButton                          = $(C_i_welcomeScreenJoinButton),
        jqWelcomeScreenBottomText                          = $(C_i_welcomeScreenBottomText),

        /**
         * Clears up the elements and Activates the sign in.
         *
         * @function
         */
        Handle_Click                                       = (oEvent) => {
          oEvent.stopImmediatePropagation()
          this.Welcome_Clear_Elements()
          this.Unwind_Clear_Cover()
          $(C_i_joinNote).html(C_sWelcome)
          $(C_i_accountIcon_cEmpty).trigger(C_click)
        }
      // end const

      let
        bAddBackground                                     = false,
        sTopText
      // end let

      bWelcomeScreenDisplayed                              = true                                  // We only want to show this once per session!

      this.Welcome_Clear_Elements()                                                                // Start fresh

      $(C_i_dropDownSearch).prop('disabled', true)                                                 // We can't let folks search while in the Welcome_Screen

      jqclearCoverWelcome4
// Disable the background click.        .on(C_click, Handle_Click)                                 // Event Handler
        .show()
      // end $jq

      switch (iSource) {
        case WS_iU:                                                                                // ToDo: 2018-04-27 I don't think this can happen! @see: oB.IUL.Follow_Command case C_setToFollow:
          self.Display_Overlay({
            sClearOverlayNumber                            : C_sClearCover1,
            sOverlay                                       : C_i_clearCoverWelcome4
          })
          sTopText                                         = 'follow friends'
          jqWelcomeScreenTopText
            .addClass(C_background)
            .removeClass(C_twoLines + ' ' + C_threeLines)                                          // Just in case
          // end $jq

          bAddBackground                                   = true
      break

        case WS_iFollowingList:
          sTopText                                         = 'follow friends'
          jqWelcomeScreenTopText.removeClass(C_background + ' ' + C_twoLines + ' ' + C_threeLines) // Just in case
//          Immediately_Display()
//    return                                                                                         // Immediate exit!
      break

        case WS_iListedList:
          sTopText                                         = 'follow friends'
          jqWelcomeScreenTopText.removeClass(C_background + ' ' + C_twoLines + ' ' + C_threeLines) // Just in case
      break

        case WS_iFamousList:
          sTopText                                         = 'Join<br>to<br>Follow'
          jqWelcomeScreenTopText
            .removeClass(C_background + ' ' + C_twoLines)                                          // Just in case
            .addClass(C_threeLines)
          // end $jq
      break

        case WS_iFollowersList:
          sTopText                                         = 'build<br>followers'
          jqWelcomeScreenTopText
            .removeClass(C_background + ' ' + C_threeLines)                                        // Just in case
            .addClass(C_twoLines)
          // end $jq
//          Immediately_Display()
//    return                                                                                         // Immediate exit!
      break
      }

      // Show the text
      jqWelcomeScreenTopText
        .html(sTopText)
        .show()
      // end $jq

      iPhase1                                              = setTimeout(() => {
        $(C_i_centerImage).hide()                                                                 // Hide the #centerImage

        if (bAddBackground) {
          $(C_i_addBackground3).show()
        }

        jqWelcomeScreenTopText.addClass(C_up + ' ' + C_background)                               // Slide the button up
        setTimeout(() => { jqWelcomeScreenTopText.addClass(C_background) }, WS_iButtonSlideTimeInMS)

        if (bAddBackground) {
          $(C_i_addBackground3).addClass(C_rollUp)
        } else {
          $(C_i_dropDownHeaderBox).addClass(C_rollUp)                                            // rollUp the box
        }
        Load_And_Display_Center_Icons_bDemoMode()                                                  // Load the #centerIcons in bDemoMode
                                                                                                   // and fade them in.
        iPhase2                                            = setTimeout(() => {
          if (bAddBackground) {
            $(C_i_addBackground3).removeClass(C_rollUp)
          } else {
            $(C_i_dropDownHeaderBox).removeClass(C_rollUp)                                       // roll the box back down
          }
          jqWelcomeScreenBottomText.show()

          iPhase3                                          = setTimeout(() => {
            jqWelcomeScreenTopText.removeClass(C_background)
            jqWelcomeScreenJoinButton
              .on(C_click, Handle_Click)                                                        // Event Handler
              .show()
            // end $jq
            jqWelcomeScreenBottomText.addClass(C_down)
          }, WS_iWaitTimeInMS * WS_fFractionOfWaitTimeInMS)
        }, WS_iWaitTimeInMS * 3)
      }, WS_iWaitTimeInMS)
    }
  }
}

