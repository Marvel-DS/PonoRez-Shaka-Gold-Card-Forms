function external_urlEncode(clearString)
{
  if (typeof clearString == 'undefined') return '';
  var output = '';
  var x = 0;
  clearString = clearString.toString();
  var regex = /(^[a-zA-Z0-9_.]*)/;
  while (x < clearString.length)
  {
    var match = regex.exec(clearString.substr(x));
    if (match != null && match.length > 1 && match[1] != '')
    {
      output += match[1];
      x += match[1].length;
    }
    else
    {
      if (clearString[x] == ' ' || clearString.charCodeAt(x).toString(16).toUpperCase() == '20' || clearString.charCodeAt(x).toString(16).toUpperCase() == 'A0')
      {
        output += '+';
      }
      else
      {
        var charCode = clearString.charCodeAt(x);
        var hexVal = charCode.toString(16);
        output += '%' + ( hexVal.length < 2 ? '0' : '' ) + hexVal.toUpperCase();
      }
      x++;
    }
  }
  return output;
}

var external_escapeForJsStringMap = { '\'': '\\x27', '"': '\\x22', '\\': '\\x5c', '<': '\\x3c', '>': '\\x3e', '\r': '\\r', '\n': '\\n' };
// ^^^ We prefer character code escapes to "just prepend backslash" escapes, the former
// are much safer in other contexts.
// '<' and '>' are escaped to neutralize things like '</script>'.

