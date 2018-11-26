// Add new game
document.getElementById('game-add').addEventListener('submit', function(evt) {
    evt.preventDefault();
    var form = new FormData(this);
    http.post({
        url: '/api/game/add',
        data: form,
        headers: {
            'X-Api-Key': __APIKEY
        }
    }, function(data){
        data = JSON.parse(data);
        window.alert(data.MSG);
    });
});

// Add new game Steam
document.getElementById('game-add-steam').addEventListener('submit', function(evt) {
    evt.preventDefault();
    var form = new FormData(this);
    http.post({
        url: '/api/game/add/steam',
        data: form,
        headers: {
            'X-Api-Key': __APIKEY
        }
    }, function(data){
        data = JSON.parse(data);
        window.alert(data.MSG);
    });
});

var featuredForm = document.getElementById('game-featured');
new Autocomplete(featuredForm.querySelector('.game-autocomplete')).init();

var featuredPreview = document.getElementById('featured');
var pckry = new Packery(featuredPreview, {
    gutter: 4,
    itemSelector: '.drag',
    horizontalOrder: true,
    horizontal: true
});

var items = featuredPreview.getElementsByClassName('drag');
for (var i = 0; i < items.length; i++) {
    var draggie = new Draggabilly(items[i], {
        axis: 'x'
    });
    pckry.bindDraggabillyEvents(draggie);
}

featuredForm.addEventListener('submit', function(evt){
    evt.preventDefault();
    var template = '<span class="drag" title="{{ name }}" data-game-id="{{ id }}"><div class="fa fa-times remove" data-game-id="{{ id }}"></div><img src="{{ src }}"></span>';
    var featured = new FormData(this);
    featured.append('add', 1);
    http.post({
        url: '/admin/games/editFeatured',
        data: featured
    }, function(data){
        var game = JSON.parse(data);
        game.src = game.cover;
        var html = Mustache.render(template, game);
        var el = document.createElement('div');
        el.innerHTML = html;
        el = el.firstElementChild;
        featuredPreview.insertBefore(el, featuredPreview.firstChild);
        pckry.prepended(el);
        var draggie = new Draggabilly(el, {
            axis: 'x'
        });
        pckry.bindDraggabillyEvents(draggie);
        relayout();
        updateOrder();
    });
});

featuredPreview.addEventListener('click', function(evt){
    if (evt.target && evt.target.classList.contains('remove')) {
        pckry.remove(evt.target.closest('.drag'));
        pckry.layout();
    }
});

pckry.on('dragItemPositioned', updateOrder);
pckry.on('removeComplete', updateOrder);
relayout();

function updateOrder(){
    var items = pckry.getItemElements();
    var newOrder = [];
    for (var i = 0; i < items.length; i++) {
        newOrder.push(items[i].dataset.gameId);
    }
    http.post({
        url: '/admin/games/editFeatured',
        data: {update: newOrder.join(',')}
    }, function(){});
}

function relayout(){
    imagesLoaded('#featured').on('progress', function() {
        pckry.layout();
    });
}