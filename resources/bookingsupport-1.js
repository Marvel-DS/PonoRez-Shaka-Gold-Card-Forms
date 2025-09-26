this.PonoRezExternal = this.PonoRezExternal || { };

this.PonoRezExternal.BookingSupport = (function(global) {

  var ActivitySelectMode = {
    Loading: 'LOADING',
    Active: 'ACTIVE',
    FullSet: 'FULL_SET'
  };

  var $ = global.jQuery;
  var Accommodation = global.PonoRezExternal.Accommodation;

  var _baseUrl = _detectNonProductionBaseUrl() || 'https://ponorez.online/reservation/';

  var _AVAILABILITY_QUERY_TIMEOUT_MS = 30 * 1000;
  var _AVAILABILITY_QUERY_MAX_COUNT = 2;

  /* contextData structure:
  {
    supplierId: 128, // may be omitted in legacy forms without accommodation controls
    agencyId: 0, // may be omitted in legacy forms without accommodation controls and new orders
    selectActivityAfterDate: true, // may be omitted (interpreted as false) in legacy forms
    groupSelectSelector: '.BookingForm_a173c5a0 .groupSelect',
    allActivitySelectContainersSelector: '.BookingForm_a173c5a0 .activitySelectContainer',
    activitySelectSubselector: '.activitySelect',
    activitySelectSelectOptionSubsubselector: '.selectOption', // not needed/used if not 'selectActivityAfterDate'
    activitySelectLoadingOptionSubsubselector: '.loadingOption', // not needed/used if not 'selectActivityAfterDate'
    dateTextInputSelector: '.BookingForm_a173c5a0 .dateTextInput', // not needed/used with Activity-Date-Guests order
    allGuestTypeContainersSelector: '.BookingForm_a173c5a0 .guestTypeContainer',
    guestCountAssignedElementSubselector: '.guestCountAssignedElement',
            // ^^^ if such element found for a Guest Type, it replaces/overrides
            // 'guestCountTextInputSubselector' and 'guestCountSelectSubselector'
    guestCountTextInputSubselector: '.guestCountTextInput',
    guestCountSelectSubselector: '.guestCountSelect',
    guestCountLimitForSelectThreshold: 10, // may be omitted if 'useSingleSeatSelect'/'useGuestCountSelects'
                                           // combination is present in all Form Variants (and thus 'guestCountLimit'
                                           // fallback doesn't happen)
    singleSeatContainersSelector: '.BookingForm_a173c5a0 .singleSeatContainer',
    singleSeatGuestTypeSelectSubselector: '.guestTypeSelect',
    guestTypeInfos: {
      '117': { containerSelector: '.BookingForm_a173c5a0 .guestTypeContainer.gt117' },
      '121': { containerSelector: '.BookingForm_a173c5a0 .guestTypeContainer.gt121' }
    },
    upgradesSectionSelector: '.BookingForm_a173c5a0 .upgradesSection', // omitted in forms without upgrades
    allUpgradeContainersSelector: '.BookingForm_a173c5a0 .upgradeContainer', // omitted in forms without upgrades
    upgradeCountElementSubselector: '.upgradeCountElement', // omitted in forms without upgrades
    upgradeInfos: { // omitted in forms without upgrades
      '121': { containerSelector: '.BookingForm_a173c5a0 .upgradeContainer.u121' },
      '120': { containerSelector: '.BookingForm_a173c5a0 .upgradeContainer.u120' }
    },
    hotelSelectSelector: '.BookingForm_a173c5a0 .hotelSelect', // omitted in forms without accommodation
    hotelSelectSelectActivityOptionSubselector: '.selectActivityOption',
            // ^^^ not needed/used in forms without accommodation
            // may be omitted in legacy forms, then first enabled <option> is used
    hotelSelectLoadingOptionSubselector: '.loadingOption',
            // ^^^ not needed/used in forms without accommodation
            // may be omitted in legacy forms, then <option> is created
    roomTextInputSelector: '.BookingForm_a173c5a0 .roomTextInput', // not needed/used in forms without accommodation
    transportationRoutesSectionSelector: '.BookingForm_a173c5a0 .transportationRoutesSection', // not needed/used in forms without accommodation
    allTransportationRouteContainersSelector: '.BookingForm_a173c5a0 .transportationRouteContainer', // not needed/used in forms without accommodation
    allTransportationRouteRadiosSelector: '.BookingForm_a173c5a0 .transportationRouteRadio', // not needed/used in forms without accommodation
    groupInfos: {
      '1': {
        activitySelectContainerSelector: '.BookingForm_a173c5a0 .activitySelectContainer.g1',
        isTimeSelection: true, // may be omitted in forms which don't need/use getAndCheckActivityId()
        selectableActivityIds: [ '222', '777' ] // not needed/used if not 'selectActivityAfterDate'
      }
    },
    activityInfos: { // may be present in legacy forms (non-'selectActivityAfterDate') instead
                     // of 'formVariantInfos' and 'activityAccommodationInfos'
      '369': {
        useSingleSeatSelect: false, // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        useGuestCountSelects: false, // not needed/used if 'useSingleSeatSelect'
                                     // not used for Guest Types with 'guestCountAssignedElementSubselector' element
                                     // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        guestCountLimit: 30, // may be present in legacy forms instead of 'useSingleSeatSelect' and/or 'useGuestCountSelects'
        singleSeatContainerSelector: '.BookingForm_a173c5a0 .singleSeatContainer.a369', // may be omitted if 'useSingleSeatSelect' is false (specified or inferred)
        guestTypeIds: [ 117 ],
        upgradeIds: [ 120, 121 ], // omitted in forms without upgrades
        hasTransportationRoutes: true, // omitted in forms without accommodation
        isTransportationMandatory: false, // omitted in forms without accommodation
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: {
            '3': '.BookingForm_a173c5a0 .transportationRouteContainer.tr3'
          }
        }
      },
      '222': {
        useSingleSeatSelect: false, // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        useGuestCountSelects: true, // not needed/used if 'useSingleSeatSelect'
                                    // not used for Guest Types with 'guestCountAssignedElementSubselector' element
                                    // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        guestCountLimit: 15, // may be present in legacy forms instead of 'useSingleSeatSelect' and/or 'useGuestCountSelects'
        singleSeatContainerSelector: '.BookingForm_a173c5a0 .singleSeatContainer.a222', // may be omitted if 'useSingleSeatSelect' is false (specified or inferred)
        guestTypeIds: [ 117, 121 ],
        upgradeIds: [ ], // omitted in forms without upgrades
        hasTransportationRoutes: true, // omitted in forms without accommodation
        isTransportationMandatory: true, // omitted in forms without accommodation
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: {
            '3': '.BookingForm_a173c5a0 .transportationRouteContainer.tr3',
            '4': '.BookingForm_a173c5a0 .transportationRouteContainer.tr4'
          }
        }
      },
      '777': {
        useSingleSeatSelect: true, // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        useGuestCountSelects: false, // not needed/used if 'useSingleSeatSelect'
                                     // not used for Guest Types with 'guestCountAssignedElementSubselector' element
                                     // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        guestCountLimit: 1, // may be present in legacy forms instead of 'useSingleSeatSelect' and/or 'useGuestCountSelects'
        singleSeatContainerSelector: '.BookingForm_a173c5a0 .singleSeatContainer.a777', // may be omitted if 'useSingleSeatSelect' is false (specified or inferred)
        guestTypeIds: [ 117, 121 ],
        upgradeIds: [ ], // omitted in forms without upgrades
        hasTransportationRoutes: false, // omitted in forms without accommodation
        isTransportationMandatory: false, // omitted in forms without accommodation
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: { }
        }
      }
    },
    formVariantInfos: {
      'a369': {
        useSingleSeatSelect: false, // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        useGuestCountSelects: false, // not needed/used if 'useSingleSeatSelect'
                                     // not used for Guest Types with 'guestCountAssignedElementSubselector' element
                                     // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        guestCountLimit: 30, // may be present in legacy forms instead of 'useSingleSeatSelect' and/or 'useGuestCountSelects'
        singleSeatContainerSelector: '.BookingForm_a173c5a0 .singleSeatContainer.a369', // may be omitted if 'useSingleSeatSelect' is false (specified or inferred)
        guestTypeIds: [ 117 ],
        upgradeIds: [ 120, 121 ] // omitted in forms without upgrades
      },
      'g1': {
        useSingleSeatSelect: true, // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        useGuestCountSelects: false, // not needed/used if 'useSingleSeatSelect'
                                     // not used for Guest Types with 'guestCountAssignedElementSubselector' element
                                     // may be omitted in legacy forms, then inferred from 'guestCountLimit'
        guestCountLimit: 1, // may be present in legacy forms instead of 'useSingleSeatSelect' and/or 'useGuestCountSelects'
        singleSeatContainerSelector: '.BookingForm_a173c5a0 .singleSeatContainer.g1', // may be omitted if 'useSingleSeatSelect' is false (specified or inferred)
        guestTypeIds: [ 117, 121 ],
        upgradeIds: [ ] // omitted in forms without upgrades
      }
    },
    activityAccommodationInfos: { // not needed/used in forms without accommodation
      '369': {
        hasTransportationRoutes: true,
        isTransportationMandatory: false,
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: {
            '3': '.BookingForm_a173c5a0 .transportationRouteContainer.tr3'
          }
        }
      },
      '222': {
        hasTransportationRoutes: true,
        isTransportationMandatory: true,
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: {
            '3': '.BookingForm_a173c5a0 .transportationRouteContainer.tr3',
            '4': '.BookingForm_a173c5a0 .transportationRouteContainer.tr4'
          }
        }
      },
      '777': {
        hasTransportationRoutes: false,
        isTransportationMandatory: false,
        routeSelectionContextData: { // not needed/used if '!hasTransportationRoutes'
          routesContainerSelector: '.BookingForm_a173c5a0 .transportationRoutesSection',
          routeSelectorMap: { }
        }
      }
    },
    toggleUncertainAvailabilityWarningFn: // needed only with Activity-Guests-Date order
      function(show) {
        $(".BookingForm_a173c5a0 .uncertainAvailabilityWarningSection").toggle(show);
      }
  }
  */

  /**
   * Returns the currently selected Activity ID, if any, as a string value.
   * If no Activity is selected, returns a falsy value (0 or '').
   */
  function getActivityId(contextData)
  {
    var groupOrActivityCode = $(contextData.groupSelectSelector).val();
    if (/^g(\d+)$/.test(groupOrActivityCode))
    {
      var groupId = RegExp.$1;
      var groupInfo = contextData.groupInfos[groupId];
      if (!groupInfo) return 0;

      return $(groupInfo.activitySelectContainerSelector).find(contextData.activitySelectSubselector).val();
    }
    else if (/^a(\d+)$/.test(groupOrActivityCode))
    {
      var activityId = RegExp.$1;
      return activityId;
    }
    else
    {
      return 0;
    }
  }

  /**
   * Returns the currently selected Activity ID, if any, as a string value.
   * If no Activity is selected, shows an error message to the user, and
   * returns a falsy value (0 or '').
   */
  function getAndCheckActivityId(contextData)
  {
    var maybeActivityId = getActivityId(contextData);
    if (maybeActivityId)
    {
      return maybeActivityId;
    }

    var useTimeSelection = _useTimeSelection(contextData);
    alert('Please select ' + (useTimeSelection ? 'activity time' : 'activity'));
    return 0;
  }

  /**
   * Updates the form according to a value of the Activity Group select (which can be
   * an Activity Group or a stand-alone Activity).
   *
   * Switches to a relevant Form Variant (updates visible Guest and Upgrade controls
   * to those applicable to a selected Group or Activity). In an "Activity before Date"
   * form this is achieved by the applyActivity() call.
   *
   * In an "Activity after Date" form, if Form Variant was changes vs. previous selection,
   * resets the Date input and the Activity select, to make sure the available Activity
   * variants are loaded upon subsequent Date selection.
   *
   * In an "Activity before Date" form, doesn't reset anything, but calls applyActivity(),
   * which may trigger showing (or hiding) of the Uncertain Availability warning.
   */
  function applyGroup(contextData)
  {
    var groupOrActivityCode = $(contextData.groupSelectSelector).val();
    var maybeGroupInfo = null;
    if (/^g(\d+)$/.test(groupOrActivityCode))
    {
      var groupId = RegExp.$1;
      maybeGroupInfo = contextData.groupInfos[groupId];
      if (!maybeGroupInfo) return;
    }

    $(contextData.allActivitySelectContainersSelector).toggle(false);
    if (maybeGroupInfo)
    {
      $(maybeGroupInfo.activitySelectContainerSelector).toggle(true);
    }

    if (contextData.selectActivityAfterDate)
    {
      var maybeFormVariantInfoBox = _getFormVariantInfoBox(contextData);
      var maybeFormVariantInfo = (maybeFormVariantInfoBox ? maybeFormVariantInfoBox.info : undefined);
      var maybeFormVariantCode = (maybeFormVariantInfoBox ? maybeFormVariantInfoBox.code : undefined);

      if (contextData._selectedFormVariantCode !== maybeFormVariantCode)
      {
        // Group/Activity actually changed. Now we don't know if the selected Date (if any)
        // is available. So we reset Date and Selectable Activity (if any),
        // to be consistent with how it's done when Guests or Upgrades are changed.
        _resetDateAndSelectableActivity(contextData);
        contextData._selectedFormVariantCode = maybeFormVariantCode;
      }

      _toggleFormVariantGuestsUpgradesControls(contextData, maybeFormVariantInfo);
      applyActivity(contextData);
    }
    else
    {
      applyActivity(contextData);
    }
  }

  /**
   * Updates the form according to a value of the Activity select (which can be
   * replaced with a hidden field in an "Activity before Date" form, if there is
   * only one Activity to select).
   *
   * In an "Activity before Date" form, switches to a relevant Form Variant (updates
   * visible Guest and Upgrade controls to those applicable to a selected Activity).
   * May trigger showing (or hiding) of the Uncertain Availability warning.
   *
   * In a form with Accommodation, if Activity was changed vs. previous selection,
   * calls Accommodation.loadHotels() to fill the Hotel select. Before this call,
   * prepares the Hotel select and the Transportation Route controls as needed.
   * If no Activity is selected, empties the Hotel select and hides the Transportation
   * Route controls instead.
   */
  function applyActivity(contextData)
  {
    var activityId = getActivityId(contextData);
    var previousActivityId = contextData._currentActivityId;
    contextData._currentActivityId = activityId;

    if (!contextData.selectActivityAfterDate)
    {
      var maybeFormVariantInfo = _getFormVariantInfo(contextData);
      _toggleFormVariantGuestsUpgradesControls(contextData, maybeFormVariantInfo);
      _recalculateUncertainAvailabilityWarning(contextData);
    }

    if (contextData.hotelSelectSelector && contextData.supplierId
        // ^^^ supplierId is needed because Accommodation.loadHotels() won't work
        // without it.
        && activityId != previousActivityId
        // ^^^ Avoid re-initializing Hotel Select if Activity wasn't changed.
        // Note non-strict comparison: we want to tolerate String-Number mixing.
    )
    {
      // We're re-initializing Hotel Select and thus un-selecting Hotel,
      // so hide the Transportation Routes section. It will be shown (if appropriate)
      // once a Hotel is selected.
      $(contextData.transportationRoutesSectionSelector).toggle(false);

      // Accommodation.loadHotels() will eventually (once hotels are loaded) indirectly
      // call Accommodation.setupTransportationRoutes(), which only operates
      // on Transportation Routes applicable for the current Activity. Make sure
      // all other Routes are not left visible:
      $(contextData.allTransportationRouteContainersSelector).toggle(false);

      // Fill 'contextData.hotelSelectSelectActivityOptionSubselector', if not present:
      _ensureHotelSelectSelectActivityOptionSubselector(contextData);

      var $hotelSelect = $(contextData.hotelSelectSelector);

      if (activityId)
      {
        var activityAccommodationInfo = _getActivityAccommodationInfo(contextData, activityId);

        $hotelSelect.find(contextData.hotelSelectSelectActivityOptionSubselector)
            .prop('disabled', true).toggle(false);
        Accommodation.loadHotels({
          supplierId: contextData.supplierId,
          activityId: activityId,
          agencyId: contextData.agencyId,
          hotelSelectSelector: contextData.hotelSelectSelector,
          hotelSelectProtectedOptionsSubselector: contextData.hotelSelectSelectActivityOptionSubselector,
          hotelSelectLoadingOptionSubselector: contextData.hotelSelectLoadingOptionSubselector, // may be absent
          routeSelectionContextData: activityAccommodationInfo.hasTransportationRoutes ? activityAccommodationInfo.routeSelectionContextData : undefined
        });
      }
      else
      {
        var $selectActivityOption = $hotelSelect.find(contextData.hotelSelectSelectActivityOptionSubselector);
        var $loadingOption = $hotelSelect.find(contextData.hotelSelectLoadingOptionSubselector);

        $hotelSelect.find("option")
            .not($selectActivityOption).not($loadingOption)
            .remove();
        $loadingOption.prop('disabled', true).toggle(false);
        $selectActivityOption.prop('disabled', false).toggle(true);

        _resetSelectSelection($hotelSelect);
        $hotelSelect.prop('disabled', true);
      }
    }
  }

  /**
   * Updates the form according to values of the Guest controls.
   *
   * In an "Activity after Date" form, if Guests were changes vs. values saved
   * on Date selection, resets the Date input and the Activity select, to make sure
   * the available Activity variants are loaded upon subsequent Date selection.
   *
   * In an "Activity before Date" form, doesn't reset anything, but may trigger
   * showing (or hiding) of the Uncertain Availability warning.
   */
  function applyGuestCount(contextData)
  {
    var formVariantInfo = _getFormVariantInfo(contextData);
    if (!formVariantInfo) return;

    if (contextData._selectedDateInfo)
    {
      if (contextData.selectActivityAfterDate)
      {
        var guestsInfo = _collectGuests(contextData, formVariantInfo);

        var newGuestsAreTheSame = _guestsInfosEqual(contextData._selectedDateInfo.guestsInfo, guestsInfo);
        if (!newGuestsAreTheSame)
        {
          // Guests actually changed. Now we don't know if the selected Date
          // is available. Moreover, the set of available selectable Activities may
          // have changed in both directions. Since we don't want to query PonoRez
          // on each Guests or Upgrades change, we just reset Date and Selectable
          // Activity (if any), to force re-selection and re-query of available
          // selectable Activities.
          _resetDateAndSelectableActivity(contextData);
        }
      }
      else
      {
        _recalculateUncertainAvailabilityWarning(contextData);
      }
    }
  }

  /**
   * Updates the form according to values of the Upgrade controls.
   *
   * In an "Activity after Date" form, if Upgrades were changes vs. values saved
   * on Date selection, resets the Date input and the Activity select, to make sure
   * the available Activity variants are loaded upon subsequent Date selection.
   *
   * In an "Activity before Date" form, doesn't reset anything, but may trigger
   * showing (or hiding) of the Uncertain Availability warning.
   */
  function applyUpgradeCount(contextData)
  {
    var formVariantInfo = _getFormVariantInfo(contextData);
    if (!formVariantInfo) return;

    if (contextData._selectedDateInfo)
    {
      if (contextData.selectActivityAfterDate)
      {
        var upgradesInfo = _collectUpgrades(contextData, formVariantInfo);

        var newUpgradesAreTheSame = _upgradesInfosEqual(contextData._selectedDateInfo.upgradesInfo, upgradesInfo);
        if (!newUpgradesAreTheSame)
        {
          // Upgrades actually changed. Now we don't know if the selected Date
          // is available. Moreover, the set of available selectable Activities may
          // have changed in both directions. Since we don't want to query PonoRez
          // on each Guests or Upgrades change, we just reset Date and Selectable
          // Activity (if any), to force re-selection and re-query of available
          // selectable Activities.
          _resetDateAndSelectableActivity(contextData);
        }
      }
      else
      {
        _recalculateUncertainAvailabilityWarning(contextData);
      }
    }
  }

  /**
   * Updates the form according to values of the Date input.
   *
   * The Date input is assumed to contain a Date selected with an Availability Calendar
   * configured according to the current Activity/Activity Group and (for a
   * "Guests then Date" form) the current Guests and Upgrades - or an empty value.
   * That is, a non-empty Date is treated as available with this configuration.
   *
   * In an "Activity after Date" form, with the current Form Variant attributed to
   * an Activity Group: if Date is non-empty, calls _initiateSelectableActivityAvailabilityQueryIfNeeded(),
   * which initiates a PonoRez query to determine available Activities for the current
   * Activity Group and Guests/Upgrades and Date, unless they are already cached.
   * Also, calls _applySelectableActivityKnownAvailabilityStatus() to reconfigure
   * the Selectable Activity select according to the current state of affairs with
   * Activity availability and its queries.
   *
   * In an "Activity before Date" form, may trigger showing (or hiding) of
   * the Uncertain Availability warning.
   *
   * Also, saves certain components of the state of the form (Date itself,
   * Form Variant code, Guests and Upgrades) "as of Date selection" for future reference.
   */
  function applyDate(contextData)
  {
    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;
    var formVariantInfo = formVariantInfoBox.info;
    var formVariantCode = formVariantInfoBox.code;

    var date = $(contextData.dateTextInputSelector).val();
    var haveDate = (date != ''); // intentional non-strict comparison

    var guestsInfo = _collectGuests(contextData, formVariantInfo);
    var upgradesInfo = _collectUpgrades(contextData, formVariantInfo);

    if (haveDate)
    {
      contextData._selectedDateInfo = {
        date: date,
        formVariantCode: formVariantCode,
        guestsInfo: guestsInfo,
        upgradesInfo: upgradesInfo
      };
    }
    else
    {
      contextData._selectedDateInfo = undefined;
    }

    if (contextData.selectActivityAfterDate && formVariantInfoBox.maybeGroupId)
    {
      if (haveDate)
      {
        _initiateSelectableActivityAvailabilityQueryIfNeeded(contextData, formVariantCode, formVariantInfoBox.maybeGroupId, guestsInfo, upgradesInfo, date);
      }
      _applySelectableActivityKnownAvailabilityStatus(contextData);
    }

    if (!contextData.selectActivityAfterDate)
    {
      _setUncertainAvailabilityWarning(contextData, false);
    }
  }

  /**
   * Configures the Transportation Route select, according to the currently selected Activity.
   *
   * This function is intended to be called when the Hotel select is updated.
   */
  function setupTransportationRoutes(contextData)
  {
    var activityId = getActivityId(contextData);
    if (!activityId) return;
    var activityAccommodationInfo = _getActivityAccommodationInfo(contextData, activityId);

    var hotelId = $(contextData.hotelSelectSelector).val();

    $(contextData.transportationRoutesSectionSelector).toggle(activityAccommodationInfo.hasTransportationRoutes || false);
    if (activityAccommodationInfo.hasTransportationRoutes && contextData.supplierId)
        // ^^^ supplierId is needed because Accommodation.setupTransportationRoutes()
        // won't work without it.
    {
      // Accommodation.setupTransportationRoutes() only operates on Transportation Routes
      // applicable for the current Activity. Make sure all other Routes are not left visible:
      $(contextData.allTransportationRouteContainersSelector).toggle(false);

      Accommodation.setupTransportationRoutes({
        supplierId: contextData.supplierId,
        activityId: activityId,
        agencyId: contextData.agencyId,
        hotelId: hotelId,
        routeSelectionContextData: activityAccommodationInfo.routeSelectionContextData
      });
    }
  }

  /**
   * Shows Availability Calendar, configured according to the active Form Variant and
   * entered Guests and Upgrades.
   *
   * options: { anchorId }
   * anchorId: <id or element or $element>
   *
   * In an "Activity after Date" form, if the active Form Variant is attributed to
   * an Activity Group and a Selectable Activity is already selected, first asks
   * the user whether to select among available dates for the specific Activity/Time
   * or for the entire group.
   *
   * This function is intended to be called when the Date input or its "calendar" image
   * is clicked.
   */
  function showGuestsDependentAvailabilityCalendar(contextData, options)
  {
    if (!(options && typeof(options) === 'object')) options = { };
    var anchorId = options.anchorId || undefined;

    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;

    var activityIds;
    if (formVariantInfoBox.maybeActivityId)
    {
      activityIds = [ formVariantInfoBox.maybeActivityId ];
    }
    else if (formVariantInfoBox.maybeGroupId)
    {
      // Note that we can only get here if 'selectActivityAfterDate'.

      var maybeActivityIdForCalendar = undefined;
      //==//
      {
        var maybeSelectedActivityId = getActivityId(contextData);
        if (maybeSelectedActivityId)
        {
          var timeOrActivityString = (_useTimeSelection(contextData) ? 'activity time' : 'activity');
          var confirmationText =
              'You already selected a specific ' + timeOrActivityString
              + '. Do you want to choose only among dates that have this ' + timeOrActivityString
              + ' available?';
          if (confirm(confirmationText))
          {
            maybeActivityIdForCalendar = maybeSelectedActivityId;
          }
        }
      }

      activityIds = (maybeActivityIdForCalendar
          ? [ maybeActivityIdForCalendar ]
          : contextData.groupInfos[formVariantInfoBox.maybeGroupId].selectableActivityIds);
    }
    else
    {
      return;
    }

    var agencyId = contextData.agencyId || 0;

    var guestsInfo = _collectGuests(contextData, formVariantInfoBox.info);
    var upgradesInfo = _collectUpgrades(contextData, formVariantInfoBox.info);
    var maybeMinAvailability = _maybePonorezMinAvailability(guestsInfo, upgradesInfo);
    // If no (valid) Guests/Upgrades specified, just show a simple availability calendar.

    global.showAvailabilityCalendar2(
        activityIds, $(contextData.dateTextInputSelector),
        {
          local: false, anchorId: anchorId,
          agencyId: agencyId, minavailability: maybeMinAvailability, webBooking: true
        }
    );
  }

  /**
   * Adds entered Guests to External API's Reservation order specification.
   *
   * If the current Form Variant is single-Guest (_useSingleSeatSelect() returns true),
   * calls global.addGuests() (from External API) for the selected Guest Type.
   *
   * If the current Form Variant is not single-Guest, calls global.addGuests() for
   * each Guest Type specified for the current Form Variant, passing raw value
   * from the corresponding Guest Type's control. (This way, invalid values
   * are passed, too, allowing the further checkDateAndGuestsAndUpgrades()
   * or availability_XXX() call to perform full validation.)
   *
   * Returns true if Guests were passed to External API. Returns a falsy value
   * on failure (for example, if no Activity is selected).
   */
  function addGuests(contextData)
  {
    var formVariantInfo = _getFormVariantInfo(contextData);
    if (!formVariantInfo) return;

    var guestsInfo = _collectGuests(contextData, formVariantInfo);
    $.each(guestsInfo.guestsRawArray, function() {
      global.addGuests(this.guestTypeId, this.guestCountRaw);
    });

    return true;
  }

  /**
   * Adds entered Upgrades to External API's Reservation order specification.
   *
   * Calls global.addUpgrades() (from External API) for each Upgrade specified
   * for the current Form Variant, passing raw value from the corresponding
   * Upgrade's control. (This way, invalid values are passed, too, allowing
   * the further checkDateAndGuestsAndUpgrades() or availability_XXX() call
   * to perform full validation.)
   *
   * Returns true if Upgrades were passed to External API. Returns a falsy value
   * on failure (for example, if no Activity is selected).
   */
  function addUpgrades(contextData)
  {
    var formVariantInfo = _getFormVariantInfo(contextData);
    if (!formVariantInfo) return;

    var upgradesInfo = _collectUpgrades(contextData, formVariantInfo);
    $.each(upgradesInfo.upgradesRawArray, function() {
      global.addUpgrades(this.upgradeId, this.upgradeCountRaw);
    });

    return true;
  }

  /**
   * Sets Accommodation information in External API's Reservation order specification.
   *
   * Calls global.setHotel() and global.setRoom() (from External API) with the selected
   * Hotel and the entered Room. If a Transportation Route is selected, also calls
   * global.setTransportationRoute() (from External API) with the selected Route.
   *
   * If no Hotel is selected, this is interpreted as an indication that the Accommodation
   * module did not successfully load the list of Hotels from PonoRez; in this case,
   * an alert with the error message is shown, and the calls listed above are not made.
   *
   * If the selected Activity has mandatory Transportation ('isTransportationMandatory == true'),
   * and no Transportation Route is selected, an alert with the error message is shown,
   * and the calls listed above are not made.
   *
   * Returns true if Accommodation information was passed to External API.
   * Returns false (or another falsy value) if one of the error situations described
   * above or a different failure (for example, no Activity is selected) happened.
   */
  function setAccommodation(contextData)
  {
    var activityId = getActivityId(contextData);
    if (!activityId) return;
    var activityAccommodationInfo = _getActivityAccommodationInfo(contextData, activityId);

    if (!(contextData.hotelSelectSelector /*&& contextData.roomTextInputSelector*/))
    {
      console.warn("setAccommodation(): 'hotelSelectSelector' is not present in form context data");
      return false;
    }

    var hotelId = parseInt($(contextData.hotelSelectSelector).val());
    var room = $(contextData.roomTextInputSelector).val();
    if (isNaN(hotelId))
    {
      alert('Please wait until hotels are loaded, or reload the page and start again');
      return false;
    }

    var transportationRouteId = parseInt($(contextData.allTransportationRouteRadiosSelector).filter(':visible:checked').val());
    if (activityAccommodationInfo.isTransportationMandatory && isNaN(transportationRouteId))
    {
      alert('Please select a Transportation Route');
      return false;
    }

    global.setHotel(hotelId);
    global.setRoom(room);

    if (!isNaN(transportationRouteId))
    {
      global.setTransportationRoute(transportationRouteId);
    }

    return true;
  }

  /**
   * Returns an Info Box of the currently active Form Variant.
   *
   * return: { info: formVariantInfo, code: formVariantCode, maybeGroupId, maybeActivityId }
   * formVariantInfo: taken from 'contextData.formVariantInfos', or generated
   *                  from 'contextData.activityInfos'
   * formVariantCode: 'gXXX' or 'aXXX'
   * maybeGroupId: <groupId> (string) if Form Variant is attributed to an Activity Group,
   *               undefined otherwise
   * maybeActivityId: <activityId> (string) if Form Variant is attributed to an Activity,
   *                  undefined otherwise
   *
   * For an "Activity before Date" form, returns an "Info Box" of the Form Variant
   * attributed to the currently selected Activity, if any. If no Activity is selected,
   * returns undefined.
   *
   * For an "Activity after Date" form, returns an "Info Box" of the Form Variant
   * attributed to whatever is currently selected in the Activity Group select, if any.
   * (It could be an Activity Group or a stand-alone Activity.) If nothing is selected
   * in Activity Group select, returns undefined.
   *
   * Remarks:
   *
   * "Form Variant" is, informally, one of the fixed configurations of the form,
   * tailored to booking of a specific Activity or Activity Group. A Form Variant
   * determines (for the most part (*)) composition of visible form controls and their
   * behaviour. Note that, even though Form Variant (mostly) determines "what a
   * user sees", it doesn't (always) determine a selected Activity.
   *
   * (*) Certain aspects, such as inclusion of specific Selectable Activities or
   * visibility of specific Transportation Routes, are controlled by data that is
   * dynamically received from PonoRez. Also, for an "Activity after Date" form,
   * available Hotels and Transportation Routes may be determined based on a Selectable
   * Activity selection which is performed within a given Form Variant (attributed
   * to an Activity Group).
   *
   * A Form Variant is associated with: "code", which is a string in the form 'g<groupId>'
   * or 'a<activityId>' and a key in 'contextData.formVariantInfos'; "info", which
   * is normally (*) a value from 'contextData.formVariantInfos' and describes composition
   * and behaviour of Guest and Upgrade controls; either Group ID or Activity ID,
   * which determine attribution of the Form Variant.
   *
   * (*) In legacy forms ("Activity before Date" only), 'contextData.formVariantInfos'
   * may not exist; in this case, a Form Variant Info object is generated from
   * the value from 'contextData.activityInfos'.
   *
  */
  function _getFormVariantInfoBox(contextData)
  {
    var groupOrActivityCode = $(contextData.groupSelectSelector).val();

    var formVariantCode = groupOrActivityCode;
    var maybeGroupId = undefined;
    var maybeActivityId = undefined;

    if (/^g(\d+)$/.test(groupOrActivityCode))
    {
      var groupId = RegExp.$1;
      var groupInfo = contextData.groupInfos[groupId];
      if (!groupInfo) return undefined;

      if (contextData.selectActivityAfterDate)
      {
        maybeGroupId = groupId;
      }
      else
      {
        var activityId = $(groupInfo.activitySelectContainerSelector).find(contextData.activitySelectSubselector).val();
        formVariantCode = 'a' + activityId;
        maybeActivityId = activityId;
      }
    }
    else if (/^a(\d+)$/.test(formVariantCode))
    {
      var activityId = RegExp.$1;
      maybeActivityId = activityId;
    }
    else
    {
      return undefined;
    }

    var formVariantInfo = undefined;
    if (contextData.formVariantInfos)
    {
      if (contextData.formVariantInfos[formVariantCode])
      {
        formVariantInfo = contextData.formVariantInfos[formVariantCode];
      }
    }
    else if (contextData.activityInfos && maybeActivityId)
    {
      // Legacy context data format compatibility.
      if (contextData.activityInfos[maybeActivityId])
      {
        var legacyActivityInfo = contextData.activityInfos[maybeActivityId];
        formVariantInfo = $.extend({}, legacyActivityInfo, {
          upgradeIds: legacyActivityInfo.upgradeIds || [ ]
        });
      }
    }

    if (!formVariantInfo) return undefined;

    return {
      info: formVariantInfo, code: formVariantCode,
      maybeGroupId: maybeGroupId, maybeActivityId: maybeActivityId
    };
  }

  /**
   * Returns a Form Variant Info object of the currently active Form Variant.
   *
   * If no Form Variant is currently active, returns undefined.
   *
   * See _getFormVariantInfoBox() for more information.
   */
  function _getFormVariantInfo(contextData)
  {
    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    return (formVariantInfoBox ? formVariantInfoBox.info : undefined);
  }

  /**
   * Returns the Selectable Activity select (as a jQuery set) of the specified
   * Form Variant, if it's attributed to an Activity Group
   *
   * If the specified Form Variant is not attributed to an Activity Group,
   * returns undefined.
   *
   * This function is for an "Activity after Date" form.
   */
  function _getFormVariantActivitySelectSet(contextData, formVariantInfoBox)
  {
    if (!contextData.selectActivityAfterDate) return;

    if (!formVariantInfoBox.maybeGroupId) return undefined;
    var groupInfo = contextData.groupInfos[formVariantInfoBox.maybeGroupId];

    return $(groupInfo.activitySelectContainerSelector).find(contextData.activitySelectSubselector);
  }

  /**
   * Returns the "Time Selection" flag of the active Form Variant
   *
   * If the active Form Variant is attributed to an Activity Group (and thus has
   * a Selectable Activity select), returns Group's 'isTimeSelection' value
   * (which indicates whether Group's Selectable Activities are Times).
   *
   * Otherwise (if the active Form Variant is not attributed to an Activity Group, or
   * there is no active Form Variant at all), returns false.
   *
   * This function is for an "Activity after Date" form.
   */
  function _useTimeSelection(contextData)
  {
    if (!contextData.selectActivityAfterDate) return;

    var groupOrActivityCode = $(contextData.groupSelectSelector).val();
    if (/^g(\d+)$/.test(groupOrActivityCode))
    {
      var groupId = RegExp.$1;
      var groupInfo = contextData.groupInfos[groupId];
      if (groupInfo)
      {
        return !!groupInfo.isTimeSelection;
      }
    }

    return false;
  }

  /**
   * Returns an Accommodation Info object of the specified Activity.
   *
   * Normally, an Accommodation Info object is a value from
   * 'contextData.activityAccommodationInfos'. In legacy forms,
   * 'contextData.activityAccommodationInfos' may not exist; in this case,
   * an Accommodation Info object is generated from the value from
   * 'contextData.activityInfos'.
   *
   * This function always supposed to return a sensible Accommodation Info object,
   * even if the passed Activity ID is empty or invalid - as long as the structure
   * of Context Data is valid (within modern or legacy standard).
   */
  function _getActivityAccommodationInfo(contextData, activityId)
  {
    if (contextData.activityAccommodationInfos)
    {
      if (contextData.activityAccommodationInfos[activityId])
      {
        return contextData.activityAccommodationInfos[activityId];
      }
    }
    else if (contextData.activityInfos)
    {
      var legacyActivityInfo = contextData.activityInfos[activityId];
      if (legacyActivityInfo)
      {
        return {
          hasTransportationRoutes: legacyActivityInfo.hasTransportationRoutes || false,
          isTransportationMandatory: legacyActivityInfo.isTransportationMandatory || false,
          routeSelectionContextData: legacyActivityInfo.routeSelectionContextData || {
            routesContainerSelector: null,
            routeSelectorMap: { }
          }
        };
      }
    }

    return {
      hasTransportationRoutes: false,
      isTransportationMandatory: false
    };
  }

  /**
   * Returns a Guests Info object for the current Guests input of the specified Form Variant
   *
   * return: { guestsRawArray, guestsRaw, guestsParsedArray, guestsParsed, haveValidGuests }
   * guestsRawArray: [ { guestTypeId, guestCountRaw }, ... ]
   * guestsRaw: { guestTypeId: guestCountRaw, ... }
   * guestsParsedArray: [ { guestTypeId, guestCountParsed }, ... ]
   * guestsParsed: { guestTypeId: guestCountParsed, ... }
   * guestTypeId: integer or string
   * guestCountRaw: string
   * guestCountParsed: integer
   * haveValidGuests: boolean
   *
   * Collects input from Guest controls, according to the specified Form Variant Info, and
   * returns a Guests Info object with the collected data. The "Raw" fields contain
   * unparsed, unfiltered input from all relevant Guest controls, suitable for validation.
   * The "Parsed" fields contain parsed input from relevant Guest controls that have
   * valid and non-zero values. 'haveValidGuests' is a convenience field that specifies
   * whether any Guest was correctly specified at all.
   */
  function _collectGuests(contextData, formVariantInfo)
  {
    var result = { };

    result.guestsRawArray = [ ];
    result.guestsRaw = { };
    result.guestsParsedArray = [ ];
    result.guestsParsed = { };
    result.haveValidGuests = false;
    if (_useSingleSeatSelect(contextData, formVariantInfo))
    {
      var selectedGuestTypeId = $(formVariantInfo.singleSeatContainerSelector)
          .find(contextData.singleSeatGuestTypeSelectSubselector).val();

      result.guestsRawArray.push({ guestTypeId: selectedGuestTypeId, guestCountRaw: '1' });
      result.guestsRaw[selectedGuestTypeId] = '1';
      result.guestsParsedArray.push({ guestTypeId: selectedGuestTypeId, guestCountParsed: 1 });
      result.guestsParsed[selectedGuestTypeId] = 1;
      result.haveValidGuests = true;
    }
    else
    {
      var useSelectsIfNoAssigned = _useGuestCountSelects(contextData, formVariantInfo);
      var guestCountElementSubselectorIfNoAssigned =
          (useSelectsIfNoAssigned ? contextData.guestCountSelectSubselector : contextData.guestCountTextInputSubselector);

      $.each(formVariantInfo.guestTypeIds, function(i, guestTypeId) {
        var guestTypeInfo = contextData.guestTypeInfos['' + guestTypeId];
        if (!guestTypeInfo) return true; // continue

        var $guestCountAssignedElementOrEmpty = $();
        if (contextData.guestCountAssignedElementSubselector)
        {
          $guestCountAssignedElementOrEmpty = $(guestTypeInfo.containerSelector).find(contextData.guestCountAssignedElementSubselector);
        }

        var guestCountRaw = ($guestCountAssignedElementOrEmpty.length
                ? $guestCountAssignedElementOrEmpty.val()
                : $(guestTypeInfo.containerSelector).find(guestCountElementSubselectorIfNoAssigned).val()
        );
        result.guestsRawArray.push({ guestTypeId: guestTypeId, guestCountRaw: guestCountRaw });
        result.guestsRaw[guestTypeId] = guestCountRaw;

        var guestCountParsed = parseInt(guestCountRaw);
        if (!isNaN(guestCountParsed) && guestCountParsed > 0)
        {
          result.guestsParsedArray.push({ guestTypeId: guestTypeId, guestCountParsed: guestCountParsed });
          result.guestsParsed[guestTypeId] = guestCountParsed;
          result.haveValidGuests = true;
        }
      });
    }

    return result;
  }

  /**
   * Returns an Upgrades Info object for the current Upgrades input of the specified Form Variant
   *
   * return: { upgradesRawArray, upgradesRaw, upgradesParsedArray, upgradesParsed, haveValidUpgrades }
   * upgradesRawArray: [ { upgradeId, upgradeCountRaw }, ... ]
   * upgradesRaw: { upgradesId: upgradeCountRaw, ... }
   * upgradesParsedArray: [ { upgradeId, upgradeCountParsed }, ... ]
   * upgradesParsed: { upgradeId: upgradeCountParsed, ... }
   * upgradeId: integer or string
   * upgradeCountRaw: string
   * upgradeCountParsed: integer
   * haveValidUpgrades: boolean
   *
   * Collects input from Upgrade controls, according to the specified Form Variant Info, and
   * returns an Upgrades Info object with the collected data. The "Raw" fields contain
   * unparsed, unfiltered input from all relevant Upgrade controls, suitable for validation.
   * The "Parsed" fields contain parsed input from relevant Upgrade controls that have
   * valid and non-zero values. 'haveValidUpgrades' is a convenience field that specifies
   * whether any Upgrade was correctly specified at all.
   */
  function _collectUpgrades(contextData, formVariantInfo)
  {
    var result = { };

    result.upgradesRawArray = [ ];
    result.upgradesRaw = { };
    result.upgradesParsedArray = [ ];
    result.upgradesParsed = { };
    result.haveValidUpgrades = false;
    $.each(formVariantInfo.upgradeIds, function(i, upgradeId) {
      var upgradeInfo = contextData.upgradeInfos['' + upgradeId];
      if (!upgradeInfo) return true; // continue

      var upgradeCountRaw = $(upgradeInfo.containerSelector).find(contextData.upgradeCountElementSubselector).val();
      result.upgradesRawArray.push({ upgradeId: upgradeId, upgradeCountRaw: upgradeCountRaw });
      result.upgradesRaw[upgradeId] = upgradeCountRaw;

      var upgradeCountParsed = parseInt(upgradeCountRaw);
      if (!isNaN(upgradeCountParsed) && upgradeCountParsed > 0)
      {
        result.upgradesParsedArray.push({ upgradeId: upgradeId, upgradeCountParsed: upgradeCountParsed });
        result.upgradesParsed[upgradeId] = upgradeCountParsed;
        result.haveValidUpgrades = true;
      }
    });

    return result;
  }

  /**
   * Returns the Min Availability object (for PonoRez query) for the specified Guests Info
   * and Upgrades Info
   *
   * return: { guests, upgrades }
   * guests: { guestTypeId: guestCount }
   * upgrades: { upgradeId: upgradeCount }
   *
   * The returned object is intended to be passed to PonoRez's availability query
   * (companyservlet?action=COMMON_AVAILABILITYCHECKJSON).
   *
   * If both the specified Guests Info and Upgrades Info are empty (no valid Guests
   * and no valid Upgrades), returns undefined instead.
   */
  function _maybePonorezMinAvailability(guestsInfo, upgradesInfo)
  {
    var guests = { };
    $.each(guestsInfo.guestsParsedArray, function() {
      guests[this.guestTypeId] = this.guestCountParsed;
    });

    var upgrades = { };
    $.each(upgradesInfo.upgradesParsedArray, function() {
      upgrades[this.upgradeId] = this.upgradeCountParsed;
    });

    if (guestsInfo.haveValidGuests || upgradesInfo.haveValidUpgrades)
    {
      return { guests: guests, upgrades: upgrades };
    }
    else
    {
      return undefined;
    }
  }

  /**
   * Shows Guests/Upgrades controls of the active Form Variant, and hides all other
   * said controls.
   */
  function _toggleFormVariantGuestsUpgradesControls(contextData, maybeFormVariantInfo)
  {
    // Old code compatibility:
    contextData.upgradeInfos ||= { };

    $(contextData.allGuestTypeContainersSelector).toggle(false);
    $(contextData.singleSeatContainersSelector).toggle(false);

    if (maybeFormVariantInfo)
    {
      var formVariantInfo = maybeFormVariantInfo;
      if (_useSingleSeatSelect(contextData, formVariantInfo))
      {
        $(formVariantInfo.singleSeatContainerSelector).toggle(true);
      }
      else
      {
        var useSelects = _useGuestCountSelects(contextData, formVariantInfo);

        $.each(formVariantInfo.guestTypeIds, function(i, guestTypeId) {
          var guestTypeInfo = contextData.guestTypeInfos['' + guestTypeId];
          if (!guestTypeInfo) return true; // continue

          $(guestTypeInfo.containerSelector).toggle(true);
        });

        $(contextData.allGuestTypeContainersSelector).find(contextData.guestCountTextInputSubselector)
            .toggle(!useSelects);
        $(contextData.allGuestTypeContainersSelector).find(contextData.guestCountSelectSubselector)
            .toggle(useSelects);

        // Note that we don't do anything special for Guest Types which have "guest count
        // assigned elements" present. Such Guest Types are not supposed to have standard
        // "text input" or "select" elements, in which case they won't be affected by what
        // we're doing here.
      }
    }

    $(contextData.allUpgradeContainersSelector).toggle(false);

    var haveVisibleUpgrades = false;
    if (maybeFormVariantInfo)
    {
      $.each(maybeFormVariantInfo.upgradeIds, function(i, upgradeId) {
        var upgradeInfo = contextData.upgradeInfos['' + upgradeId];
        if (!upgradeInfo) return true; // continue

        $(upgradeInfo.containerSelector).toggle(true);
        haveVisibleUpgrades = true;
      });
    }

    $(contextData.upgradesSectionSelector).toggle(haveVisibleUpgrades);
  }

  /**
   * Updates Selectable Activity select of the currently selected Group,
   * according to Known Availability Info that we currently have
   *
   * Selectable Activity select is set up in one of the following configurations:
   *
   * 1. "Full Set" - all Selectable Activities are shown, plus the neutral "Select"
   * entry. Used when no Date is selected, or when a Date was selected, but
   * Known Availability is not loaded and no availability queries were initiated yet.
   *
   * 2. "Full Set" (queries failed) - all Selectable Activities are shown, plus the
   * neutral "Select" entry. Used when a Date was selected, Known Availability
   * is not loaded, no availability queries are currently running, and a prior
   * availability query failed. After this configuration is set up, the
   * Selectable Activity select is reset to the neutral entry, and the previously
   * saved Selectable Activity (if any) is forgotten.
   *
   * 3. "Active" - Selectable Activities available for the currently entered
   * Guests/Upgrades and Date are shown, plus the neutral "Select" entry.
   * Used when a Date was selected and Known Availability was loaded. After this
   * configuration is set up, a previously saved Selectable Activity is
   * selected (if it was saved for this Form Variant).
   *
   * 4. "Loading" - no Selectable Activities are shown, just the neutral "Loading"
   * entry. Used when a Date was selected, Known Availability is not loaded,
   * and an availability query is currently running. Before this configuration is
   * set up, the currently selected Selectable Activity (if any) is saved for future
   * restoration.
   *
   * This function is for an "Activity after Date" form.
   */
  function _applySelectableActivityKnownAvailabilityStatus(contextData)
  {
    if (!contextData.selectActivityAfterDate) return;

    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;
    var formVariantInfo = formVariantInfoBox.info;

    var date = $(contextData.dateTextInputSelector).val();
    var haveDate = (date != ''); // intentional non-strict comparison

    if (formVariantInfoBox.maybeGroupId)
    {
      var knownAvailabilityInfo;
      if (haveDate)
      {
        var guestsInfo = _collectGuests(contextData, formVariantInfo);
        var upgradesInfo = _collectUpgrades(contextData, formVariantInfo);
        knownAvailabilityInfo = _knownAvailabilityInfo(contextData, formVariantInfoBox.code, guestsInfo, upgradesInfo, date);
      }
      else
      {
        knownAvailabilityInfo = { }; // for safety
      }

      if (!haveDate)
      {
        _setupSelectableActivitySelect(contextData, formVariantInfoBox, ActivitySelectMode.FullSet);
      }
      else if (knownAvailabilityInfo.loaded)
      {
        _setupSelectableActivitySelect(contextData, formVariantInfoBox, ActivitySelectMode.Active, knownAvailabilityInfo.availableActivityIdSet);
        _restoreSelectableActivity(contextData, knownAvailabilityInfo.availableActivityIdSet);
      }
      else if (knownAvailabilityInfo.loading)
      {
        _saveSelectableActivity(contextData);
        _setupSelectableActivitySelect(contextData, formVariantInfoBox, ActivitySelectMode.Loading);
      }
      else
      {
        // Either loading failed, or it didn't start yet. In any case, allow
        // all Activities in this situation.
        _setupSelectableActivitySelect(contextData, formVariantInfoBox, ActivitySelectMode.FullSet);
        if (knownAvailabilityInfo.loadingFailed)
        {
          _resetAndForgetSelectableActivity(contextData);
        }
      }
    }
  }

/**
 * Sets up the Selectable Activity select of the specified Form Variant with the specified
 * Activity Select Mode and (for the "Active" mode) the specified Activity Id Set.
 *
 * This is a technical function that manipulates the Selectable Activity select according
 * to specified parameters.
 *
 * This function is for an "Activity after Date" form.
 */
  function _setupSelectableActivitySelect(contextData, formVariantInfoBox, activitySelectMode, activeActivityIdSet)
  {
    activeActivityIdSet = activeActivityIdSet || { };

    var $activitySelect = _getFormVariantActivitySelectSet(contextData, formVariantInfoBox);
    if (!$activitySelect) return;
    var selectedActivityId = $activitySelect.val();

    var filterActivityIdFn;
    var neutralOptionSubsubselector;
    switch (activitySelectMode)
    {
      case ActivitySelectMode.Loading:
        filterActivityIdFn = function(activityId) { return false; };
        neutralOptionSubsubselector = contextData.activitySelectLoadingOptionSubsubselector;
        break;
      case ActivitySelectMode.Active:
        filterActivityIdFn = function(activityId) { return activeActivityIdSet.hasOwnProperty(activityId); };
        neutralOptionSubsubselector = contextData.activitySelectSelectOptionSubsubselector;
        break;
      case ActivitySelectMode.FullSet:
      default:
        filterActivityIdFn = function(activityId) { return true; };
        neutralOptionSubsubselector = contextData.activitySelectSelectOptionSubsubselector;
        break;
    }

    $activitySelect.find("option[value='']").prop('disabled', true).toggle(false);
    $activitySelect.find(neutralOptionSubsubselector).prop('disabled', false).toggle(true);

    $activitySelect.find("option[value!='']").each(function() {
      var thisActivityIsAvailable = filterActivityIdFn($(this).val());
      $(this).prop('disabled', !thisActivityIsAvailable).toggle(thisActivityIsAvailable);
    });

    if (!selectedActivityId)
    {
      // We may have switched neutral options, make sure the correct one
      // is shown.
      _resetSelectSelection($activitySelect);
    }
    else if (!filterActivityIdFn(selectedActivityId))
    {
      // The previously selected Activity ID is no longer available, change to
      // a default (neutral) option.
      _resetSelectSelection($activitySelect);
      applyActivity(contextData);
    }
  }

  /**
   * Saves the currently selected Selectable Activity ID (if any) for future restoration
   * by _restoreSelectableActivity()
   *
   * The active Form Variant Code is saved together with the Activity ID in
   * 'contextData._savedSelectableActivitySelectionInfo'.
   *
   * If no Selectable Activity is currently selected, the previously saved
   * Selectable Activity Selection Info (if any) is *not* overwritten.
   *
   * This function is for an "Activity after Date" form.
   */
  function _saveSelectableActivity(contextData)
  {
    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;
    var $activitySelect = _getFormVariantActivitySelectSet(contextData, formVariantInfoBox);
    if (!$activitySelect) return;
    var selectedActivityId = $activitySelect.val();
    if (!selectedActivityId) return;

    contextData._savedSelectableActivitySelectionInfo = {
      formVariantCode: formVariantInfoBox.code,
      selectedActivityId: selectedActivityId
    };
  }

  /**
   * Restores the Selectable Activity ID (if any) previously saved by
   * _saveSelectableActivity()
   *
   * If the saved Form Variant Code differs from the active Form Variant Code,
   * the saved Selectable Activity is not restored.
   *
   * In any case, the saved Selectable Activity Selection Info is forgotten,
   * so it won't have a chance to be restored in the future.
   *
   * This function is for an "Activity after Date" form.
   */
  function _restoreSelectableActivity(contextData)
  {
    var savedSelectableActivitySelectionInfo = contextData._savedSelectableActivitySelectionInfo;
    if (!savedSelectableActivitySelectionInfo) return;
    contextData._savedSelectableActivitySelectionInfo = undefined;

    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;
    var $activitySelect = _getFormVariantActivitySelectSet(contextData, formVariantInfoBox);
    if (!$activitySelect) return;

    if (savedSelectableActivitySelectionInfo.formVariantCode !== formVariantInfoBox.code) return;
    var savedSelectedActivityId = savedSelectableActivitySelectionInfo.selectedActivityId;

    if ($activitySelect.find("option[value='"+savedSelectedActivityId+"']").is(":enabled"))
    {
      $activitySelect.val(savedSelectedActivityId);
      applyActivity(contextData);
    }
  }

  /**
   * Resets the Selectable Activity select to a neutral entry and forgets the
   * previously saved Selectable Activity Selection Info (if any)
   *
   * After Selectable Activity is reset, calls applyActivity() to update Accommodation
   * controls (if applicable).
   *
   * This function is for an "Activity after Date" form.
   */
  function _resetAndForgetSelectableActivity(contextData)
  {
    if (!contextData.selectActivityAfterDate) return;

    contextData._savedSelectableActivitySelectionInfo = undefined;

    var formVariantInfoBox = _getFormVariantInfoBox(contextData);
    if (!formVariantInfoBox) return;
    var $activitySelect = _getFormVariantActivitySelectSet(contextData, formVariantInfoBox);
    if (!$activitySelect) return;

    _resetSelectSelection($activitySelect);
    applyActivity(contextData);
  }

  /**
   * Resets both the selected Date and the selected Selectable Activity
   *
   * Calls _resetAndForgetSelectableActivity() to do the Selectable Activity part.
   *
   * After Date is reset, calls applyDate().
   *
   * This function is intended to be called when either selected Group is switched
   * or entered Guests/Upgrades are changed, so that existing Date selection and
   * Selectable Activity select setup, which are only relevant for old Group/Guests/Upgrades,
   * do not linger with new Group/Guests/Upgrades.
   *
   * This function is for an "Activity after Date" form.
   */
  function _resetDateAndSelectableActivity(contextData)
  {
    if (!contextData.selectActivityAfterDate) return;

    _resetAndForgetSelectableActivity(contextData);

    $(contextData.dateTextInputSelector).val('');
    applyDate(contextData);
  }

  /**
   * Initiates an availability query against PonoRez for the specified Form Variant/Group,
   * Guests/Upgrades and Date, if necessary and allowed, and handles the outcome
   *
   * If availability was already loaded for the combination of passed Form Variant,
   * Guests/Upgrades and Date, or a new query is not allowed (because a maximal number
   * of queries in Month Availability Info exceeds '_AVAILABILITY_QUERY_MAX_COUNT'),
   * returns. Calls _cleanupRunningAvailabilityQueries() before counting queries,
   * to ensure that outdated queries are not counted.
   *
   * Creates a Running Query Info and saves it into Month Availability Info for accounting,
   * and then initiates an availability query against PonoRez.
   *
   * If and when the query completes successfully and our Running Query Info was not
   * removed, saves the query results into Month Availability Info, removes our
   * Running Query Info, and calls _applySelectableActivityKnownAvailabilityStatus()
   * to reflect a possible status change.
   *
   * If and when the query fails and our Running Query Info was not removed,
   * records failure in Month Availability Info, removes our Running Query Info, and calls
   * _applySelectableActivityKnownAvailabilityStatus() to reflect a possible status change.
   *
   * This function is for an "Activity after Date" form.
   */
  function _initiateSelectableActivityAvailabilityQueryIfNeeded(contextData, formVariantCode, groupId, guestsInfo, upgradesInfo, date)
  {
    // 'date' must be non-empty.
    // 'groupId' must be non-empty and refer to a known Group Info.

    var knownAvailabilityInfo = _knownAvailabilityInfo(contextData, formVariantCode, guestsInfo, upgradesInfo, date);
    if (knownAvailabilityInfo.loaded)
    {
      return;
    }

    var groupInfo = contextData.groupInfos[groupId];
    var monthAvailabilityInfo = knownAvailabilityInfo.monthAvailabilityInfo;

    _cleanupRunningAvailabilityQueries(contextData, monthAvailabilityInfo);
    if (monthAvailabilityInfo.runningQueryInfos.length >= _AVAILABILITY_QUERY_MAX_COUNT)
    {
      return;
    }

    var queryResultMonthDataExDeferred = $.Deferred();
    var ourRunningQueryInfo = {
      startDate: new Date(),
      queryResultMonthDataExDeferred: queryResultMonthDataExDeferred
    };

    // Wait for completion no more than 30 seconds:
    window.setTimeout(function() { queryResultMonthDataExDeferred.reject(); }, _AVAILABILITY_QUERY_TIMEOUT_MS);

    var dateObj = new Date(date);
    var year_month = '' + dateObj.getFullYear() + '_' + (1 + dateObj.getMonth());
    var maybeMinAvailability = _maybePonorezMinAvailability(guestsInfo, upgradesInfo);
    var minAvailabilityStrOrEmpty = (maybeMinAvailability ? JSON.stringify(maybeMinAvailability) : '');
    // If no (valid) Guests/Upgrades specified, check for "default" availability.

    monthAvailabilityInfo.runningQueryInfos.push(ourRunningQueryInfo);

    $.ajax({
      url: _baseUrl + 'companyservlet?action=COMMON_AVAILABILITYCHECKJSON',
      dataType: 'jsonp',
      data: {
        wantExtendedResults: true,
        activityid: groupInfo.selectableActivityIds.join('|'),
        agencyid: contextData.agencyId || 0,
        blocksonly: false,
        year_months: year_month,
        checkAutoAttached: true,
        webbooking: true,
        hawaiifunbooking: false,
        agencybooking: false,
        minavailability: minAvailabilityStrOrEmpty
      }
    })
    .done(function(response) {
      var monthDataEx = response['yearmonth_' + year_month + '_ex'];
      if (monthDataEx && typeof(monthDataEx) === 'object')
      {
        queryResultMonthDataExDeferred.resolve(monthDataEx);
      }
      else
      {
        queryResultMonthDataExDeferred.reject();
      }
    })
    .fail(function() {
      queryResultMonthDataExDeferred.reject();
    });

    queryResultMonthDataExDeferred.done(function(monthDataEx) {
      var ourRunningQueryIndex = monthAvailabilityInfo.runningQueryInfos.indexOf(ourRunningQueryInfo);
      if (ourRunningQueryIndex === -1) return;

      monthAvailabilityInfo.queryResultMonthDataEx = monthDataEx;

      monthAvailabilityInfo.runningQueryInfos.splice(ourRunningQueryIndex, 1);
      _applySelectableActivityKnownAvailabilityStatus(contextData);
    });
    queryResultMonthDataExDeferred.fail(function() {
      var ourRunningQueryIndex = monthAvailabilityInfo.runningQueryInfos.indexOf(ourRunningQueryInfo);
      if (ourRunningQueryIndex === -1) return;

      monthAvailabilityInfo.loadingFailed = true;

      monthAvailabilityInfo.runningQueryInfos.splice(ourRunningQueryIndex, 1);
      _applySelectableActivityKnownAvailabilityStatus(contextData);
    });
  }

  /**
   * Removes outdated Running Query Infos from the specified Month Availability Info
   *
   * Finds Running Query Infos of the specified Month Availability Info that are older
   * than '_AVAILABILITY_QUERY_TIMEOUT_MS', rejects their Deferred's and removes them
   * from Month Availability Info.
   *
   * If any Running Query Info is found and removed, calls _applySelectableActivityKnownAvailabilityStatus()
   * (indirectly during rejection, and/or directly).
   *
   * Remarks:
   *
   * Outdated Running Queries are supposed to be rejected and removed automatically
   * by a timed call. This function serves as a backup, for possible faulty scenarios
   * where automatic removal doesn't work.
   *
   * This function is for an "Activity after Date" form.
   */
  function _cleanupRunningAvailabilityQueries(contextData, monthAvailabilityInfo)
  {
    var runningQueryInfos = monthAvailabilityInfo.runningQueryInfos;
    var outdatedThreshold = new Date(new Date().getTime() - _AVAILABILITY_QUERY_TIMEOUT_MS);

    var queryInfosToRemove = $.grep(runningQueryInfos, function(rqi) { return (rqi.startDate < outdatedThreshold); });

    $.each(queryInfosToRemove, function() { this.queryResultMonthDataExDeferred.reject(); });

    // The .reject() calls above are supposed to remove all outdated Query Infos
    // from 'runningQueryInfos'. We also do manual removal in case something goes
    // wrong.

    var manuallyRemovedQueryInfos = false;
    for (var i = runningQueryInfos.length - 1; i >= 0; i--)
    // ^^^ Reverse iteration avoids interference with element removal.
    {
      if ($.inArray(runningQueryInfos[i], queryInfosToRemove) >= 0)
      {
        runningQueryInfos.splice(i, 1);
        manuallyRemovedQueryInfos = true;
      }
    }

    if (manuallyRemovedQueryInfos)
    {
      console.warn('Unexpected condition: Running Query Info is still in the array after Deferred.reject()');
      _applySelectableActivityKnownAvailabilityStatus(contextData);
    }
  }

  /**
   * Returns a Known Availability Info object for the specified Form Variant with the
   * specified Guests/Upgrades and Date
   *
   * return: { monthAvailabilityInfo, loaded, availableActivityIdSet, loading, loadingFailed }
   * monthAvailabilityInfo: a Month Availability Info object (see below)
   * loaded: true if availability was loaded, a falsy value otherwise
   * availableActivityIdSet: { <activityId>: true, ... }
   *                         a "set" of available Activity IDs, if availability was loaded;
   *                         undefined otherwise
   * loading: true if no availability was loaded and an availability query is currently running,
   *          a falsy value otherwise
   * loadingFailed: true if no availability was loaded, no availability queries are currently
   *                running and a prior query failed; a falsy value otherwise
   *
   * monthAvailabilityInfo: { runningQueryInfos, queryResultMonthDataEx, loadingFailed }
   * runningQueryInfos: [ runningQueryInfo, ... ]
   * runningQueryInfo: a Running Query Info object, see below
   * queryResultMonthDataEx: { d1: { aids: [ <activityId>, ... ] }, ... }
   *                         month's "extended" availability data from the result of
   *                         the last successful query, if any; a falsy value otherwise
   * loadingFailed: true if any availability query failed or timed out, a falsy value otherwise
   *                (contrast with monthAvailabilityInfo.loadingFailed which is reset by
   *                any running or successfully completed query)
   *
   * runningQueryInfo: { startDate, queryResultMonthDataExDeferred }
   * startDate: a Date object
   * queryResultMonthDataExDeferred: a Deferred object that will receive the query outcome
   *
   * Remarks:
   *
   * A Known Availability Info object is mainly a "front-end" for data consumers.
   * The actual handling of availability data and availability queries is performed
   * with Month Availability Info objects (which are referenced from Known Availability Info
   * object) on monthly bases.
   *
   * This function maintains the cache of Known Availability Infos and Month Availability Infos
   * and their link; contents of Month Availability Infos is managed/filled by
   * _initiateSelectableActivityAvailabilityQueryIfNeeded(), and contents of
   * Known Availability Infos is derived from their Month Availability Infos
   * by _updateKnownAvailabilityInfoFromMonthInfo().
   *
   * This function is for an "Activity after Date" form.
   */
  function _knownAvailabilityInfo(contextData, formVariantCode, guestsInfo, upgradesInfo, date)
  {
    // 'date' must be non-empty.

    var variantGuestsUpgradesCode = formVariantCode
        + '|' + JSON.stringify(guestsInfo.guestsParsedArray)
        + '|' + JSON.stringify(upgradesInfo.upgradesParsedArray);

    var dateObj = new Date(date);
    var monthCode = '' + dateObj.getFullYear() + '-' + dateObj.getMonth();

    if (!contextData._availabilityCacheNodeByVariantGuestsUpgradesCode)
    {
      contextData._availabilityCacheNodeByVariantGuestsUpgradesCode = { };
    }
    var availabilityCacheNodeByVariantGuestsUpgradesCode = contextData._availabilityCacheNodeByVariantGuestsUpgradesCode;

    if (!availabilityCacheNodeByVariantGuestsUpgradesCode[variantGuestsUpgradesCode])
    {
      availabilityCacheNodeByVariantGuestsUpgradesCode[variantGuestsUpgradesCode] = {
        monthAvailabilityInfoByMonthCode: { },
        knownAvailabilityInfoByDate: { }
      };
    }
    var availabilityCacheNode = availabilityCacheNodeByVariantGuestsUpgradesCode[variantGuestsUpgradesCode];

    if (!availabilityCacheNode.monthAvailabilityInfoByMonthCode[monthCode])
    {
      availabilityCacheNode.monthAvailabilityInfoByMonthCode[monthCode] = {
        runningQueryInfos: [ ],
        queryResultMonthDataEx: undefined
      };
    }
    var monthAvailabilityInfo = availabilityCacheNode.monthAvailabilityInfoByMonthCode[monthCode];

    if (!availabilityCacheNode.knownAvailabilityInfoByDate[date])
    {
      availabilityCacheNode.knownAvailabilityInfoByDate[date] = {
        monthAvailabilityInfo: monthAvailabilityInfo
      };
    }
    var knownAvailabilityInfo = availabilityCacheNode.knownAvailabilityInfoByDate[date];

    if (!knownAvailabilityInfo.loaded)
    {
      _updateKnownAvailabilityInfoFromMonthInfo(knownAvailabilityInfo, dateObj);
    }

    return knownAvailabilityInfo;
  }

  /**
   * Updates Known Availability Info from its parent Month Availability Info
   *
   * If 'knownAvailabilityInfo.loaded', does nothing.
   *
   * If not 'knownAvailabilityInfo.loaded', fully resets Known Availability Info
   * according to the current state of 'knownAvailabilityInfo.monthAvailabilityInfo',
   * using the passed Date object (to find specific day's data within month's data).
   *
   * This function is for an "Activity after Date" form.
   */
  function _updateKnownAvailabilityInfoFromMonthInfo(knownAvailabilityInfo, dateObj)
  {
    if (knownAvailabilityInfo.loaded) return;

    knownAvailabilityInfo.loading = false;
    knownAvailabilityInfo.loadingFailed = false;

    var monthAvailabilityInfo = knownAvailabilityInfo.monthAvailabilityInfo;
    if (monthAvailabilityInfo.queryResultMonthDataEx)
    {
      var dayDataEx = monthAvailabilityInfo.queryResultMonthDataEx['d' + dateObj.getDate()];

      knownAvailabilityInfo.availableActivityIdSet = { };
      if (dayDataEx && dayDataEx.aids)
      {
        $.each(dayDataEx.aids, function() { knownAvailabilityInfo.availableActivityIdSet[this] = true; });
      }
      knownAvailabilityInfo.loaded = true;
    }
    else if (monthAvailabilityInfo.runningQueryInfos.length)
    {
      knownAvailabilityInfo.loading = true;
    }
    else if (monthAvailabilityInfo.loadingFailed)
    {
      knownAvailabilityInfo.loadingFailed = true;
    }
  }

  /**
   * Shows or hides the Uncertain Availability warning, depending on differences between
   * current Form Variant and Guests/Upgrades and those saved on date selection
   *
   * If there is an active Form Variant and a Date is selected, compares active
   * Form Variant Code and currently entered Guests/Upgrades with those saved in
   * 'contextData._selectedDateInfo'. If Form Variant is different, or Form Variant
   * is the same but saved Guests/Upgrades don't fully cover currently entered
   * Guests/Upgrades, calls _setUncertainAvailabilityWarning() to show the
   * Uncertain Availability warning.
   *
   * In other cases (no active Form Variant, or no Date is selected, or '_selectedDateInfo'
   * matches the active Form Variant and fully covers currently entered Guests/Upgrades),
   * calls _setUncertainAvailabilityWarning() to hide the Uncertain Availability warning.
   *
   * This function is for an "Activity before Date" form.
   *
   * This function is only relevant to the "Activity-Guests-Date" order. It may (and will)
   * be called with the "Activity-Date-Guests" order, but it won't have any effect since
   * forms with this order don't have an Uncertain Availability warning.
   */
  function _recalculateUncertainAvailabilityWarning(contextData)
  {
    if (contextData.selectActivityAfterDate) return;

    var maybeFormVariantInfoBox = _getFormVariantInfoBox(contextData);
    var selectedDateInfo = contextData._selectedDateInfo;
    if (!maybeFormVariantInfoBox || !selectedDateInfo)
    {
      // No Form Variant or Date selected, nothing to be uncertain of.
      _setUncertainAvailabilityWarning(contextData, false);
      return;
    }

    var formVariantInfo = maybeFormVariantInfoBox.info;
    var formVariantCode = maybeFormVariantInfoBox.code;

    if (selectedDateInfo.formVariantCode !== formVariantCode)
    {
      // Form Variant (Group+Activity) was changed since Date was selected.
      // So availability is uncertain.
      _setUncertainAvailabilityWarning(contextData, true);
      return;
    }

    var guestsParsedOfSelectedDate = selectedDateInfo.guestsInfo.guestsParsed;
    var guestsInfo = _collectGuests(contextData, formVariantInfo);
    for (var i = 0; i < guestsInfo.guestsParsedArray.length; i++)
    {
      var guestInfo = guestsInfo.guestsParsedArray[i];
      var guestCountOfSelectedDate = guestsParsedOfSelectedDate[guestInfo.guestTypeId] || 0;
      if (guestInfo.guestCountParsed > guestCountOfSelectedDate)
      {
        // For this Guest Type, currently specified guest count is greater
        // than it was when Date was selected. So availability is uncertain.
        _setUncertainAvailabilityWarning(contextData, true);
        return;
      }
    }

    var upgradesParsedOfSelectedDate = selectedDateInfo.upgradesInfo.upgradesParsed;
    var upgradesInfo = _collectUpgrades(contextData, formVariantInfo);
    for (var i = 0; i < upgradesInfo.upgradesParsedArray.length; i++)
    {
      var upgradeInfo = upgradesInfo.upgradesParsedArray[i];
      var upgradeCountOfSelectedDate = upgradesParsedOfSelectedDate[upgradeInfo.upgradeId] || 0;
      if (upgradeInfo.upgradeCountParsed > upgradeCountOfSelectedDate)
      {
        // For this Upgrade, currently specified upgrade count is greater
        // than it was when Date was selected. So availability is uncertain.
        _setUncertainAvailabilityWarning(contextData, true);
        return;
      }
    }

    _setUncertainAvailabilityWarning(contextData, false);
  }

  /**
   * Shows or hides the Uncertain Availability warning
   *
   * Calls 'contextData.toggleUncertainAvailabilityWarningFn' (if any) with the passed
   * 'active' parameter, to show or hide the Uncertain Availability warning, unless
   * the new 'active' value is the same as the previously saved value.
   *
   * Saves the 'active' value for future comparisons.
   *
   * This function is for an "Activity before Date" form.
   *
   * This function is only relevant to the "Activity-Guests-Date" order. It may (and will)
   * be called with the "Activity-Date-Guests" order, but it won't have any effect since
   * forms with this order don't have an Uncertain Availability warning.
   */
  function _setUncertainAvailabilityWarning(contextData, active)
  {
    if (contextData.selectActivityAfterDate) return;

    active = !!active;
    var previouslyActive = !!contextData._uncertainAvailabilityWarningActive;

    contextData._uncertainAvailabilityWarningActive = active;

    if (active !== previouslyActive
        && typeof(contextData.toggleUncertainAvailabilityWarningFn) === 'function')
    {
      contextData.toggleUncertainAvailabilityWarningFn(active);
    }
  }

  /**
   * Checks whether two passed Guests Info objects describe the same Guests set
   *
   * Compares parsed Guest counts in 'guestsInfo1' and 'guestsInfo2'. Returns true
   * if parsed Guest counts are the same in these Guests Infos for each Guest Type ID.
   * Returns false otherwise.
   *
   * Remarks:
   *
   * Note that only parsed Guest counts are taken into account. If a Guests Info
   * has Guest Types with invalid counts (only present in raw data), such Guest Types
   * do not count (they are treated as zero-number/non-existent).
   */
  function _guestsInfosEqual(guestsInfo1, guestsInfo2)
  {
    // Check 1:
    if (guestsInfo1.guestsParsedArray.length !== guestsInfo2.guestsParsedArray.length)
    {
      // Number of non-zero Guest Types is different => no equality.
      return false;
    }

    var guestsParsedArray1 = guestsInfo1.guestsParsedArray;
    var guestsParsed2 = guestsInfo2.guestsParsed;

    // Check 2:
    for (var i = 0; i < guestsParsedArray1.length; i++)
    {
      var guestInfo1 = guestsParsedArray1[i];
      var guestCount2 = guestsParsed2[guestsInfo1.guestTypeId] || 0;

      if (guestInfo1.guestCountParsed !== guestCount2)
      {
        return false;
      }
    }

    // The following sets don't intersect and constitute full coverage of Guest Type set:
    // GTzz = ( gt | guests1[gt] == 0 && guests2[gt] == 0 )
    // GTzn = ( gt | guests1[gt] == 0 && guests2[gt] != 0 )
    // GTnz = ( gt | guests1[gt] != 0 && guests2[gt] == 0 )
    // GTnn = ( gt | guests1[gt] != 0 && guests2[gt] != 0 )
    //
    // Check 1 means that count(GTnz + GTnn) == count(GTzn + GTnn),
    // thus count(GTnz) == count(GTzn). However, GTnz is empty because each such
    // Guest Type would fail Check 2. So GTzn is empty, too.
    //
    // So, since both GTzn and GTnz are empty, if guests1[gt] != guests2[gt],
    // gt must be in GTnn, but such Guest Type would, again, fail Check 2.
    // So guests1[gt] != guests2[gt] is impossible.

    return true;
  }

  /**
   * Checks whether two passed Upgrades Info objects describe the same Upgrades set
   *
   * Compares parsed Upgrade counts in 'upgradesInfo1' and 'upgradesInfo2'. Returns true
   * if parsed Upgrade counts are the same in these Upgrade Infos for each Upgrade ID.
   * Returns false otherwise.
   *
   * Remarks:
   *
   * Note that only parsed Upgrade counts are taken into account. If an Upgrades Info
   * has Upgrades with invalid counts (only present in raw data), such Upgrades
   * do not count (they are treated as zero-number/non-existent).
   */
  function _upgradesInfosEqual(upgradesInfo1, upgradesInfo2)
  {
    // Check 1:
    if (upgradesInfo1.upgradesParsedArray.length !== upgradesInfo2.upgradesParsedArray.length)
    {
      // Number of non-zero Upgrades is different => no equality.
      return false;
    }

    var upgradesParsedArray1 = upgradesInfo1.upgradesParsedArray;
    var upgradesParsed2 = upgradesInfo2.upgradesParsed;

    // Check 2:
    for (var i = 0; i < upgradesParsedArray1.length; i++)
    {
      var upgradeInfo1 = upgradesParsedArray1[i];
      var upgradeCount2 = upgradesParsed2[upgradesInfo1.upgradeId] || 0;

      if (upgradeInfo1.upgradeCountParsed !== upgradeCount2)
      {
        return false;
      }
    }

    // The logic is the same as in _guestsInfosEqual().
    return true;
  }

  /**
   * Returns the 'useSingleSeatSelect' property of the specified Form Variant if it's
   * directly specified, or infers it from Form Variant's 'guestCountLimit'.
   *
   * If the passed Form Variant Info object contains 'useSingleSeatSelect', returns it.
   * Otherwise, returns true if Form Variant's 'guestCountLimit' is at most 1, and
   * false otherwise.
   */
  function _useSingleSeatSelect(contextData, formVariantInfo)
  {
    if (formVariantInfo.useSingleSeatSelect !== undefined)
    {
      return formVariantInfo.useSingleSeatSelect;
    }
    else
    {
      return (formVariantInfo.guestCountLimit <= 1);
    }
  }

  /**
   * Returns the 'useGuestCountSelects' property of the specified Form Variant if it's
   * directly specified, or infers it from Form Variant's 'guestCountLimit'.
   *
   * If the passed Form Variant Info object contains 'useGuestCountSelects', returns it.
   *
   * Otherwise, if Form Variant's 'useSingleSeatSelect' is present and true, returns true.
   *
   * Otherwise, returns true if Form Variant's 'guestCountLimit' is at most
   * 'guestCountLimitForSelectThreshold' from Contact Data, and false otherwise.
   *
   * Remarks:
   *
   * This function is not supposed to be used for Form Variants for which
   * _useSingleSeatSelect() returns true. If it is called nevertheless, it tries
   * to return a sensible value anyway.
   */
  function _useGuestCountSelects(contextData, formVariantInfo)
  {
    if (formVariantInfo.useGuestCountSelects !== undefined)
    {
      return formVariantInfo.useGuestCountSelects;
    }
    else if (formVariantInfo.useSingleSeatSelect)
    {
      return true;
    }
    else
    {
      return (formVariantInfo.guestCountLimit <= contextData.guestCountLimitForSelectThreshold);
    }
  }

  /**
   * Fills 'contextData.hotelSelectSelectActivityOptionSubselector', if it is not present
   *
   * If 'contextData.hotelSelectSelectActivityOptionSubselector' is not a truthy value,
   * tries to find the first enabled neutral (value='') <option> in Hotel select.
   * If it's found, adds the 'selectActivityOption' CSS class to it, and sets
   * 'hotelSelectSelectActivityOptionSubselector' accordingly. If it's not found,
   * sets 'hotelSelectSelectActivityOptionSubselector' to [], so that using this as
   * a selector yielded a guaranteed empty set, but this function considered it as present.
   *
   * Remarks:
   *
   * 'hotelSelectSelectActivityOptionSubselector' is not specified in legacy forms, but
   * our code now manipulates the "Select Activity" neutral <option>, so it's convenient
   * to always have a sub-selector for it.
   */
  function _ensureHotelSelectSelectActivityOptionSubselector(contextData)
  {
    if (contextData.hotelSelectSelectActivityOptionSubselector) return;

    var $hotelSelect = $(contextData.hotelSelectSelector);
    var $maybeSelectActivityOption = $hotelSelect.find("option[value='']:enabled:first");
    if ($maybeSelectActivityOption.length)
    {
      $maybeSelectActivityOption.addClass('selectActivityOption');
      contextData.hotelSelectSelectActivityOptionSubselector = ".selectActivityOption";
    }
    else
    {
      // No neutral option found. So use a non-false kind-of-selector that
      // yields an empty set.
      contextData.hotelSelectSelectActivityOptionSubselector = [];
    }
  }

  function _resetSelectSelection($select)
  {
    $select.find("option:selected").prop('selected', false);
    // The first call (above) deselect the selected option, if any. If something was deselected,
    // it also selects the first visible+enabled option. However, it's possible that
    // nothing was deselected because, for example, the previously selected option
    // was removed; for this case, we explicitly select the first visible+enabled option
    // with the second call (below).
    $select.find("option:enabled:first")
        .filter(function() { return !$(this).prop('disabled'); })
        .prop('selected', true);
  }

  /**
   * Returns non-production PonoRez's application (servlet context) URL, found by scanning
   * DOM for <script> elements referencing this file
   *
   * Scans the DOM for <script> elements referencing this file (external/bookingsupport-1.js).
   * If such an element is found, returns the base part of its source URL (before 'external',
   * and including the trailing '/'). Considers only URLs with single-word hostnames
   * or URLs containing 'reservation_test', with the intent to skip URLs referencing PonoRez
   * "production" instance.
   *
   * If no relevant <script> element is found, returns null.
   *
   * Remarks:
   *
   * The result of this function is used to access resources from the non-production PonoRez
   * instance that this file (presumably) belongs to. When this function returns a falsy value,
   * a production PonoRez instance is assumed, and then the hard-coded base URL is used.
   */
  function _detectNonProductionBaseUrl()
  {
    var nonProductionBaseUrl = null;

    var thisScriptName = 'external/bookingsupport-1'; // regexp-friendly
    $($("script[src*='/"+thisScriptName+".js']").get().reverse()).each(function() {
      // Note reverse iteration: we only want the last matching <script>.
      if (new RegExp('^(.*\\/)'+thisScriptName+'\\.js(?:\\?|$)').test($(this).attr('src')))
      {
        var scriptBaseUrl = RegExp.$1;
        if (/^https?:\/\/[a-z][a-z0-9]*(?:-[a-z0-9]+)*[:\/]/i.test(scriptBaseUrl) // single-word hostname: development environment
            || /reservation_test/.test(scriptBaseUrl)) // reservation_test: test environment
        {
          nonProductionBaseUrl = scriptBaseUrl;
        }

        return false; // break iteration
      }
    });

    return nonProductionBaseUrl;
  }

  return {
    getActivityId: getActivityId,
    getAndCheckActivityId: getAndCheckActivityId,
    applyGroup: applyGroup,
    applyActivity: applyActivity,
    applyGuestCount: applyGuestCount,
    applyUpgradeCount: applyUpgradeCount,
    applyDate: applyDate,
    setupTransportationRoutes: setupTransportationRoutes,
    showGuestsDependentAvailabilityCalendar: showGuestsDependentAvailabilityCalendar,
    addGuests: addGuests,
    addUpgrades: addUpgrades,
    setAccommodation: setAccommodation
  };

})(this);
