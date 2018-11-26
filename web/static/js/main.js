// @koala-prepend "compat.js"
// @koala-prepend "visualcaptcha.vanilla.js"
document.body.classList.remove('no-js');

var http = function() {
    this.toParam = function(obj){
        var query = [];
        for (var key in obj) {
            query.push(encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]));
        }
        return query.join('&');
    };
    this.fromParam = function(str){
        return JSON.parse('{"' + decodeURI(str).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g,'":"') + '"}');
    };
    this.get = function(settings, call) {
        var param = this.toParam(settings.data);
        var xmlHttp = new XMLHttpRequest();
        xmlHttp.onreadystatechange = function() { 
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200){
                call(xmlHttp.responseText);
            }
        };

        xmlHttp.open('GET', settings.url+'?'+param, true);
        if (typeof(settings.headers) !== 'undefined') {
            for (var key in settings.headers) {
                // check if the property/key is defined in the object itself, not in parent
                if (settings.headers.hasOwnProperty(key)) {
                    xmlHttp.setRequestHeader(key, settings.headers[key]);
                }
            }
        }
        xmlHttp.send(null);
        return xmlHttp;
    };
    this.post = function(settings, call) {
        var formdata = true;
        try {
           settings.data.entries(); // test if formdata
        } catch (e) {
            formdata = false;
        }
        var xmlHttp = new XMLHttpRequest();
        xmlHttp.onreadystatechange = function() { 
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200){
                call(xmlHttp.responseText);
            }
        };
        xmlHttp.open('POST', settings.url, true);
        if (typeof(settings.headers) !== 'undefined') {
            for (var key in settings.headers) {
                // check if the property/key is defined in the object itself, not in parent
                if (settings.headers.hasOwnProperty(key)) {
                    xmlHttp.setRequestHeader(key, settings.headers[key]);
                }
            }
        }
        if(formdata){
            xmlHttp.send(settings.data);
        } else {
            var param = this.toParam(settings.data);
            xmlHttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xmlHttp.send(param);
        }
        return xmlHttp;
    };
};

// Use moment.js from the DOM
var timeyWimey = function(container) {
    if (typeof(container) === 'undefined') {
        this.container = document;
    } else {
        this.container = container;
    }
    this.init = function(){
        // do it for everything on the page
        var momentEls = this.container.querySelectorAll('[data-moment]');
        for (var i = 0; i < momentEls.length; i++) {
            var el = momentEls[i];
            this.format(el.dataset.moment, el);
        }
    };
    this.format = function(type, el){
        var format = el.dataset.format;
        var titleFormat = 'MMMM Do YYYY, h:mm:ss A';
        var time = el.dataset.time;
        switch (type) {
            // add more case-switches here for more types (define types in the DOM as a "data-moment" attribute)
            case 'epoch':
                if (format === 'fromnow') {
                    this.replace(moment.unix(parseInt(time)).format(titleFormat), moment.unix(parseInt(time)).fromNow(), el);
                }
                break;
        }
    };
    this.replace = function(title, text, el) {
        el.setAttribute('title', title);
        el.textContent = text;
    };
};

var Autocomplete = function(el) {
    var element = el.getElementsByTagName('input')[0];
    var dataList = el.getElementsByTagName('ul')[0];

    this.init = function(){
        element.addEventListener('input', debounce(this.query.bind(this), 300));
        dataList.addEventListener('click', function(evt){
            if (evt.target && typeof(evt.target.dataset.gameId) !== 'undefined') {
                var gameId = evt.target.dataset.gameId;
                element.value = gameId;
                dataList.innerHTML = '';
            }
        });
    };
    this.query = function(){
        var updateList = this.updateList.bind(this);
        var currentSearch = null;
        if (currentSearch !== null) {
            currentSearch.abort();
        }
        if (element.value.trim() === '') {
            return;
        }
        dataList.innerHTML = '';
        currentSearch = http.get({
            url: '/api/autocomplete',
            data: {term: element.value}
        }, function(data){
            data = JSON.parse(data);
            if (typeof(data) === 'object'){
                updateList(data.hits);
            }
        });
    };
    this.updateList = function(data){
        // Make new items
        for (var i = 0; i < data.length; i++) {
            var id = data[i]._id;
            var name = data[i]._source.name;

            var item = document.createElement('li');
            item.dataset.gameId = id;
            item.textContent = name;
            dataList.appendChild(item);
        }
    };
};