function external_escapeForJsString(clearString)
{
  if (clearString === undefined || clearString === null) return '';
  clearString += ''; // stringify
  return clearString.replaceAll(/['"\\<>\r\n]/g, function(c) { return external_escapeForJsStringMap[c] });
}

function external_trim(str)
{
  if (typeof(str) != 'string') return str;
  return str.replace(/^\s+|\s+$/g, '');
}

function external_fixGoogleTrackerUrl(url)
{
  return url.replace(/\|/g, '%7C');
}

function checkpositivenumber(x)
{
	var anum=/^\d+$/;
	return anum.test(x);
}

function checkdate()
{
	if (document.getElementById('date').value.length == 0)
	{
		alert('Please select activity date');
		return false;
	}

	return true;
}

function checkupgrade(requested, toolate, limited, available, name)
{
  requested = external_trim(requested);
	if (!checkpositivenumber(requested))
	{
		alert('Please enter valid upgrade amount(s)');
		return false;
	}
  if (requested != 0 && toolate)
  {
    alert('Sorry, it\'s too late to book ' + name);
    return false;
  }
	if (limited && parseInt(requested) > available)
	{
		alert('Sorry, only ' + available + ' ' + name + '(s) available');
		return false;
	}
	return true;
}

var googleTagManagerSessionId = '';
var ismobileapp = false;
var isIframe = false;
var cancellationPolicyConfirmed = false;
var query = '';
var reservationmode = '';
var activitydate = '';
var gtagTagId = '';
var gtagDebugMode = false;
var googleanalyticsaccount = '';
var guestCountMap;
var mode2Setup;
var upgradeCountMap;
// Note: "saved" added to emphasize difference with "goldcardnumber" parameter.
// All of this needs to be encapsulated better.
var savedGoldcardNumbers;
var savedBuyGoldcards;
// Deprecated but still supported:
var seats1;
var seats2;
var seats3;
var seats4;
var seats5;

// Works only after the upgrades page.
function setMobileApp()
{
  ismobileapp = true;
}

function setIframe()
{
  isIframe = true;
}
function setGoogleTagManagerSession(sessionId)
{
  console.log('writing gatm: ' + sessionId);
  googleTagManagerSessionId = sessionId.replace("_ga=","");
  query += googleTagManagerSessionId != '' ? '&_ga=' + googleTagManagerSessionId : '';

}
// These two functions can be called before reservation_init():

function checkcancellation(cancellationpolicy)
{
  cancellationPolicyConfirmed = false;

  if (!cancellationpolicy.checked)
  {
    alert('You must check the box acknowledging reading & accepting our Cancellation Policy');
    return false;
  }

  cancellationPolicyConfirmed = true;
	return true;
}

function setCancellationPolicyConfirmed()
{
  cancellationPolicyConfirmed = true;
}

function reservation(dummy_referrerid, activityid, date, discountname, discountpercents)
{
  reservation_init(dummy_referrerid, activityid, date, discountname, discountpercents, 0.0, discountpercents, 0.0, discountpercents, 0.0, discountpercents, 0.0, discountpercents, 0.0, discountpercents, discountpercents, window.location.href);
}

function reservation2(dummy_referrerid, activityid, date, discountname, discountpercentage1, discountvalue1, discountpercentage2, discountvalue2, discountpercentage3, discountvalue3, discountpercentage4, discountvalue4, discountpercentage5, discountvalue5, discountpercentagetransportation, discountpercentageupgrades)
{
  reservation_init(dummy_referrerid, activityid, date, discountname, discountpercentage1, discountvalue1, discountpercentage2, discountvalue2, discountpercentage3, discountvalue3, discountpercentage4, discountvalue4, discountpercentage5, discountvalue5, discountpercentagetransportation, discountpercentageupgrades, window.location.href)
}

function reservation_init(dummy_referrerid, activityid, date, discountname, discountpercentage1, discountvalue1, discountpercentage2, discountvalue2, discountpercentage3, discountvalue3, discountpercentage4, discountvalue4, discountpercentage5, discountvalue5, discountpercentagetransportation, discountpercentageupgrades, referer)
{
  query =
    '&referer=' + external_urlEncode(referer) +
    '&activityid=' + external_urlEncode(activityid) +
      (date != undefined ? '&date=' + external_urlEncode(date) : '');
  reservationmode = '';
  activitydate = external_trim(date);
  gtagTagId = '';
  gtagDebugMode = false;
  googleanalyticsaccount = '';
  guestCountMap = {};
  mode2Setup = false;
  upgradeCountMap = {};
  savedGoldcardNumbers = [];
  savedBuyGoldcards = false;
  // Deprecated:
  seats1 = 0;
  seats2 = 0;
  seats3 = 0;
  seats4 = 0;
  seats5 = 0;
}

function setGiftCertificate()
{
  reservationmode = 'giftcertificate';
}

function addGuests(guestTypeId, guestCount)
{
  if (!mode2Setup)
  {
    query = query + '&externalpurchasemode=2';
    mode2Setup = true;
  }
  if (guestCount == 0)
  {
    return;
  }
  query = query + '&guests_t' + guestTypeId + '=' + external_urlEncode(external_trim(guestCount));
  guestCountMap[guestTypeId] = external_trim(guestCount);
}

function addUpgrades(upgradeId, upgradeCount)
{
  if (upgradeCount == 0) return;

  query = query + '&upgrades_u' + upgradeId + '=' + external_urlEncode(external_trim(upgradeCount));
  upgradeCountMap[upgradeId] = external_trim(upgradeCount);
}

function setUpgradesFixed()
{
  query = query + "&upgradesfixed=1";
}

function setHotel(hotelId)
{
  query = query + "&hotelid=" + external_urlEncode(hotelId);
}

function setRoom(room)
{
  query = query + "&room=" + external_urlEncode(external_trim(room));
}

function setTransportationRoute(transportationRouteId)
{
  query = query + "&transportationrouteid=" + external_urlEncode(transportationRouteId);
}

function setAccommodationFixed()
{
  query = query + "&accommodationfixed=1";
}

function setFirstName(firstName)
{
  query = query + "&firstname=" + external_urlEncode(external_trim(firstName));
}

function setLastName(lastName)
{
  query = query + "&lastname=" + external_urlEncode(external_trim(lastName));
}

function setEmail(email)
{
  query = query + "&email=" + external_urlEncode(external_trim(email));
}

function setdiscount(discountcode)
{
  query = query + '&discountcode=' + external_urlEncode(discountcode);
}

function setagency(agencyid)
{
  query = query + '&agencyid=' + agencyid;
}

function setgoldcard(goldcardnumber)
{
  setgoldcards(goldcardnumber);
}

function setgoldcards(goldcardnumbers)
{
  var goldcardNumbersArray;
  if (typeof(goldcardnumbers) === 'string')
  {
    goldcardnumbers = external_trim(goldcardnumbers);
    goldcardNumbersArray = (goldcardnumbers != '' ? goldcardnumbers.split(/\s*,\s*/) : []);
  }
  else if (typeof(goldcardnumbers) === 'object' && typeof(goldcardnumbers.length) === 'number')
  {
    // It may be an array-like object, sanitize it to be safe.
    goldcardNumbersArray = [];
    for (var i = 0; i < goldcardnumbers.length; ++i)
    {
      goldcardNumbersArray.push(goldcardnumbers[i]);
    }
  }
  else
  {
    goldcardNumbersArray = [];
  }

  for (var i = 0; i < goldcardNumbersArray.length; ++i)
  {
    query = query + '&goldcardnumber=' + external_urlEncode(goldcardNumbersArray[i]);
  }
  savedGoldcardNumbers = goldcardNumbersArray;
}

function setbuygoldcard(checked)
{
  setbuygoldcards(checked);
}

function setbuygoldcards(checked)
{
  if (checked)
  {
    query = query + '&buygoldcards=1';
    savedBuyGoldcards = true;
  }
}

function setpromotionalcode(promotionalcode)
{
  query = query + '&promotionalcode=' + external_urlEncode(external_trim(promotionalcode));
}

function setpaylater(paylater)
{
  query = query + '&paylater=' + paylater;
}

function setlanguage(language)
{
  query = query + '&language=' + language;
}

function setGtagTagId(tagId)
{
  gtagTagId = tagId;
  query = query + '&gtagtagid=' + external_urlEncode(tagId);
}

function setGtagDebugMode()
{
  gtagDebugMode = true;
  query = query + '&gtagdebugmode=1';
}

function setgoogleanalytics(account)
{
  // Since pre-GA4 Google Analytics service is discontinued since July of 2023,
  // our old GA integration is now useless. So we now interpret the ID set with
  // the legacy GA setup function (setgoogleanalytics()) as GA4 Google Tag ID,
  // to avoid requiring Supplier sites to switch to the new GA4 setup function
  // (setGtagTagId()), which is especially useful for PonoRez WP plug-in users.
  if (!gtagTagId)
      // (But only if the new GA4 setup function was not already called.)
  {
    setGtagTagId(account);
  }

  // googleanalyticsaccount = account;
  // query = query + '&googleanalyticsaccount=' + external_urlEncode(account);
}

function checkDateAndGuestsAndUpgrades(options)
{
  options = (options && typeof(options) === 'object' ? options : { });
  var dateAfterGuestsUpgrades = options.dateAfterGuestsUpgrades || false;

  if (!reservationmode)
  {
    // old-style reservation mode guessing
    var modefield = document.getElementById('mode');
    if(modefield != undefined) reservationmode = modefield.value;
  }
  if (!reservationmode) reservationmode = 'reservation';

  // Date (if before Guests/Upgrades):

  if (!dateAfterGuestsUpgrades)
  {
    if (reservationmode == 'reservation' && activitydate == '')
    {
      alert('Please select activity date');
      return false;
    }
  }

  // Guests:

  var haveMode2Errors = false;
  var activeMode2GuestsTypes = 0;
  for (var guestTypeId in guestCountMap)
  {
    if (!guestCountMap.hasOwnProperty(guestTypeId)) continue;
    var guestCountString = guestCountMap[guestTypeId];
    if (!checkpositivenumber(guestCountString))
    {
      haveMode2Errors = true;
      continue;
    }
    if (parseInt(guestCountString) != 0)
    {
      activeMode2GuestsTypes++;
    }
  }
  // seats1 etc. are deprecated
  if (haveMode2Errors || !checkpositivenumber(seats1) || !checkpositivenumber(seats2) || !checkpositivenumber(seats3) || !checkpositivenumber(seats4) || !checkpositivenumber(seats5) ||
      (activeMode2GuestsTypes == 0 && parseInt(seats1) == 0 && parseInt(seats2) == 0 && parseInt(seats3) == 0 && parseInt(seats4) == 0 && parseInt(seats5) == 0))
  {
	  alert('Please enter valid guests number(s)');
	  return false;
  }
  if (activeMode2GuestsTypes > 5)
  {
    alert('Sorry, you can\'t order seats for more than 5 different guest types');
    return false;
  }

  // Upgrades:

  for (var upgradeId in upgradeCountMap)
  {
    if (!upgradeCountMap.hasOwnProperty(upgradeId)) continue;
    var upgradeCount = upgradeCountMap[upgradeId];
    if (!checkpositivenumber(upgradeCount))
    {
      alert('Please enter valid upgrades number(s)');
      return false;
    }
  }

  // Date (if after Guests/Upgrades):

  if (dateAfterGuestsUpgrades)
  {
    if (reservationmode == 'reservation' && activitydate == '')
    {
      alert('Please select activity date');
      return false;
    }
  }

  return true;
}

// Deprecated but still supported.
function addseats1(seats, price, priceafterdiscount)
{
  query = query + '&seats1=' + external_urlEncode(external_trim(seats));
  seats1 = external_trim(seats);
}

function addseats2(seats, price, priceafterdiscount)
{
  query = query + '&seats2=' + external_urlEncode(external_trim(seats));
  seats2 = external_trim(seats);
}

function addseats3(seats, price, priceafterdiscount)
{
  query = query + '&seats3=' + external_urlEncode(external_trim(seats));
  seats3 = external_trim(seats);
}

function addseats4(seats, price, priceafterdiscount)
{
  query = query + '&seats4=' + external_urlEncode(external_trim(seats));
  seats4 = external_trim(seats);
}

function addseats5(seats, price, priceafterdiscount)
{
  query = query + '&seats5=' + external_urlEncode(external_trim(seats));
  seats5 = external_trim(seats);
}

function addseatsfromselect(select)
{
  var id = select.options[select.selectedIndex].value;
  if (id.match(/^t(\d+)$/))
  {
    var guestTypeId = RegExp.$1;
    addGuests(guestTypeId, 1);
  }
  else
  {
    query = query + '&seats' + id + '=1';
    if (id == '1') seats1 = 1;
    if (id == '2') seats2 = 1;
    if (id == '3') seats3 = 1;
    if (id == '4') seats4 = 1;
    if (id == '5') seats5 = 1;
  }
}

// Not supported, but may be used somewhere.
function addextras(name, amount, price, priceafterdiscount)
{
}

function detectNonProductionBaseUrl()
{
  var nonProductionBaseUrl = null;

  var thisScriptName = 'external/functions'; // regexp-friendly
  var scripts = document.getElementsByTagName("script");
 	for (var i = scripts.length - 1; i >= 0; i--) {
    // Note reverse iteration: we only want the last matching <script>.
    // "scripts" is a NodeList, so no reverse() for us.
 		var script = scripts[i];
    if (new RegExp('^(.*\\/)'+thisScriptName+'\\.js(?:\\?|$)').test(script.src))
    {
      var scriptBaseUrl = RegExp.$1;
      if (/^https?:\/\/[a-z][a-z0-9]*(?:-[a-z0-9]+)*[:\/]/i.test(scriptBaseUrl) // single-work hostname: development environment
          || /reservation_test/.test(scriptBaseUrl)) // reservation_test: test environment
      {
        nonProductionBaseUrl = scriptBaseUrl;
      }

      break;
    }
 	}

  return nonProductionBaseUrl;
}

function getUrlOriginPart(url)
{
  if (/^(https?:\/\/[^\/]+)/.test(url))
  {
    return RegExp.$1;
  }
  else
  {
    return url;
  }
}

function getJsVersion()
{
  var scripts = document.getElementsByTagName('script');
  for (var i = 0; i < scripts.length; ++i)
  {
    if (scripts[i].src && /external\/functions.js\?(?:.*&)?jsversion=([^&]+)(?:&|$)/.test(scripts[i].src))
    {
      return RegExp.$1;
    }
  }

  return '';
}

var baseUrl = detectNonProductionBaseUrl() || 'https://ponorez.online/reservation/';
var baseurl = baseUrl; // temporary: needed for old, cached versions of 'accommodation-1.js'
var baseUrlOrigin = getUrlOriginPart(baseUrl);

var jsVersion = getJsVersion();

function external_additionalQueryParams()
{
  var params = '';
  if (cancellationPolicyConfirmed) params += '&policy=1';

  return params;
}

function availability_popup()
{
  if (!external_validateActivityInfo()) return;

  var action = 'AVAILABILITYCHECKPAGE';
  if (reservationmode == 'giftcertificate')
  {
    action = 'GIFTCERTIFICATESELECTUPGRADES';
  }

  var d=window.open('', '_blank', 'width=800,height=200,scrollbars=yes,resizable=yes,top=100,left=100').document;
  d.open("text/html", "replace");
  // Separate write() calls are a work-around for a bug in IE11 for Windows 10
  // that exists as of 2017-12-16 (version 11.125.16299.0, update 11.0.49).
  // With this bug, the page is not fully loaded, and the console shows the error
  // "HTML0: Parser Terminated Early around <SCRIPT ...".
  d.write("<!DOCTYPE html><html><head>"
  );d.write(""
    + "<script type='text/javascript'>var q='"+ query + external_additionalQueryParams() +"';</script>"
  );d.write(""
    + "<script type='text/javascript' src='"+ baseUrl + "common/jquery/jquery-1.9.1.min.js'></script>"
  );d.write(""
    + "<script type='text/javascript' src='"+ baseUrl + "external/functions.js?jsversion=" + jsVersion + "'></script>"
  );d.write(""
    + "<script type='text/javascript' src='"+ baseUrl + 'externalservlet?action=' + action + query + external_additionalQueryParams() +"'></script>"
  );d.write(""
    + (gtagTagId == '' ? '' : "<script type='text/javascript' async src='https://www.googletagmanager.com/gtag/js?id=" + external_urlEncode(gtagTagId) + "'></script><script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', '" + external_escapeForJsString(gtagTagId) + "'" + (gtagDebugMode ? ", { debug_mode: true }" : "") + ");</script>")
  );d.write(""
    + (googleanalyticsaccount == '' ? '' : "<script src='https://ssl.google-analytics.com/ga.js' type='text/javascript'></script><script type='text/javascript'>try { pageTracker = _gat._createTracker('" + googleanalyticsaccount + "'); pageTracker._setAllowLinker(true); pageTracker._setAllowHash(false); pageTracker._trackPageview(); } catch(err) {}</script>")
    // ^^^ Note that, contrary to other places in External interface, we do not set
    // cookie_flags='SameSite=None;Secure'. This is because this code is executed
    // in the context of the invoking (Supplier's) site, and we don't need and
    // don't want to mess with its GA cookies.
  );d.write(""
    + "</head><body onload='javascript:showContent();'><table width='100%' height=170><tr><td width=100% valign=center align=center><b>C h e c k i n g . . .</b></td></tr></table></body></html>");
  d.close();
}

function availability_iframe() {
  if (!external_validateActivityInfo()) return;

  var action = 'AVAILABILITYCHECKPAGE';
  if (reservationmode == 'giftcertificate')
  {
    action = 'GIFTCERTIFICATESELECTUPGRADES';
  }

  var url = baseUrl + 'externalservlet?action=' + action + '&iframe=1' + query + external_additionalQueryParams();
  external_supplyGoogleLinkerInstrumentedUrl(url, function(instrumentedUrl) {
    try {
      if (window.A3HE) {
        window.A3HE.open({ url: instrumentedUrl });
        return;
      }
    } catch(e) {}

    availability_popup();
  });
}

function availability_iframe_popup() {
  var check = false;
  (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)))check = true})(navigator.userAgent||navigator.vendor||window.opera);

  if (check) {
    availability_popup();
  } else {
    availability_iframe();
  }
}

