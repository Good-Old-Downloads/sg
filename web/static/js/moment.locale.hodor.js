function hodorArray(int, o){
  if (typeof(o) === 'undefined') {o = {}}
  if (typeof(o.pp) !== 'string') {o.pp = ''}
  if (typeof(o.ap) !== 'string') {o.ap = ''}
  var h = [];
  for (var i = 0; i < int; i++) h.push(o.pp+'hodor'+o.ap);
  if (o.uc) { h = h.map(function(v){return v.charAt(0).toUpperCase() + v.slice(1);}); }
  return h;
}

moment.locale('hodor', {
    months : hodorArray(12, {uc: true}),
    monthsShort : hodorArray(12),
    monthsParseExact : true,
    weekdays : hodorArray(7),
    weekdaysShort : hodorArray(7, {pp: '.'}),
    weekdaysMin : hodorArray(7, {uc: true}),
    weekdaysParseExact : true,
    longDateFormat : {
        LT : 'HH:mm',
        LTS : 'HH:mm:ss',
        L : 'DD/MM/YYYY',
        LL : 'D MMMM YYYY',
        LLL : 'D MMMM YYYY HH:mm',
        LLLL : 'dddd D MMMM YYYY HH:mm'
    },
    calendar : {
        sameDay : '[Hodor:] LT',
        nextDay : '[Hodor] LT',
        nextWeek : 'dddd [Hodor] LT',
        lastDay : '[Hodor] LT',
        lastWeek : 'dddd [Hodor] LT',
        sameElse : 'L'
    },
    relativeTime : {
        future : 'Hodor %s',
        past : 'Hodor hodor %s',
        s : 'Hodor hodors',
        m : 'Hodor hodor',
        mm : '%d hodor',
        h : 'Hodor hodor',
        hh : '%d hodor',
        d : 'hodor hodor',
        dd : '%d hodor',
        M : 'hodor hodor',
        MM : '%d hodor',
        y : 'hodor hodor',
        yy : '%d hodor'
    },
    meridiem : function (hours, minutes, isLower) {
        return hours < 12 ? 'HO' : 'DOR';
    },
});