// https://gist.github.com/niyazpk/f8ac616f181f6042d1e0
// Modified by https://gist.github.com/amorgner
function updateUrlParameter(uri, key, value) {
  // remove the hash part before operating on the uri
  var i = uri.indexOf('#');
  var hash = i === -1 ? ''  : uri.substr(i);
  uri = i === -1 ? uri : uri.substr(0, i);

  var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
  var separator = uri.indexOf('?') !== -1 ? "&" : "?";

  if (!value) {
    // remove key-value pair if value is empty
    uri = uri.replace(new RegExp("([?&]?)" + key + "=[^&]*", "i"), '');
    if (uri.slice(-1) === '?') {
      uri = uri.slice(0, -1);
    }
    // replace first occurrence of & by ? if no ? is present
    if (uri.indexOf('?') === -1) uri = uri.replace(/&/, '?');
  } else if (uri.match(re)) {
    uri = uri.replace(re, '$1' + key + "=" + value + '$2');
  } else {
    uri = uri + separator + key + "=" + value;
  }
  return uri + hash;
}

// https://davidwalsh.name/javascript-debounce-function
function debounce(func, wait, immediate) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

new Clipboard('[data-clip-links-legacy]', {
    text: function(trigger) {
        var children = trigger.parentNode.childNodes;
        var links = [];
        for (var i = 0; i < children.length; i++) {
            var node = children[i];
            if (node.tagName === 'LI'){
                links.push(node.getElementsByTagName('a')[0].getAttribute('href'));
            }
        }
        return links.join('\n');
    }
});

new Clipboard('[data-clip-links]', {
    text: function(trigger) {
        var anchors = trigger.nextElementSibling.nextElementSibling.nextElementSibling.querySelectorAll('a.item');
        var links = [];
        for (var i = 0; i < anchors.length; i++) {
            var node = anchors[i];
            links.push(node.href);
        }
        return links.join('\n');
    }
});

new Headroom(document.getElementById("navigation")).init();
new Headroom(document.getElementById("navigation-mobile")).init();

////
// Click NFO
////
function showNFO(rlsName, type){
    var nfoContainer = document.querySelector('#modal-nfo + .modal .modal__inner');
    var nfoArea = document.querySelector('#modal-nfo + .modal pre');
    // Open modal
    document.getElementById('modal-nfo').checked = true;
    // Clear old NFO
    nfoArea.innerHTML = '';
    // Reset font size
    nfoArea.style.fontSize = null;

    switch (type) {
      case 'desktop':
        http.get({
            url: '/nfo/',
            data: {release: rlsName}
        }, function(data){
            var res = JSON.parse(data);
            var nfo = res.MSG.replace(/[^\S\r\n]+$/gm, ""); // Strip empty spaces at the end
            nfo = nfo.split(/\r?\n/);
            //var length = nfo.map(function(item){return item.trim();}).sort(function (a, b){ return b.length - a.length; })[0].length; // Get length of longest line with shitespaces stripped
            nfo = nfo.map(function(item){return item.slice(-length);});

            nfoArea.innerHTML = nfo.join("\n");
        });
        break;
      case 'mobile':
        http.get({
            url: '/nfo/',
            data: {release: rlsName}
        }, function(data){
            var res = JSON.parse(data);
            var nfo = res.MSG.replace(/[^\S\r\n]+$/gm, ""); // Strip empty spaces at the end
            nfo = nfo.split(/\r?\n/);
            var length = nfo.map(function(item){return item.trim();}).sort(function (a, b){ return b.length - a.length; })[0].length; // Get length of longest line with shitespaces stripped
            var width = ((nfoContainer.offsetWidth-6)/length)*2; // Calculate font-size based on nfo container width
            nfoArea.style.fontSize = Math.round(width*100)/100+'px';
            nfoArea.innerHTML = nfo.join("\n");
        });
        break;
    }
    toggleScroll();
}