function availability_page()
{
  if (!external_validateActivityInfo()) return;

  var action = 'AVAILABILITYCHECKPAGE';
  if (reservationmode == 'giftcertificate')
  {
    action = 'GIFTCERTIFICATESELECTUPGRADES';
  }

  window.document.location = baseUrl + 'externalservlet?action=' + action + '&mobileapp=1' + query + external_additionalQueryParams();
}

function external_validateActivityInfo(options)
{
  if (!checkDateAndGuestsAndUpgrades(options))
  {
    return false;
  }

  var totalGuestCountMode2 = 0;
  for (var guestTypeId in guestCountMap)
  {
    if (!guestCountMap.hasOwnProperty(guestTypeId)) continue;
    var guestCount = parseInt(guestCountMap[guestTypeId]);
    totalGuestCountMode2 += guestCount;
  }
  var totalGuestCountMode1 = parseInt(seats1) + parseInt(seats2) + parseInt(seats3) + parseInt(seats4) + parseInt(seats5);

  var totalGuestCount = Math.max(totalGuestCountMode1, totalGuestCountMode2);
  var GOLDCARD_BASE_GUEST_LIMIT = 4; // Logic.GOLDCARD_BASE_GUEST_LIMIT
  var GOLDCARD_BASE_PRICE = 30; // Logic.GOLDCARD_BASE_PRICE
  var GOLDCARD_EXTRA_GUEST_PRICE = 7.5; // Logic.GOLDCARD_EXTRA_GUEST_PRICE
  if (savedBuyGoldcards && totalGuestCount > GOLDCARD_BASE_GUEST_LIMIT)
  {
    var goldcardPrice = GOLDCARD_BASE_PRICE + GOLDCARD_EXTRA_GUEST_PRICE * (totalGuestCount - GOLDCARD_BASE_GUEST_LIMIT);
    if (!confirm(
            'Shaka Gold Membership for base price ($' + GOLDCARD_BASE_PRICE + ') '
            + 'covers at most ' + GOLDCARD_BASE_GUEST_LIMIT + ' guests.\n'
            + 'For your booking of ' + totalGuestCount + ' guests, the Shaka Gold '
            + 'Membership will cost $' + goldcardPrice + '.\n'
            + 'Do you want to continue?'))
    {
      return false;
    }
  }
  else if (savedGoldcardNumbers.length != 0 && totalGuestCount > savedGoldcardNumbers.length * GOLDCARD_BASE_GUEST_LIMIT)
  {
    var minCoveredGuestCount = savedGoldcardNumbers.length * GOLDCARD_BASE_GUEST_LIMIT;
    var maxAdditionalGuestCount = totalGuestCount - minCoveredGuestCount;
    var maxGoldcardPrice =
            GOLDCARD_BASE_PRICE
            + GOLDCARD_EXTRA_GUEST_PRICE * Math.max(0, maxAdditionalGuestCount - GOLDCARD_BASE_GUEST_LIMIT);
    //var additionalGoldcardsNeeded = Math.ceil(totalGuestCount / GOLDCARD_BASE_GUEST_LIMIT) - savedGoldcardNumbers.length;
    if (!confirm(
            'One Shaka Gold Membership for base price ($' + GOLDCARD_BASE_PRICE + ') '
            + 'covers at most ' + GOLDCARD_BASE_GUEST_LIMIT + ' guests.\n'
            + 'Unless your existing Shaka Gold Membership(s) already cover all your '
            + totalGuestCount + ' guests, '
            + 'your purchase will include an additional Membership for up to $' + maxGoldcardPrice + ' '
            + 'to cover the remaining guests.\n'
            + 'Do you want to continue?'))
    {
      return false;
    }
  }

  return true;
}

