var elem = document.querySelector('#game-cards-container');
var masonaryOptions = {
  itemSelector: '.game-card',
  columnWidth: '.game-card-sizer',
  percentPosition: true,
  horizontalOrder: true
};

var msnry = new Packery(elem, masonaryOptions);
imagesLoaded('.card-bg', { background: true }).on('progress', function(instance, currentImage) {
  currentImage.element.style.opacity = 1;
});

var searchTerm = document.getElementById('__search-block').querySelector('[data-term]').dataset.term;

var genres = document.querySelectorAll('#genres-dropdown input.genre');
var checkedGenres = [];
if (location.search.substring(1) !== '') {
  if (typeof(http.fromParam(location.search.substring(1)).genres) === 'string') {
    checkedGenres = http.fromParam(location.search.substring(1)).genres.split(',');
  }
}

for (var i = 0; i < genres.length; i++) {
  genres[i].addEventListener('change', function(evt) {
    if (this.checked){
      checkedGenres.push(evt.target.dataset.slug);
    } else {
      checkedGenres.splice(checkedGenres.indexOf(evt.target.dataset.slug), 1);
    }
    var currentParams;
    if (location.search.substring(1) === '') {
      currentParams = {};
    } else {
      currentParams = http.fromParam(location.search.substring(1));
    }

    currentParams.ajaxSearch = 1;
    currentParams.genres = checkedGenres.join(',');

    history.pushState(null, null, updateUrlParameter(window.location.href, 'genres', checkedGenres.join(',')));
    // Get url of TITLE and DATE then use updateUrlParameter to add genres to it
    var titleBtn = document.querySelector('.search-settings .title');
    var dateBtn = document.querySelector('.search-settings .date');
    titleBtn.href = updateUrlParameter(titleBtn.getAttribute('href'), 'genres', checkedGenres.join(','));
    dateBtn.href = updateUrlParameter(dateBtn.getAttribute('href'), 'genres', checkedGenres.join(','));
    if (window.location.pathname.indexOf('/group/') !== -1) {
      http.get({url: "/group/"+searchTerm+"/", data: currentParams}, loadDone);
    } else {
      http.get({url: "/search/"+searchTerm+"/", data: currentParams}, loadDone);
    }
  });
}


function loadDone(res){
  document.querySelector('#__search-block').innerHTML = res;
  var elem = document.querySelector('#game-cards-container');
  msnry = new Packery(elem, masonaryOptions);
  time.init(elem);
  imagesLoaded('.card-bg', { background: true }).on('progress', function(instance, currentImage) {
    currentImage.element.style.opacity = 1;
  });
}