function toggleSearch(){
    if (searchResults.classList.value === 'show') {
        searchResults.classList.remove('show');
        document.getElementById('darken').classList.remove('on');
    } else {
        searchResults.classList.add('show');
        document.getElementById('darken').classList.add('on');
    }
}
function toggleScroll(){
    if (document.body.classList[0] === 'no-scroll') {
        document.body.classList.remove('no-scroll');
    } else {
        document.body.classList.add('no-scroll');
    }
}

// All pages
var http = new http();
var time = new timeyWimey();
time.init();

// "Open all links" button
document.addEventListener('click', function(evt) {
    if (evt.target && ('openBatch' in evt.target.dataset || 'openBatch' in evt.target.parentNode.dataset)) {
        var anchors = evt.target.parentNode.parentNode.getElementsByTagName('a');
        if (evt.target.dataset.openBatch === "new") {
            anchors = evt.target.nextElementSibling.nextElementSibling.querySelectorAll('a.item');
        }
        var urls = [];
        for (var i = 0; i < anchors.length; i++) {
            urls.push(anchors[i].href);
        }
        var delay = 0;
        for (var i = 0; i < urls.length; i++) {
            if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1){
                (function(index) {
                    setTimeout(function(){
                        var a = document.createElement('a');
                        a.download = '';
                        a.href = urls[index];
                        a.target = '_blank';
                        a.dispatchEvent(new MouseEvent('click'));
                    }, 100 * ++delay);
                })(i);
            } else {
                (function(index) {
                    setTimeout(function(){
                        window.open(urls[index], '_blank');
                    }, 1000);
                })(i);
            }
        }
    }
}, false);

/////
// Search
/////
var searchBtn = document.getElementById('search-btn');
var searchResults = document.getElementById('search-results');
var innerResults = searchResults.querySelector('.inner');
var currentSearch = null;

var search = debounce(function() {
    if (currentSearch !== null) {
        currentSearch.abort();
    }
    if (this.value.trim() === '') {
        return;
    }
    currentSearch = http.get({
        url: '/api/autocomplete',
        data: {term: this.value}
    }, function(data){
        data = JSON.parse(data);
        if (searchResults.classList.value !== 'show') {
            toggleSearch();
        }
        innerResults.innerHTML = '';
        for (var i = 0; i < data.hits.length; i++) {
            var game = data.hits[i]._source;
            var template = document.getElementById('tpl-search').innerHTML;
            var html = Mustache.render(template, {
                gameSlug: game.slug,
                gameName: game.name,
                //releases: game.releases
            });
            innerResults.innerHTML += html;
        }
    });
}, 300);

document.querySelector('#search-bar input').addEventListener('input', search);

document.querySelector('#search-bar').addEventListener('submit', function(evt){
    evt.preventDefault();
    var term = this.querySelector('input').value;
    window.location.href = '/search/'+encodeURIComponent(term);
});

var elHideSearch = document.querySelectorAll('[data-hide-autocomplete]');
for (var i = 0; i < elHideSearch.length; i++) {
  elHideSearch[i].addEventListener('click', toggleSearch);
}


/////
// NFO showers
/////

// Use event delegation cause the search uses ajax for genre switching
document.addEventListener('click', function(evt) {
    if (evt.target && 'nfo' in evt.target.dataset) {
        showNFO(evt.target.dataset.nfo, 'desktop');
    }
    if(evt.target && 'nfoMobile' in evt.target.dataset) {
        showNFO(evt.target.dataset.nfoMobile, 'mobile');
    }
    if (evt.target && 'nfomobile' in evt.target.dataset) {
        showNFOMobile(evt.target);
    }
});