function purchase(transportationPreselected)
{
  var url = baseUrl + 'externalservlet?action=EXTERNALPURCHASEPAGE' + (ismobileapp ? '&mobileapp=1' : '')
      + (isIframe ? '&iframe=1' : '') + '&mode='
      + reservationmode + query + external_additionalQueryParams()
      + '&transportationpreselected=' + transportationPreselected;

  external_supplyGoogleLinkerInstrumentedUrl(url, function(instrumentedUrl) {
    if (typeof(pageTracker) !== 'undefined' && typeof(pageTracker._getLinkerUrl) === 'function')
    {
      instrumentedUrl = external_fixGoogleTrackerUrl(pageTracker._getLinkerUrl(instrumentedUrl));
    }

    if (ismobileapp || isIframe)
    {
      window.document.location = instrumentedUrl;
    }
    else
    {
      window.opener.document.location = instrumentedUrl;
      window.close();
    }
  })
}

function external_supplyGoogleLinkerInstrumentedUrl(url, fn)
{
  external_supplyGoogleLinkerValue(function(googleLinkerValue) {
    var instrumentedUrl = (googleLinkerValue !== undefined
        ? url + '&_gl=' + external_urlEncode(googleLinkerValue)
        : url
    );
    fn(instrumentedUrl);
  });
}

