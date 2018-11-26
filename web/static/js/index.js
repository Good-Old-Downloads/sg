////
// Initiate cards
////
var elem = document.querySelector('#game-cards-container');
var msnry = new Packery(elem, {
  itemSelector: '.game-card',
  columnWidth: '.game-card-sizer',
  percentPosition: true,
  horizontalOrder: true
});

imagesLoaded('.card-bg', { background: true }).on('progress', function(instance, currentImage) {
  currentImage.element.style.opacity = 1;
});

function reloadMasonary(){
  msnry.layout();
}