// Show NFO's for mobile
function showNFOMobile(target){
    var nfoContainer = target.parentNode.parentNode.querySelector('.nfo-container');
    var nfoArea = nfoContainer.getElementsByTagName('pre')[0];
    if (nfoContainer.classList.contains('hidden')) {
        nfoContainer.classList.remove('hidden');
        http.get({
            url: '/nfo/',
            data: {release: target.dataset.nfomobile}
        }, function(data){
            var res = JSON.parse(data);
            var nfo = res.MSG.replace(/[^\S\r\n]+$/gm, ""); // Strip empty spaces at the end
            nfo = nfo.split(/\r?\n/);
            var length = nfo.map(function(item){return item.trim();}).sort(function (a, b){ return b.length - a.length; })[0].length; // Get length of longest line with shitespaces stripped
            var width = ((nfoContainer.offsetWidth-6)/length)*2; // Calculate font-size based on nfo container width
            nfoArea.style.fontSize = Math.round(width*100)/100+'px';
            nfoArea.innerHTML = nfo.join("\n");
        });
    } else {
        nfoContainer.classList.add('hidden');
    }
}

// Check every "change" event and toggle scrolling if it's a modal
document.addEventListener('change', function(evt) {
    if (evt.target && evt.target.classList.contains('modal-state')) {
        toggleScroll();
    }
});

document.addEventListener('click', function(evt) {
    // Calculate height for modals with 'modal-calc-height'
    if (evt.target && evt.target.classList.contains('modal-calc-height') && evt.target.tagName === 'LABEL') {
        var openedModal = document.getElementById(evt.target.getAttribute('for')).nextElementSibling.getElementsByClassName('modal__inner')[0];
        var height = 0;
        for (var i = 0; i < openedModal.childNodes.length; i++) {
            var elHeight = openedModal.childNodes[i].offsetHeight;
            if (!isNaN(elHeight)) {
                height = height+elHeight;
            }
        }
        openedModal.style.maxHeight = height+'px';
    }
});

/////
// Edit Game
/////
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.classList.contains('edit-btn')) {
        toggleScroll();
        // Open modal
        document.getElementById('modal-editgame').checked = true;
        var template = document.getElementById('tpl-editgame').innerHTML;
        var container = document.querySelector('#modal-editgame + .modal .editgame-container');
        var id = evt.target.dataset.gameId;
        container.innerHTML = '';

        http.get({
            url: '/admin/ajax/game',
            data: {gameId: id},
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            var res = JSON.parse(data);
            res.MSG.renderDate = function(){
                return function(val, render) {
                    return moment.unix(parseInt(render(val))).format('YYYY-MM-DD');
                };
            };
            res.MSG.ifselected = function(){
                return function(val, render) {
                    var status = render(val);
                    status = JSON.parse(status);
                    if (status.status === status.value) {
                        return 'selected';
                    }
                };
            };
            var html = Mustache.render(template, res.MSG);
            html = document.createRange().createContextualFragment(html);
            container.appendChild(html);
            new Autocomplete(container.querySelector('.game-autocomplete')).init();
        });
    }
    if (evt.target && evt.target.classList.contains('links-delete-batch')) {
        evt.preventDefault();
        var linksDeleteForm = new FormData();

        var checks = evt.target.closest('.links').querySelectorAll('input.link-check:checked');
        for (var i = 0; i < checks.length; i++) {
            var val = checks[i].value;
            linksDeleteForm.append('linkIds[]', val);
        }

        http.post({
            url: '/api/release/scene/links/remove',
            data: linksDeleteForm,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                for (var i = 0; i < checks.length; i++) {
                    checks[i].parentNode.parentNode.parentNode.removeChild(checks[i].parentNode.parentNode);
                }
            }
        });
    }
    // TGDB Images search
    if (evt.target && evt.target.classList.contains('form-images-edit-tgdb')) {
        evt.preventDefault();
        var gamename = evt.target.dataset.gameName;
        var type = evt.target.dataset.searchType;
        http.post({
            url: '/admin/ajax/tgdb_proxy',
            data: {'name': gamename},
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            var images = '';
            var loop;
            if (type === 'poster') {
                loop = data.posters;
            } else if (type === 'background'){
                loop = data.backgrounds;
            }
            for (var i = 0; i < loop.length; i++) {
                images += '<img src="'+data.base+loop[i]+'">';
            }
            document.getElementById('tgdb-selector-container').innerHTML = images;
        });
    }
});