function external_supplyGoogleLinkerValue(fn)
{
  // The code that generates the linker value is based on David Vallejo's article and code,
  // see https://www.thyngster.com/cross-domain-tracking-on-google-analytics-4-ga4
  // and https://github.com/analytics-debugger/google-tag-linker .

  if (!(gtagTagId && typeof(gtag) === 'function'))
  {
    fn(undefined);
    return;
  }

  var alreadyCalled = false;
  var checkAndCall = function() {
    if (alreadyCalled) return false;

    var shortenedGaCookiesObject = getShortenedGaCookiesObject();
    if (!shortenedGaCookiesObject) return false;

    // We could pass 'shortenedGaCookiesObject' to google_tag_data.glBridge.generate() from
    // analytics.js, but we chose to implement linker value generation ourselves instead.

    var encodedGaCookies = [];
    for (name in shortenedGaCookiesObject)
    {
      if (!shortenedGaCookiesObject.hasOwnProperty(name)) continue;
      encodedGaCookies.push([name, btoa(shortenedGaCookiesObject[name]).replace(/=/g, '.')].join('*'));
    }

    var googleLinkerValue = [ "1", calculateLinkerFingerprintHash(encodedGaCookies), encodedGaCookies.join('*') ].join('*');
    fn(googleLinkerValue);

    alreadyCalled = true;
    return true;
  };

  // Maybe we already have gtag's cookies?
  if (checkAndCall()) return;

  gtag('get', gtagTagId, 'dummy', function () {
    // When we get here, gtag.js should have already set all its cookies.
    checkAndCall();
  });

  // In case we didn't get a callback from gtag, or it didn't result in a call:
  var backupCall = function() {
    if (!alreadyCalled)
    {
      // Check again if we can produce a linker value...
      if (!checkAndCall())
      {
        // If not, then we'll proceed without it.
        fn(undefined);
        alreadyCalled = true;
      }
    }
  }
  setTimeout(backupCall, 0.2);

  function calculateLinkerFingerprintHash(values)
  {
    // Build Finger Print String
    var fingerPrintString = [window.navigator.userAgent, new Date().getTimezoneOffset(), window.navigator.userLanguage ||
    window.navigator.language, Math.floor(new Date().getTime() / 60 / 1E3) - 0, values ? values.join('*') : ""].join("*");

    // make a CRC Table
    var c;
    var crcTable = [];
    for (var n = 0; n < 256; n++) {
      c = n;
      for (var k = 0; k < 8; k++) {
        c = c & 1 ? 0xEDB88320 ^ c >>> 1 : c >>> 1;
      }
      crcTable[n] = c;
    }
    // Create a CRC32 Hash
    var crc = 0 ^ -1;
    for (var i = 0; i < fingerPrintString.length; i++) {
      crc = crc >>> 8 ^ crcTable[(crc ^ fingerPrintString.charCodeAt(i)) & 0xFF];
    }
    // Convert the CRC32 Hash to Base36 and return the value
    crc = ((crc ^ -1) >>> 0).toString(36);
    return crc;
  }

  function getShortenedGaCookiesObject()
  {
    var cookieNames = [ '_ga', '_ga_' + getGaCookieSubname(gtagTagId) ]

    var cookiePairArray = (';' + document.cookie).split(/\s*;\s*/);
    var shortenedCookiesObject = { };
    for (var i = 0; i < cookiePairArray.length; i++)
    {
      if (/^\s*([^=;\s]+)\s*=\s*([^=;\s]+)\s*$/.test(cookiePairArray[i]))
      {
        var name = RegExp.$1;
        var value = RegExp.$2;
        if (cookieNames.indexOf(name) >= 0)
        {
          if (/^G[A-Z]1\.[0-9]\.(.+)$/.test(value))
          {
            value = RegExp.$1;
          }
          shortenedCookiesObject[name] = value;
        }
      }
    }

    if (shortenedCookiesObject['_ga'] && shortenedCookiesObject['_ga_' + getGaCookieSubname(gtagTagId)])
    {
      return shortenedCookiesObject;
    }
    else
    {
      return undefined;
    }

    function getGaCookieSubname(tagId)
    {
      if (/^[A-Z]+-(.+)$/i.test(tagId))
      {
        return RegExp.$1;
      }
      else
      {
        return tagId;
      }
    }
  }
}