document.addEventListener('change', function(evt) {
    if (evt.target && evt.target.classList.contains('releases-select-all')) {
        var checks = evt.target.closest('.releases').querySelectorAll('input.release-check');
        for (var i = 0; i < checks.length; i++) {
            if (evt.target.checked) {
                checks[i].checked = true;
            } else {
                checks[i].checked = false;
            }
        }
    }
    if (evt.target && evt.target.classList.contains('links-select-all')) {
        var checks = evt.target.closest('.links').querySelectorAll('input.link-check');
        for (var i = 0; i < checks.length; i++) {
            if (evt.target.checked) {
                checks[i].checked = true;
            } else {
                checks[i].checked = false;
            }
        }
    }
    if (evt.target && evt.target.classList.contains('releases-batch-action')) {
        var searchBar = evt.target.parentNode.getElementsByClassName('game-autocomplete')[0];
        if (evt.target.value === 'move') {
            searchBar.classList.remove('hidden');
        } else {
            searchBar.classList.add('hidden');
        }
    }
});

document.addEventListener('submit', function(evt) {
    if (evt.target && evt.target.classList.contains('form-game-edit')) {
        evt.preventDefault();
        var gameform = new FormData(evt.target);
        http.post({
            url: '/admin/ajax/game',
            data: gameform,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                window.alert('Success!');
            } else {
                window.alert(data.MSG);
            }
        });
    }
    if (evt.target && evt.target.classList.contains('form-images-edit')) {
        evt.preventDefault();
        var imagesForm = new FormData(evt.target);
        http.post({
            url: '/admin/ajax/game',
            data: imagesForm,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                window.alert('Successfully Saved!');
            } else {
                window.alert(data.MSG);
            }
        });
    }
    if (evt.target && evt.target.classList.contains('form-release-edit')) {
        evt.preventDefault();
        var form = new FormData(evt.target);
        http.post({
            url: '/admin/ajax/game',
            data: form,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                window.alert('Success!');
            } else {
                window.alert(data.MSG);
            }
        });
    }
    if (evt.target && evt.target.classList.contains('form-release-editnfo')) {
        evt.preventDefault();
        var nfoform = new FormData(evt.target);
        http.post({
            url: '/api/nfo/add',
            data: nfoform,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                window.alert('Success!');
            } else {
                window.alert(data.MSG);
            }
        });
    }
    if (evt.target && evt.target.classList.contains('form-release-editlink')) {
        evt.preventDefault();
        var linksForm = new FormData(evt.target);
        http.post({
            url: '/api/release/scene/links/update',
            data: linksForm,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                window.alert('Success!');
            } else {
                window.alert(data.MSG);
            }
        });
    }
    if (evt.target && evt.target.classList.contains('releases-batch-edit')) {
        evt.preventDefault();
        var gameMoveform = new FormData(evt.target);

        var checks = evt.target.closest('.releases').querySelectorAll('input.release-check:checked');
        for (var i = 0; i < checks.length; i++) {
            var val = checks[i].value;
            gameMoveform.append('releaseIds[]', val);
        }

        var url;
        switch (gameMoveform.get('action')){
            case 'move':
                url = '/api/release/move';
                break;
            case 'delete':
                var releasesStr = 'releases';
                if (checks.length === 1) {
                    releasesStr = 'release';
                }
                if (confirm("Are you sure you want to delete "+checks.length+" "+releasesStr+"?")) {
                    url = '/api/release/delete';
                } else {
                    return;
                }
                break;
        }

        http.post({
            url: url,
            data: gameMoveform,
            headers: {
                'X-Api-Key': __APIKEY
            }
        }, function(data){
            data = JSON.parse(data);
            if (data.SUCCESS) {
                for (var i = 0; i < checks.length; i++) {
                    checks[i].parentNode.parentNode.parentNode.removeChild(checks[i].parentNode.parentNode);
                }
            }
        });
    }
});

document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.classList.contains('links-modal-trigger')) {
        var modal = document.querySelector('#'+evt.target.getAttribute('for')+' + .modal');
        http.get({
            url: '/getlinks/',
            data: {id: evt.target.dataset.id}
        }, function(data){
            modal.getElementsByClassName('links-loading')[0].classList.add('hidden');
            modal.getElementsByClassName('links-block')[0].innerHTML = data;
        });
    }
});

/////
// Captcha
/////
var captcha;
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.matches('.__vote-modal-trigger')) {
        evt.stopPropagation();
        evt.preventDefault();
        toggleScroll();
        var id =  parseInt(evt.target.dataset.id);
        voteBtn.classList.add('hidden');
        document.getElementById('vote-captcha-message').classList.add('hidden');
        document.getElementById('vote-captcha').classList.remove('hidden');
        document.getElementById('vote-captcha-success').classList.add('hidden');
        captcha = visualCaptcha('vote-captcha', {
            captcha: {
                numberOfImages: 9,
                url: window.location.origin+'/api/captcha',
                randomParam: 'what-are-you-looking-at',
                routes: {
                    start: '/begin',
                    image: '/img',
                },
                callbacks: {
                    loaded: function(captcha){
                        // Open modal
                        document.getElementById('modal-captcha').checked = true;
                        captcha.releaseId = id; // hacky hack so vote button can get it
                        captcha.voteTrigger = evt.target; // hacky hack so vote button can get it

                        // Stop # when clicking anchors
                        var anchorOptions = document.getElementById('vote-captcha').getElementsByClassName('img');
                        var anchorList = Array.prototype.slice.call(anchorOptions);
                        anchorList.forEach(function(anchor){
                            anchor.addEventListener('click', function(evt){
                                evt.preventDefault();
                                voteBtn.classList.remove('hidden');
                            }, false);
                        });
                    }
                }
            }
        });
    }
}, false);

// Validate when click vote button
var voteBtn = document.getElementsByClassName('__vote')[0];
voteBtn.addEventListener('click', function(evt){
    evt.preventDefault();
    var captchaData = captcha.getCaptchaData();
    if (captchaData.valid) {
        var capName = captcha.imageFieldName();
        var capValue = captchaData.value;
        var postData = {rls_id: captcha.releaseId};
        postData[capName] = capValue;
        var captchaMsg = document.getElementById('vote-captcha-message');
        var captchaMsgSuccess = document.getElementById('vote-captcha-success');
        http.post({
            url: '/api/captcha/vote',
            data: postData
        }, function(res){
            var ret = JSON.parse(res);
            if (ret.SUCCESS) {
                captcha.voteTrigger.classList.add('hidden');
                document.getElementById('vote-captcha').classList.add('hidden');
                captchaMsgSuccess.classList.remove('hidden');
                voteBtn.classList.add('hidden');
                captchaMsg.classList.add('hidden');
            } else {
                captcha.refresh();
                captchaMsgSuccess.classList.add('hidden');
                voteBtn.classList.add('hidden');
                captchaMsg.classList.remove('hidden');
                captchaMsg.classList.add('txt-red');
                captchaMsg.innerText = ret.MSG;
            }
        });
    }
}, false);