function replacePage(html, cssArray)
{
  jQuery("body").html(html).css(cssArray);
}

window.A3HE = (function register_a3h_listener() {
  if(window.A3HE) return window.A3HE;

  function createElement(name, attributes) {
    attributes = attributes || {};
    var el = document.createElement(name);
    for(var attr in attributes) {
      if (attributes.hasOwnProperty(attr)) {
        el[attr] = attributes[attr];
      }
    }
    return el;
  }

  function createExternalPurchaseFrame() {
    var externalpurchaseframecontainer = document.getElementById("external-purchase-frame-container");
    if (externalpurchaseframecontainer) return;

    var root = createElement("DIV", {id: "external-purchase-frame-container"});
    var el = createElement("LINK", {
      rel: "stylesheet",
      href: baseUrl + "external/style/iframe.css"
    });
    root.appendChild(el);

    el = createElement("DIV", { id: "close", onclick: close });
    el.innerHTML = '<a>&times;</a>';
    root.appendChild(el);

    el = createElement("DIV", {id: "shadow"});
    root.appendChild(el);

    document.body.appendChild(root);
  }

  function open(message) {
    createExternalPurchaseFrame();

    //console.log('open', message);
    var url = message.url;
    var root = document.getElementById("external-purchase-frame-container");
    if (!root) return;

    if (url.indexOf("iframe=") == -1)
    {
      url = url + (url.indexOf("?") === -1 ? "?" : "&") + "iframe=1";
    }

    var iframe = document.getElementById("external-purchase-frame-container-iframe");

    root.className = 'showing';
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';

    if (!iframe) {
      iframe = createElement("IFRAME", {
        id: "external-purchase-frame-container-iframe",
        frameBorder: 0,
        border: 0,
        width: "100%",
        src: url
      });
      iframe.style.opacity = 0;
      root.appendChild(iframe);
    }
    else {
      iframe.style.opacity = 0;
      iframe.src = url;
    }
  }

  function close(message) {
    //console.log('close', message);
    message = message || {};
    var root = document.getElementById("external-purchase-frame-container"), iframe = document.getElementById("external-purchase-frame-container-iframe");
    if (!root) { return; }
    root.className = '';
    if (iframe) { root.removeChild(iframe); }

    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';

    if (message.returnUrl) {
      window.location.href = message.returnUrl;
    }
  }

  function show(message) {
    var iframe = document.getElementById("external-purchase-frame-container-iframe");
    if (iframe) {
      iframe.style.opacity = '';
    }
  }

  var disallowIframe = !window.JSON || !window.addEventListener;
  if (disallowIframe) return null;

  //createExternalPurchaseFrame();

  var processors = {};
  //processors['a3h:open'] = open;
  processors['a3h:close'] = close;
  processors['a3h:show'] = show;

  var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent",
      eventer = window[eventMethod],
      messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

  eventer(messageEvent, function(e) {
    if (e.origin !== baseUrlOrigin) {
      return;
    }
    try {
      var message = JSON.parse(e.message || e.data), processor;
      if (message && message.event) {
        processor = processors[message.event];
      }
      if (processor) {
        processor(message);
      }
    } catch (e) {
    }
  }, false);

  return {
    open: open,
    close: close
  };
})();

window.A3H = (function() {
  if (window.A3H) return window.A3H;

  function postIt(message) {
    var t = window.opener || window.parent;
    t.postMessage(JSON.stringify(message), '*');
  }

  function close(returnUrl) {
    postIt({event: 'a3h:close', returnUrl: returnUrl});
  }

  function show() {
    postIt({event: 'a3h:show'});
  }

  return {
    close: close,
    show: show
  };
})();
